<?php
/**
 * Admin page + AJAX endpoints.
 *
 * @package MigrationUrlFixer
 */

namespace MUF;

defined( 'ABSPATH' ) || exit;

class Admin {

	const CAP       = 'manage_options';
	const MENU_SLUG = 'migration-url-fixer';
	const NONCE     = 'muf_nonce';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		add_action( 'wp_ajax_muf_scan', array( $this, 'ajax_scan' ) );
		add_action( 'wp_ajax_muf_batch', array( $this, 'ajax_batch' ) );
		add_action( 'wp_ajax_muf_rollback', array( $this, 'ajax_rollback' ) );
		add_action( 'wp_ajax_muf_discard', array( $this, 'ajax_discard' ) );
	}

	public function register_menu() {
		add_management_page(
			__( 'Migration URL Fixer', 'migration-url-fixer' ),
			__( 'Migration URL Fixer', 'migration-url-fixer' ),
			self::CAP,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue( $hook ) {
		if ( 'tools_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'muf-admin', MUF_PLUGIN_URL . 'assets/admin.css', array(), MUF_VERSION );
		wp_enqueue_script( 'muf-admin', MUF_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), MUF_VERSION, true );
		wp_localize_script(
			'muf-admin',
			'MUF',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE ),
				'i18n'     => array(
					'scanning'    => __( 'Scanning…', 'migration-url-fixer' ),
					'replacing'   => __( 'Replacing…', 'migration-url-fixer' ),
					'done'        => __( 'Done.', 'migration-url-fixer' ),
					'confirm'     => __( 'This will modify your database. A backup will be taken automatically. Continue?', 'migration-url-fixer' ),
					'confirm_rb'  => __( 'Restore the database from this backup? Current data will be overwritten.', 'migration-url-fixer' ),
					'confirm_dis' => __( 'Discard this backup permanently?', 'migration-url-fixer' ),
				),
			)
		);
	}

	public function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'migration-url-fixer' ) );
		}
		include MUF_PLUGIN_DIR . 'admin/views/main.php';
	}

	/**
	 * AJAX: scan (dry-run).
	 */
	public function ajax_scan() {
		$this->verify();
		$from  = $this->get_from_param();
		$areas = $this->sanitize_areas( $_POST['areas'] ?? array() );
		$opts  = $this->opts_from_request();

		if ( '' === $from ) {
			wp_send_json_error( array( 'message' => __( 'Old URL is required.', 'migration-url-fixer' ) ) );
		}
		wp_send_json_success( array( 'counts' => Scanner::scan( $from, $areas, $opts ) ) );
	}

	/**
	 * AJAX: process one batch of replacements.
	 */
	public function ajax_batch() {
		$this->verify();

		$from    = $this->get_from_param();
		$to      = isset( $_POST['to'] ) ? wp_unslash( $_POST['to'] ) : '';
		$area    = isset( $_POST['area'] ) ? sanitize_key( wp_unslash( $_POST['area'] ) ) : '';
		$offset  = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$run_id  = isset( $_POST['run_id'] ) ? sanitize_key( wp_unslash( $_POST['run_id'] ) ) : Backup::new_run_id();
		$dry_run = ! empty( $_POST['dry_run'] );
		$opts    = $this->opts_from_request();

		$to = sanitize_text_field( $to );

		if ( '' === $from || '' === $to ) {
			wp_send_json_error( array( 'message' => __( 'Both URLs are required.', 'migration-url-fixer' ) ) );
		}
		if ( $from === $to ) {
			wp_send_json_error( array( 'message' => __( 'Old and new URLs are identical.', 'migration-url-fixer' ) ) );
		}

		$result = Replacer::process_batch( $from, $to, $area, $offset, $run_id, $dry_run, $opts );
		wp_send_json_success( $result );
	}

	public function ajax_rollback() {
		$this->verify();
		$run_id = isset( $_POST['run_id'] ) ? sanitize_key( wp_unslash( $_POST['run_id'] ) ) : '';
		if ( '' === $run_id ) {
			wp_send_json_error( array( 'message' => __( 'Run ID is required.', 'migration-url-fixer' ) ) );
		}
		$log = Backup::restore( $run_id );
		wp_send_json_success( array( 'restored' => $log ) );
	}

	public function ajax_discard() {
		$this->verify();
		$run_id = isset( $_POST['run_id'] ) ? sanitize_key( wp_unslash( $_POST['run_id'] ) ) : '';
		if ( '' === $run_id ) {
			wp_send_json_error( array( 'message' => __( 'Run ID is required.', 'migration-url-fixer' ) ) );
		}
		$dropped = Backup::discard( $run_id );
		wp_send_json_success( array( 'dropped' => $dropped ) );
	}

	/**
	 * When regex mode is active the "from" field is a PCRE pattern and should not
	 * be coerced with esc_url_raw(). Otherwise sanitize as URL.
	 */
	private function get_from_param() {
		if ( ! isset( $_POST['from'] ) ) {
			return '';
		}
		$raw   = wp_unslash( $_POST['from'] );
		$regex = ! empty( $_POST['regex'] );
		return $regex ? sanitize_text_field( $raw ) : esc_url_raw( $raw );
	}

	private function opts_from_request() {
		$excl_types   = isset( $_POST['exclude_post_types'] ) ? wp_unslash( $_POST['exclude_post_types'] ) : '';
		$excl_options = isset( $_POST['exclude_options'] ) ? wp_unslash( $_POST['exclude_options'] ) : '';
		$excl_types   = is_array( $excl_types ) ? $excl_types : array_filter( array_map( 'trim', explode( ',', (string) $excl_types ) ) );
		$excl_options = is_array( $excl_options ) ? $excl_options : array_filter( array_map( 'trim', explode( ',', (string) $excl_options ) ) );

		return array(
			'regex'              => ! empty( $_POST['regex'] ),
			'case_insensitive'   => ! empty( $_POST['case_insensitive'] ),
			'exclude_post_types' => array_map( 'sanitize_key', $excl_types ),
			'exclude_options'    => array_map( 'sanitize_text_field', $excl_options ),
			'allow_critical'     => ! empty( $_POST['allow_critical'] ),
		);
	}

	private function sanitize_areas( $raw ) {
		$map   = array_keys( Scanner::area_map() );
		$raw   = is_array( $raw ) ? array_map( 'sanitize_key', wp_unslash( $raw ) ) : array();
		$clean = array_values( array_intersect( $map, $raw ) );
		return $clean ?: $map;
	}

	private function verify() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'migration-url-fixer' ) ), 403 );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
	}
}
