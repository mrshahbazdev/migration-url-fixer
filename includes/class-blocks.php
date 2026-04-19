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
	 * @param array  $opts    Options passed to Serialized helpers.
	 * @return string Replaced content.
	 */
	public static function replace( $content, $from, $to, array $opts = array() ) {
		if ( '' === $content || false === strpos( $content, '<!-- wp:' ) ) {
			return Serialized::string_replace( $content, $from, $to, $opts );
		}

		$blocks = function_exists( 'parse_blocks' ) ? parse_blocks( $content ) : array();
		if ( empty( $blocks ) ) {
			return Serialized::string_replace( $content, $from, $to, $opts );
		}

		$blocks = self::walk_blocks( $blocks, $from, $to, $opts );

		$out = '';
		foreach ( $blocks as $block ) {
			$out .= function_exists( 'serialize_block' ) ? serialize_block( $block ) : '';
		}
		return $out;
	}

	/**
	 * Walk a list of blocks recursively and replace URLs in attrs + innerHTML.
	 */
	private static function walk_blocks( $blocks, $from, $to, array $opts ) {
		foreach ( $blocks as &$block ) {
			if ( ! empty( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				$block['attrs'] = Serialized::replace( $block['attrs'], $from, $to, $opts, false );
			}
			if ( isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
				$block['innerHTML'] = Serialized::string_replace( $block['innerHTML'], $from, $to, $opts );
			}
			if ( isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
				foreach ( $block['innerContent'] as $i => $chunk ) {
					if ( is_string( $chunk ) ) {
						$block['innerContent'][ $i ] = Serialized::string_replace( $chunk, $from, $to, $opts );
					}
				}
			}
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::walk_blocks( $block['innerBlocks'], $from, $to, $opts );
			}
		}
		return $blocks;
	}
}
