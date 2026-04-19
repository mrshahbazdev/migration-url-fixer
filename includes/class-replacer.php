<?php
/**
 * Batched URL replacer.
 *
 * @package MigrationUrlFixer
 */

namespace MUF;

defined( 'ABSPATH' ) || exit;

class Replacer {

	/** Default batch size per AJAX / CLI tick. */
	const BATCH_SIZE = 200;

	/**
	 * Process one batch.
	 *
	 * @param string $from    Old URL/pattern.
	 * @param string $to      New URL.
	 * @param string $area    Area key.
	 * @param int    $offset  Current offset.
	 * @param string $run_id  Run ID for backups.
	 * @param bool   $dry_run If true, no writes.
	 * @param array  $opts    Options (regex, case_insensitive, exclude_post_types, exclude_options, allow_critical).
	 *
	 * @return array {processed, changed, next (-1 when done), total, run_id}
	 */
	public static function process_batch( $from, $to, $area, $offset, $run_id, $dry_run = false, array $opts = array() ) {
		global $wpdb;

		$map = Scanner::area_map();
		if ( empty( $map[ $area ] ) ) {
			return array( 'processed' => 0, 'changed' => 0, 'next' => -1, 'total' => 0, 'run_id' => $run_id );
		}
		$cfg     = $map[ $area ];
		$table   = $cfg['table'];
		$id_col  = $cfg['id'];
		$columns = $cfg['columns'];
		$blocks  = isset( $cfg['blocks'] ) ? $cfg['blocks'] : array();

		if ( ! $dry_run && 0 === $offset ) {
			Backup::snapshot_table( $run_id, $table );
		}

		$where = Scanner::build_where( $area, $opts );

		// phpcs:disable WordPress.DB.PreparedSQL
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE 1=1{$where}" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE 1=1{$where} ORDER BY `{$id_col}` ASC LIMIT %d OFFSET %d",
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
					$new = Blocks::replace( $original, $from, $to, $opts );
				} else {
					$new = Serialized::replace( $original, $from, $to, $opts, false );
				}

				if ( $new !== $original ) {
					$updates[ $col ] = $new;
				}
			}

			if ( ! empty( $updates ) ) {
				$changed++;
				if ( ! $dry_run ) {
					$wpdb->update( $table, $updates, array( $id_col => $row->$id_col ) );
				}
			}
		}

		$next = ( $offset + self::BATCH_SIZE >= $total ) ? -1 : $offset + self::BATCH_SIZE;

		return array(
			'processed' => count( $rows ),
			'changed'   => $changed,
			'next'      => $next,
			'total'     => $total,
			'run_id'    => $run_id,
		);
	}

	/**
	 * Run the full replacement synchronously for every area. Used by WP-CLI.
	 *
	 * @param string   $from    Needle.
	 * @param string   $to      Replacement.
	 * @param array    $areas   Areas to process.
	 * @param bool     $dry_run Dry-run.
	 * @param array    $opts    Options.
	 * @param callable $log     Optional logger callback( string $line ).
	 * @return array Per-area totals.
	 */
	public static function run_all( $from, $to, array $areas, $dry_run, array $opts, $log = null ) {
		$run_id = Backup::new_run_id();
		$totals = array();

		foreach ( $areas as $area ) {
			$offset  = 0;
			$changed = 0;
			$rows    = 0;
			do {
				$res     = self::process_batch( $from, $to, $area, $offset, $run_id, $dry_run, $opts );
				$changed += $res['changed'];
				$rows    += $res['processed'];
				if ( is_callable( $log ) ) {
					call_user_func( $log, sprintf( '%s: offset=%d processed=%d changed=%d total=%d', $area, $offset, $res['processed'], $res['changed'], $res['total'] ) );
				}
				$offset = $res['next'];
			} while ( -1 !== $offset );

			$totals[ $area ] = array(
				'rows'    => $rows,
				'changed' => $changed,
			);
		}
		$totals['_run_id'] = $run_id;
		return $totals;
	}
}
