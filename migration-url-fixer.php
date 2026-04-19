<?php
/**
 * Plugin Name:       Migration URL Fixer
 * Plugin URI:        https://github.com/mrshahbazdev/migration-url-fixer
 * Description:       Fix broken URLs and media paths after a WordPress migration. Gutenberg-block aware, serialized-safe, with dry-run preview, automatic backup, and one-click rollback.
 * Version:           0.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Muhammad Shahbaz
 * Author URI:        https://github.com/mrshahbazdev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       migration-url-fixer
 * Domain Path:       /languages
 *
 * @package MigrationUrlFixer
 */

defined( 'ABSPATH' ) || exit;

define( 'MUF_VERSION', '0.2.0' );
define( 'MUF_PLUGIN_FILE', __FILE__ );
define( 'MUF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MUF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MUF_BACKUP_PREFIX', 'muf_backup_' );

require_once MUF_PLUGIN_DIR . 'includes/class-serialized.php';
require_once MUF_PLUGIN_DIR . 'includes/class-blocks.php';
require_once MUF_PLUGIN_DIR . 'includes/class-backup.php';
require_once MUF_PLUGIN_DIR . 'includes/class-scanner.php';
require_once MUF_PLUGIN_DIR . 'includes/class-replacer.php';
require_once MUF_PLUGIN_DIR . 'includes/class-admin.php';
require_once MUF_PLUGIN_DIR . 'includes/class-network.php';
require_once MUF_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'MUF\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MUF\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'MUF\\Plugin', 'boot' ) );
