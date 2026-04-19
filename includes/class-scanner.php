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
	 * @param string $from Old URL.
	 * @param array  $areas Areas to include. Supported keys:
	 *                      'posts', 'postmeta', 'options', 'usermeta', 'comments', 'commentmeta', 'termmeta'.
	 * @return array Associative array of area => count.
	 */
	public static function scan( $from, array $areas ) {
		global $wpdb;
		$result = array();

		if ( in_array( 'posts', $areas, true ) ) {
			$result['posts'] = self::count_column( $wpdb->posts, array( 'post_content', 'post_excerpt', 'post_title', 'guid' ), $from );
		}
		if ( in_array( 'postmeta', $areas, true ) ) {
			$result['postmeta'] = self::count_column( $wpdb->postmeta, array( 'meta_value' ), $from );
		}
		if ( in_array( 'options', $areas, true ) ) {
			$result['options'] = self::count_column( $wpdb->options, array( 'option_value' ), $from );
		}
		if ( in_array( 'usermeta', $areas, true ) ) {
			$result['usermeta'] = self::count_column( $wpdb->usermeta, array( 'meta_value' ), $from );
		}
		if ( in_array( 'comments', $areas, true ) ) {
			$result['comments'] = self::count_column( $wpdb->comments, array( 'comment_content', 'comment_author_url' ), $from );
		}
		if ( in_array( 'commentmeta', $areas, true ) ) {
			$result['commentmeta'] = self::count_column( $wpdb->commentmeta, array( 'meta_value' ), $from );
		}
		if ( in_array( 'termmeta', $areas, true ) ) {
			$result['termmeta'] = self::count_column( $wpdb->termmeta, array( 'meta_value' ), $from );
		}

		return $result;
	}

	/**
	 * Count occurrences of $from across specified text columns of a table.
	 *
	 * Uses LIKE for a fast approximation. Exact serialized/JSON counts happen
	 * during the actual replace phase.
	 *
	 * @param string $table   Table name.
	 * @param array  $columns Columns to scan.
	 * @param string $from    Needle.
	 * @return int
	 */
	private static function count_column( $table, array $columns, $from ) {
		global $wpdb;
		$total = 0;
		$like  = '%' . $wpdb->esc_like( $from ) . '%';
		foreach ( $columns as $col ) {
			// phpcs:ignore WordPress.DB.PreparedSQL
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
					$like
				)
			);
			$total += $count;
		}
		return $total;
	}

	/**
	 * Return mapping of area -> (table, id_column, columns).
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
