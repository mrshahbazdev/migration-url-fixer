<?php
/**
 * Dry-run scanner: counts potential replacements per area without writing.
 *
 * @package MigrationUrlFixer
 */

namespace MUF;

defined( 'ABSPATH' ) || exit;

class Scanner {

	/**
	 * Scan all configured areas and return a per-area count.
	 *
	 * @param string $from  Old URL / pattern.
	 * @param array  $areas Areas to include.
	 * @param array  $opts  Shared options (regex, case_insensitive, exclude_post_types, exclude_options).
	 * @return array area => approximate count.
	 */
	public static function scan( $from, array $areas, array $opts = array() ) {
		global $wpdb;
		$result = array();
		$map    = self::area_map();

		foreach ( $areas as $area ) {
			if ( empty( $map[ $area ] ) ) {
				continue;
			}
			$cfg              = $map[ $area ];
			$result[ $area ]  = self::count_column( $cfg['table'], $cfg['columns'], $from, $opts, self::build_where( $area, $opts ) );
		}

		return $result;
	}

	/**
	 * Build a SQL WHERE clause fragment for exclusions.
	 *
	 * @param string $area Area key.
	 * @param array  $opts Options.
	 * @return string Leading " AND ..." fragment, or empty string.
	 */
	public static function build_where( $area, array $opts ) {
		global $wpdb;

		$clauses = array();

		if ( 'posts' === $area && ! empty( $opts['exclude_post_types'] ) ) {
			$types = array_map( 'sanitize_key', (array) $opts['exclude_post_types'] );
			if ( $types ) {
				$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$clauses[] = $wpdb->prepare( "post_type NOT IN ($placeholders)", $types );
			}
		}
		if ( 'postmeta' === $area && ! empty( $opts['exclude_post_types'] ) ) {
			$types = array_map( 'sanitize_key', (array) $opts['exclude_post_types'] );
			if ( $types ) {
				$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$clauses[]    = $wpdb->prepare(
					"post_id NOT IN ( SELECT ID FROM {$wpdb->posts} WHERE post_type IN ($placeholders) )",
					$types
				);
			}
		}
		if ( 'options' === $area ) {
			$excludes = array_merge(
				self::critical_options_if_protected( $opts ),
				array_map( 'sanitize_text_field', (array) ( $opts['exclude_options'] ?? array() ) )
			);
			foreach ( $excludes as $pattern ) {
				if ( '' === $pattern ) {
					continue;
				}
				$like = self::glob_to_like( $pattern );
				// phpcs:ignore WordPress.DB.PreparedSQL
				$clauses[] = $wpdb->prepare( 'option_name NOT LIKE %s', $like );
			}
		}

		return $clauses ? ' AND ' . implode( ' AND ', $clauses ) : '';
	}

	/**
	 * Critical options protected by default unless allow_critical=true.
	 *
	 * @param array $opts Options.
	 * @return array
	 */
	public static function critical_options_if_protected( array $opts ) {
		if ( ! empty( $opts['allow_critical'] ) ) {
			return array();
		}
		return array( 'siteurl', 'home', 'template', 'stylesheet', 'active_plugins', 'upload_path', 'upload_url_path' );
	}

	/**
	 * Count occurrences of $from across specified text columns of a table.
	 *
	 * For plain mode this is a LIKE-based approximation. For regex/case-insensitive
	 * the count may be slightly inflated since we still scan using LIKE, then the
	 * replacer phase is authoritative.
	 */
	private static function count_column( $table, array $columns, $from, array $opts, $extra_where = '' ) {
		global $wpdb;
		$total = 0;

		if ( ! empty( $opts['regex'] ) ) {
			// Regex mode: count total rows as an upper bound.
			// phpcs:ignore WordPress.DB.PreparedSQL
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE 1=1{$extra_where}" );
			return $count;
		}

		$like = '%' . $wpdb->esc_like( $from ) . '%';
		foreach ( $columns as $col ) {
			$sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` LIKE %s{$extra_where}";
			// phpcs:ignore WordPress.DB.PreparedSQL
			$total += (int) $wpdb->get_var( $wpdb->prepare( $sql, $like ) );
		}
		return $total;
	}

	/**
	 * Convert a simple glob (* wildcard) to a SQL LIKE pattern.
	 *
	 * @param string $glob Glob string.
	 * @return string
	 */
	public static function glob_to_like( $glob ) {
		global $wpdb;
		$escaped = $wpdb->esc_like( $glob );
		return str_replace( array( '*', '?' ), array( '%', '_' ), $escaped );
	}

	/**
	 * Return mapping of area -> (table, id_column, columns, blocks).
	 *
	 * @return array
	 */
	public static function area_map() {
		global $wpdb;
		return array(
			'posts'       => array(
				'table'   => $wpdb->posts,
				'id'      => 'ID',
				'columns' => array( 'post_content', 'post_excerpt', 'post_title', 'guid' ),
				'blocks'  => array( 'post_content' ),
			),
			'postmeta'    => array(
				'table'   => $wpdb->postmeta,
				'id'      => 'meta_id',
				'columns' => array( 'meta_value' ),
			),
			'options'     => array(
				'table'   => $wpdb->options,
				'id'      => 'option_id',
				'columns' => array( 'option_value' ),
			),
			'usermeta'    => array(
				'table'   => $wpdb->usermeta,
				'id'      => 'umeta_id',
				'columns' => array( 'meta_value' ),
			),
			'comments'    => array(
				'table'   => $wpdb->comments,
				'id'      => 'comment_ID',
				'columns' => array( 'comment_content', 'comment_author_url' ),
			),
			'commentmeta' => array(
				'table'   => $wpdb->commentmeta,
				'id'      => 'meta_id',
				'columns' => array( 'meta_value' ),
			),
			'termmeta'    => array(
				'table'   => $wpdb->termmeta,
				'id'      => 'meta_id',
				'columns' => array( 'meta_value' ),
			),
		);
	}
}
