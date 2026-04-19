<?php
/**
 * Gutenberg block parser + URL replacer.
 *
 * Walks parse_blocks() output, replaces URLs inside block attributes
 * (including nested innerBlocks), and serializes blocks back to HTML.
 *
 * @package MigrationUrlFixer
 */

namespace MUF;

defined( 'ABSPATH' ) || exit;

class Blocks {

	/**
	 * Replace URLs inside Gutenberg block content.
	 *
	 * @param string $content Post content.
	 * @param string $from    Old URL.
	 * @param string $to      New URL.
	 * @return string Replaced content.
	 */
	public static function replace( $content, $from, $to ) {
		if ( '' === $content || false === strpos( $content, '<!-- wp:' ) ) {
			// Not block content -> fall through to plain string replace.
			return Serialized::string_replace( $content, $from, $to );
		}

		$blocks = parse_blocks( $content );
		$blocks = self::walk_blocks( $blocks, $from, $to );

		$out = '';
		foreach ( $blocks as $block ) {
			$out .= serialize_block( $block );
		}
		return $out;
	}

	/**
	 * Walk a list of blocks recursively and replace URLs in attrs + innerHTML.
	 *
	 * @param array  $blocks Blocks array.
	 * @param string $from   Old URL.
	 * @param string $to     New URL.
	 * @return array
	 */
	private static function walk_blocks( $blocks, $from, $to ) {
		foreach ( $blocks as &$block ) {
			if ( ! empty( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				$block['attrs'] = Serialized::replace( $block['attrs'], $from, $to, false );
			}
			if ( isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
				$block['innerHTML'] = Serialized::string_replace( $block['innerHTML'], $from, $to );
			}
			if ( isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
				foreach ( $block['innerContent'] as $i => $chunk ) {
					if ( is_string( $chunk ) ) {
						$block['innerContent'][ $i ] = Serialized::string_replace( $chunk, $from, $to );
					}
				}
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::walk_blocks( $block['innerBlocks'], $from, $to );
			}
		}
		return $blocks;
	}

	/**
	 * Count URL occurrences inside block content (for dry-run).
	 *
	 * @param string $content Post content.
	 * @param string $from    Needle.
	 * @return int
	 */
	public static function count( $content, $from ) {
		return Serialized::count( $content, $from );
	}
}
