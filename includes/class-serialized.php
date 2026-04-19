<?php
/**
 * Serialized-safe recursive string replace.
 *
 * Walks arrays, objects, JSON strings and PHP-serialized strings, replacing
 * a search string with a replacement without breaking serialized lengths.
 *
 * Adapted conceptually from interconnect/it's Search-Replace-DB (GPL-2.0),
 * rewritten here to keep the plugin self-contained.
 *
 * @package MigrationUrlFixer
 */

namespace MUF;

defined( 'ABSPATH' ) || exit;

class Serialized {

	/**
	 * Recursively replace $from with $to inside $data, safely handling
	 * serialized arrays/objects, JSON strings, and nested structures.
	 *
	 * @param mixed  $data     Data (string, array, object, scalar).
	 * @param string $from     Search string.
	 * @param string $to       Replacement string.
	 * @param bool   $serialized Whether $data came from a serialize() call (internal).
	 *
	 * @return mixed Data with replacements applied.
	 */
	public static function replace( $data, $from, $to, $serialized = false ) {
		try {
			if ( is_string( $data ) && '' !== $data ) {
				$unserialized = @unserialize( $data, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( false !== $unserialized || 'b:0;' === $data ) {
					$data = self::replace( $unserialized, $from, $to, true );
				} elseif ( self::is_json( $data ) ) {
					$decoded = json_decode( $data, true );
					if ( is_array( $decoded ) ) {
						$decoded = self::replace( $decoded, $from, $to, false );
						$data    = function_exists( 'wp_json_encode' )
							? wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
							: json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
					} else {
						$data = self::string_replace( $data, $from, $to );
					}
				} else {
					$data = self::string_replace( $data, $from, $to );
				}
			} elseif ( is_array( $data ) ) {
				$new = array();
				foreach ( $data as $key => $value ) {
					$new[ $key ] = self::replace( $value, $from, $to, false );
				}
				$data = $new;
			} elseif ( is_object( $data ) && ! ( $data instanceof \__PHP_Incomplete_Class ) ) {
				$props = get_object_vars( $data );
				foreach ( $props as $key => $value ) {
					$data->{$key} = self::replace( $value, $from, $to, false );
				}
			}

			if ( $serialized ) {
				return serialize( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			}
		} catch ( \Throwable $e ) {
			// Fail safe: if anything odd happens, return the original value untouched.
			return $data;
		}

		return $data;
	}

	/**
	 * Plain string replace that also handles URL-encoded and scheme-agnostic variants.
	 *
	 * @param string $haystack Source string.
	 * @param string $from     Original URL/string.
	 * @param string $to       Replacement.
	 * @return string
	 */
	public static function string_replace( $haystack, $from, $to ) {
		if ( '' === $haystack ) {
			return $haystack;
		}

		$haystack = str_replace( $from, $to, $haystack );

		$from_enc = rawurlencode( $from );
		$to_enc   = rawurlencode( $to );
		if ( $from_enc !== $from ) {
			$haystack = str_replace( $from_enc, $to_enc, $haystack );
		}

		// JSON-escaped slashes variant (common in Elementor / block attrs).
		$from_slashed = addcslashes( $from, '/' );
		$to_slashed   = addcslashes( $to, '/' );
		if ( $from_slashed !== $from ) {
			$haystack = str_replace( $from_slashed, $to_slashed, $haystack );
		}

		return $haystack;
	}

	/**
	 * Heuristic JSON detection.
	 *
	 * @param string $str Candidate string.
	 * @return bool
	 */
	private static function is_json( $str ) {
		if ( ! is_string( $str ) || strlen( $str ) < 2 ) {
			return false;
		}
		$first = $str[0];
		$last  = $str[ strlen( $str ) - 1 ];
		if ( ! ( ( '{' === $first && '}' === $last ) || ( '[' === $first && ']' === $last ) ) ) {
			return false;
		}
		json_decode( $str );
		return JSON_ERROR_NONE === json_last_error();
	}

	/**
	 * Count how many times $from appears inside $data (recursive).
	 *
	 * @param mixed  $data Data.
	 * @param string $from Needle.
	 * @return int
	 */
	public static function count( $data, $from ) {
		$count = 0;
		if ( is_string( $data ) ) {
			$count += substr_count( $data, $from );
			$from_enc = rawurlencode( $from );
			if ( $from_enc !== $from ) {
				$count += substr_count( $data, $from_enc );
			}
		} elseif ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				$count += self::count( $value, $from );
			}
		} elseif ( is_object( $data ) && ! ( $data instanceof \__PHP_Incomplete_Class ) ) {
			foreach ( get_object_vars( $data ) as $value ) {
				$count += self::count( $value, $from );
			}
		}
		return $count;
	}
}
