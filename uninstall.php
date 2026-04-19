<?php
/**
 * Uninstall handler.
 *
 * Drops any leftover backup tables created by Migration URL Fixer.
 *
 * @package MigrationUrlFixer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$prefix = $wpdb->prefix . 'muf_backup_';
// phpcs:ignore WordPress.DB.PreparedSQL
$tables = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . '%' ) );
foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}
