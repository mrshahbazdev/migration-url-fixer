<?php
/**
 * PHPUnit bootstrap for Migration URL Fixer.
 *
 * Stubs the tiny WordPress surface the non-DB classes rely on so the core
 * logic can be tested without spinning up a full WordPress environment.
 *
 * @package MigrationUrlFixer
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'MUF_VERSION' ) ) {
	define( 'MUF_VERSION', 'test' );
}
if ( ! defined( 'MUF_BACKUP_PREFIX' ) ) {
	define( 'MUF_BACKUP_PREFIX', 'muf_backup_' );
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$parsed = get_object_vars( $args );
		} elseif ( is_array( $args ) ) {
			$parsed = $args;
		} else {
			parse_str( (string) $args, $parsed );
		}
		return is_array( $defaults ) ? array_merge( $defaults, $parsed ) : $parsed;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( preg_replace( '/[\r\n\t]+/', ' ', (string) $str ) );
	}
}

/**
 * Minimal wpdb stub used by ScannerTest for prepare() + esc_like().
 */
if ( ! class_exists( 'WPDB_Stub' ) ) {
	class WPDB_Stub {
		public $prefix      = 'wp_';
		public $posts       = 'wp_posts';
		public $postmeta    = 'wp_postmeta';
		public $options     = 'wp_options';
		public $usermeta    = 'wp_usermeta';
		public $comments    = 'wp_comments';
		public $commentmeta = 'wp_commentmeta';
		public $termmeta    = 'wp_termmeta';

		public function esc_like( $str ) {
			return addcslashes( (string) $str, '_%\\' );
		}

		public function prepare( $query, ...$args ) {
			if ( count( $args ) === 1 && is_array( $args[0] ) ) {
				$args = $args[0];
			}
			$i = 0;
			return preg_replace_callback(
				'/%s|%d|%f/',
				function ( $m ) use ( &$i, $args ) {
					$val = $args[ $i++ ] ?? '';
					switch ( $m[0] ) {
						case '%d':
							return (int) $val;
						case '%f':
							return (float) $val;
						default:
							return "'" . addslashes( (string) $val ) . "'";
					}
				},
				$query
			);
		}
	}
}

global $wpdb;
$wpdb = new WPDB_Stub();

require_once dirname( __DIR__ ) . '/includes/class-serialized.php';
require_once dirname( __DIR__ ) . '/includes/class-scanner.php';
