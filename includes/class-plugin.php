<?php
/**
 * Plugin bootstrap.
 *
 * @package MigrationUrlFixer
 */

namespace MUF;

defined( 'ABSPATH' ) || exit;

class Plugin {

	public static function boot() {
		load_plugin_textdomain( 'migration-url-fixer', false, dirname( plugin_basename( MUF_PLUGIN_FILE ) ) . '/languages' );

		if ( is_admin() || ( function_exists( 'is_network_admin' ) && is_network_admin() ) ) {
			Admin::instance()->hooks();
			Network::hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once MUF_PLUGIN_DIR . 'includes/class-cli.php';
		}
	}

	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( plugin_basename( MUF_PLUGIN_FILE ) );
			wp_die( esc_html__( 'Migration URL Fixer requires PHP 7.4 or higher.', 'migration-url-fixer' ) );
		}
	}

	public static function deactivate() {
		// Intentionally left blank; backup tables are preserved so users can still roll back.
	}
}
