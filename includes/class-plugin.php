<?php
/**
 * Plugin bootstrap.
 *
 * @package MigrationUrlFixer
 */

namespace MUF;

defined( 'ABSPATH' ) || exit;

class Plugin {

	/**
	 * Boot the plugin.
	 */
	public static function boot() {
		load_plugin_textdomain( 'migration-url-fixer', false, dirname( plugin_basename( MUF_PLUGIN_FILE ) ) . '/languages' );

		if ( is_admin() ) {
			Admin::instance()->hooks();
		}
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( plugin_basename( MUF_PLUGIN_FILE ) );
			wp_die( esc_html__( 'Migration URL Fixer requires PHP 7.4 or higher.', 'migration-url-fixer' ) );
		}
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		// Intentionally left blank. We keep backup tables so users can still roll back.
	}
}
