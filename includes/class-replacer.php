<?php
/**
 * Batched URL replacer.
 *
 * Iterates rows in chunks, applies serialized-safe replacement (plus
 * Gutenberg-aware handling for post_content), and updates changed rows.
 * Designed to be driven by an AJAX loop from the admin UI so large sites
 * don't time out.
 *
 * @package MigrationUrlFixer
 */

namespace MUF;

defined( 'ABSPATH' ) || exit;

class Replacer {

	/** Default batch size per AJAX tick. */
	const BATCH_SIZE = 200;

	/**
	 * Process one batch.
	 *
	 * @param string $from      Old URL.
	 * @param string $to        New URL.
	 * @param string $area      Area key from Scanner::area_map().
	 * @param int    $offset    Current offset.
	 * @param string $run_id    Current run ID (for backups).
	 * @param bool   $dry_run   If true, no writes.
	 * @return array {
	 *   @type int  processed Rows inspected this batch.
	 *   @type int  changed   Rows actually updated.
	 *   @type int  next      Next offset, or -1 if done.
	 *   @type int  total     Total rows in area.
	 * }
	 */
	public static function process_batch( $from, $to, $area, $offset, $run_id, $dry_run = false ) {
		global $wpdb;

		$map = Scanner::area_map();
		if ( empty( $map[ $area ] ) ) {
			return array( 'processed' => 0, 'changed' => 0, 'next' => -1, 'total' => 0 );
		}
		$cfg     = $map[ $area ];
		$table   = $cfg['table'];
		$id_col  = $cfg['id'];
		$columns = $cfg['columns'];
		$blocks  = isset( $cfg['blocks'] ) ? $cfg['blocks'] : array();

		if ( ! $dry_run && 0 === $offset ) {
			Backup::snapshot_table( $run_id, $table );
		}

		// phpcs:disable WordPress.DB.PreparedSQL
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` ORDER BY `{$id_col}` ASC LIMIT %d OFFSET %d",
				self::BATCH_SIZE,
				$offset
			)
		);
		// phpcs:enable

		$changed = 0;
		foreach ( $rows as $row ) {
			$updates = array();
			foreach ( $columns as $col ) {
				if ( ! isset( $row->$col ) ) {
					continue;
				}
				$original = $row->$col;
				if ( '' === $original || null === $original ) {
					continue;
				}

				if ( in_array( $col, $blocks, true ) ) {
					$new = Blocks::replace( $original, $from, $to );
				} else {
					$new = Serialized::replace( $original, $from, $to, false );
				}

				if ( $new !== $original ) {
					$updates[ $col ] = $new;
				}
			}

			if ( ! empty( $updates ) ) {
				$changed++;
				if ( ! $dry_run ) {
					$wpdb->update(
						$table,
						$updates,
						array( $id_col => $row->$id_col )
					);
				}
			}
		}

		$next = ( $offset + self::BATCH_SIZE >= $total ) ? -1 : $offset + self::BATCH_SIZE;

		return array(
			'processed' => count( $rows ),
			'changed'   => $changed,
			'next'      => $next,
			'total'     => $total,
		);
	}
}
