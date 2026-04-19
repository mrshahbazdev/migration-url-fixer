<?php
/**
 * Per-run DB backup + rollback.
 *
 * For each migration run, we copy affected tables into snapshot tables
 * prefixed with {wp_prefix}muf_backup_{run_id}_ so the user can restore.
 *
 * @package MigrationUrlFixer
 */

namespace MUF;

defined( 'ABSPATH' ) || exit;

class Backup {

	/**
	 * Create a new run ID.
	 *
	 * @return string
	 */
	public static function new_run_id() {
		return gmdate( 'Ymd_His' ) . '_' . wp_generate_password( 6, false, false );
	}

	/**
	 * Snapshot a table before modifying it.
	 *
	 * @param string $run_id Run ID.
	 * @param string $table  Full table name (including wp_ prefix).
	 * @return bool
	 */
	public static function snapshot_table( $run_id, $table ) {
		global $wpdb;
		$backup = self::backup_table_name( $run_id, $table );

		// phpcs:disable WordPress.DB.PreparedSQL
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $backup ) );
		if ( $exists ) {
			return true;
		}
		$wpdb->query( "CREATE TABLE `{$backup}` LIKE `{$table}`" );
		$wpdb->query( "INSERT INTO `{$backup}` SELECT * FROM `{$table}`" );
		// phpcs:enable

		return true;
	}

	/**
	 * Restore a snapshot.
	 *
	 * @param string $run_id Run ID.
	 * @return array Result log.
	 */
	public static function restore( $run_id ) {
		global $wpdb;
		$log     = array();
		$backups = self::list_backup_tables( $run_id );

		foreach ( $backups as $backup ) {
			$original = self::original_from_backup( $run_id, $backup );
			if ( ! $original ) {
				continue;
			}
			// phpcs:disable WordPress.DB.PreparedSQL
			$wpdb->query( "TRUNCATE TABLE `{$original}`" );
			$wpdb->query( "INSERT INTO `{$original}` SELECT * FROM `{$backup}`" );
			// phpcs:enable
			$log[] = $original;
		}

		return $log;
	}

	/**
	 * Drop backup tables for a run.
	 *
	 * @param string $run_id Run ID.
	 * @return int Count of tables dropped.
	 */
	public static function discard( $run_id ) {
		global $wpdb;
		$backups = self::list_backup_tables( $run_id );
		foreach ( $backups as $backup ) {
			// phpcs:disable WordPress.DB.PreparedSQL
			$wpdb->query( "DROP TABLE IF EXISTS `{$backup}`" );
			// phpcs:enable
		}
		return count( $backups );
	}

	/**
	 * List all runs available.
	 *
	 * @return array
	 */
	public static function list_runs() {
		global $wpdb;
		$prefix = $wpdb->prefix . MUF_BACKUP_PREFIX;
		// phpcs:ignore WordPress.DB.PreparedSQL
		$rows = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . '%' ) );
		$runs = array();
		foreach ( $rows as $row ) {
			if ( preg_match( '/' . preg_quote( $prefix, '/' ) . '([a-zA-Z0-9_]+)_/', $row, $m ) ) {
				$runs[ $m[1] ] = true;
			}
		}
		return array_keys( $runs );
	}

	/**
	 * Build backup table name.
	 */
	private static function backup_table_name( $run_id, $original ) {
		global $wpdb;
		$short = str_replace( $wpdb->prefix, '', $original );
		return $wpdb->prefix . MUF_BACKUP_PREFIX . $run_id . '_' . $short;
	}

	/**
	 * Reverse of backup_table_name.
	 */
	private static function original_from_backup( $run_id, $backup ) {
		global $wpdb;
		$prefix = $wpdb->prefix . MUF_BACKUP_PREFIX . $run_id . '_';
		if ( 0 !== strpos( $backup, $prefix ) ) {
			return null;
		}
		$short = substr( $backup, strlen( $prefix ) );
		return $wpdb->prefix . $short;
	}

	/**
	 * List backup tables for a run.
	 */
	private static function list_backup_tables( $run_id ) {
		global $wpdb;
		$pattern = $wpdb->prefix . MUF_BACKUP_PREFIX . $run_id . '_%';
		// phpcs:ignore WordPress.DB.PreparedSQL
		return (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pattern ) );
	}
}
