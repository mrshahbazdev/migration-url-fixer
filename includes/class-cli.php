<?php
/**
 * WP-CLI commands for Migration URL Fixer.
 *
 * @package MigrationUrlFixer
 */

namespace MUF;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Find & fix URLs after a WordPress migration.
 */
class CLI {

	/**
	 * Scan (dry-run) for URL occurrences across the database.
	 *
	 * ## OPTIONS
	 *
	 * --from=<url>
	 * : The old URL (or regex pattern if --regex is set).
	 *
	 * [--areas=<areas>]
	 * : Comma-separated list of areas. Default: all.
	 *
	 * [--regex]
	 * : Treat --from as a PCRE pattern.
	 *
	 * [--case-insensitive]
	 * : Case-insensitive match.
	 *
	 * [--exclude-post-types=<types>]
	 * : Comma-separated post types to skip (posts + postmeta areas).
	 *
	 * [--exclude-options=<names>]
	 * : Comma-separated option name globs to skip (`*` wildcard).
	 *
	 * [--allow-critical]
	 * : Allow critical options (siteurl, home, …) to be counted.
	 *
	 * ## EXAMPLES
	 *
	 *     wp muf scan --from=https://old.com
	 *     wp muf scan --from='https?://old\\.com' --regex
	 */
	public function scan( $args, $assoc ) {
		$opts  = self::opts_from_assoc( $assoc );
		$areas = self::areas_from_assoc( $assoc );
		$from  = self::required( $assoc, 'from' );

		$counts = Scanner::scan( $from, $areas, $opts );

		$rows = array();
		$total = 0;
		foreach ( $counts as $area => $n ) {
			$rows[] = array( 'area' => $area, 'matches' => $n );
			$total += $n;
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'area', 'matches' ) );
		\WP_CLI::log( sprintf( 'Total approximate matches: %d', $total ) );
	}

	/**
	 * Replace URLs (with automatic backup + rollback support).
	 *
	 * ## OPTIONS
	 *
	 * --from=<url>
	 * : The old URL (or regex pattern if --regex is set).
	 *
	 * --to=<url>
	 * : The new URL.
	 *
	 * [--areas=<areas>]
	 * : Comma-separated list of areas. Default: all.
	 *
	 * [--dry-run]
	 * : Do not write; report what would change.
	 *
	 * [--regex]
	 * : Treat --from as a PCRE pattern.
	 *
	 * [--case-insensitive]
	 * : Case-insensitive match.
	 *
	 * [--exclude-post-types=<types>]
	 * : Comma-separated post types to skip.
	 *
	 * [--exclude-options=<names>]
	 * : Comma-separated option name globs to skip (`*` wildcard).
	 *
	 * [--allow-critical]
	 * : Allow critical options (siteurl, home, …) to be modified.
	 *   DANGEROUS — prefer using wp option update siteurl ... directly.
	 *
	 * [--network]
	 * : On multisite, run the command for every site in the network.
	 *
	 * [--yes]
	 * : Skip the interactive confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp muf replace --from=https://old.com --to=https://new.com --dry-run
	 *     wp muf replace --from=https://old.com --to=https://new.com --exclude-post-types=revision --yes
	 *     wp muf replace --from=https://old.com --to=https://new.com --network --yes
	 */
	public function replace( $args, $assoc ) {
		$from  = self::required( $assoc, 'from' );
		$to    = self::required( $assoc, 'to' );
		$opts  = self::opts_from_assoc( $assoc );
		$areas = self::areas_from_assoc( $assoc );
		$dry   = ! empty( $assoc['dry-run'] );

		if ( $from === $to ) {
			\WP_CLI::error( 'Old and new URLs are identical.' );
		}

		if ( ! $dry && ! isset( $assoc['yes'] ) ) {
			\WP_CLI::confirm( 'This will modify the database. A backup will be taken automatically. Continue?' );
		}

		$network = ! empty( $assoc['network'] ) && function_exists( 'is_multisite' ) && is_multisite();

		if ( $network ) {
			$blog_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
			foreach ( $blog_ids as $bid ) {
				switch_to_blog( $bid );
				\WP_CLI::log( sprintf( '--- Site #%d (%s) ---', $bid, home_url() ) );
				self::run_one( $from, $to, $areas, $dry, $opts );
				restore_current_blog();
			}
		} else {
			self::run_one( $from, $to, $areas, $dry, $opts );
		}
	}

	/**
	 * List backup runs available for rollback.
	 *
	 * ## EXAMPLES
	 *
	 *     wp muf list-runs
	 */
	public function list_runs() {
		$runs = Backup::list_runs();
		if ( empty( $runs ) ) {
			\WP_CLI::log( 'No backup runs found.' );
			return;
		}
		foreach ( $runs as $run ) {
			\WP_CLI::log( $run );
		}
	}

	/**
	 * Restore a backup run.
	 *
	 * ## OPTIONS
	 *
	 * <run_id>
	 * : Run ID to restore (see `wp muf list-runs`).
	 *
	 * [--yes]
	 * : Skip the interactive confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp muf rollback 20260419_162030_abc123
	 */
	public function rollback( $args, $assoc ) {
		list( $run_id ) = $args;
		if ( ! isset( $assoc['yes'] ) ) {
			\WP_CLI::confirm( 'Restore the database from this backup? Current data will be overwritten.' );
		}
		$log = Backup::restore( $run_id );
		if ( empty( $log ) ) {
			\WP_CLI::error( 'No backup tables found for that run ID.' );
		}
		\WP_CLI::success( 'Restored: ' . implode( ', ', $log ) );
	}

	/**
	 * Discard a backup run.
	 *
	 * ## OPTIONS
	 *
	 * <run_id>
	 * : Run ID to drop.
	 */
	public function discard( $args ) {
		list( $run_id ) = $args;
		$dropped = Backup::discard( $run_id );
		\WP_CLI::success( sprintf( 'Dropped %d backup table(s).', $dropped ) );
	}

	/* Helpers */

	private static function run_one( $from, $to, $areas, $dry, $opts ) {
		$totals = Replacer::run_all( $from, $to, $areas, $dry, $opts, array( __CLASS__, 'cli_log' ) );
		$run_id = $totals['_run_id'];
		unset( $totals['_run_id'] );

		$rows = array();
		foreach ( $totals as $area => $t ) {
			$rows[] = array( 'area' => $area, 'rows' => $t['rows'], 'changed' => $t['changed'] );
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'area', 'rows', 'changed' ) );
		if ( ! $dry ) {
			\WP_CLI::success( sprintf( 'Run ID: %s  (use `wp muf rollback %s` to restore)', $run_id, $run_id ) );
		} else {
			\WP_CLI::log( 'Dry run complete; no changes written.' );
		}
	}

	public static function cli_log( $line ) {
		\WP_CLI::log( $line );
	}

	private static function required( $assoc, $key ) {
		if ( empty( $assoc[ $key ] ) ) {
			\WP_CLI::error( "--{$key} is required." );
		}
		return $assoc[ $key ];
	}

	private static function areas_from_assoc( $assoc ) {
		$all = array_keys( Scanner::area_map() );
		if ( empty( $assoc['areas'] ) ) {
			return $all;
		}
		$list = array_map( 'sanitize_key', array_map( 'trim', explode( ',', $assoc['areas'] ) ) );
		return array_values( array_intersect( $all, $list ) );
	}

	private static function opts_from_assoc( $assoc ) {
		return array(
			'regex'              => ! empty( $assoc['regex'] ),
			'case_insensitive'   => ! empty( $assoc['case-insensitive'] ),
			'exclude_post_types' => ! empty( $assoc['exclude-post-types'] )
				? array_map( 'trim', explode( ',', $assoc['exclude-post-types'] ) )
				: array(),
			'exclude_options'    => ! empty( $assoc['exclude-options'] )
				? array_map( 'trim', explode( ',', $assoc['exclude-options'] ) )
				: array(),
			'allow_critical'     => ! empty( $assoc['allow-critical'] ),
		);
	}
}

\WP_CLI::add_command( 'muf scan',      array( __NAMESPACE__ . '\\CLI', 'scan' ) );
\WP_CLI::add_command( 'muf replace',   array( __NAMESPACE__ . '\\CLI', 'replace' ) );
\WP_CLI::add_command( 'muf list-runs', array( __NAMESPACE__ . '\\CLI', 'list_runs' ) );
\WP_CLI::add_command( 'muf rollback',  array( __NAMESPACE__ . '\\CLI', 'rollback' ) );
\WP_CLI::add_command( 'muf discard',   array( __NAMESPACE__ . '\\CLI', 'discard' ) );
