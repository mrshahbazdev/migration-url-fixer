<?php
/**
 * Multisite network admin integration.
 *
 * Adds a network-level menu entry that explains how to use the plugin
 * across the network via either per-site admin pages or WP-CLI.
 *
 * @package MigrationUrlFixer
 */

namespace MUF;

defined( 'ABSPATH' ) || exit;

class Network {

	public static function hooks() {
		if ( ! is_multisite() ) {
			return;
		}
		add_action( 'network_admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'tools.php',
			__( 'Migration URL Fixer (Network)', 'migration-url-fixer' ),
			__( 'Migration URL Fixer', 'migration-url-fixer' ),
			'manage_network_options',
			'migration-url-fixer-network',
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'migration-url-fixer' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Migration URL Fixer (Network)', 'migration-url-fixer' ); ?></h1>
			<p><?php esc_html_e( 'Run URL replacement across every site in this multisite network.', 'migration-url-fixer' ); ?></p>

			<h2><?php esc_html_e( 'Recommended: WP-CLI (fastest, safest on large networks)', 'migration-url-fixer' ); ?></h2>
			<pre><code>wp muf replace --from=https://old.example.com --to=https://new.example.com --network --yes</code></pre>
			<p class="description">
				<?php esc_html_e( 'Runs inside each subsite context, creating a separate per-site backup. Use --dry-run first.', 'migration-url-fixer' ); ?>
			</p>

			<h2><?php esc_html_e( 'Per-site admin pages', 'migration-url-fixer' ); ?></h2>
			<ol>
				<?php foreach ( get_sites( array( 'number' => 100 ) ) as $site ) : ?>
					<?php
					$url = get_admin_url( (int) $site->blog_id, 'tools.php?page=' . Admin::MENU_SLUG );
					?>
					<li>
						<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $site->domain . $site->path ); ?></a>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
	}
}
