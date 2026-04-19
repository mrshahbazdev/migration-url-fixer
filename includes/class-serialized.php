<?php
/**
 * Serialized-safe recursive string replace.
 *
 * Walks arrays, objects, JSON strings and PHP-serialized strings, replacing
 * a search string (plain or regex) with a replacement without breaking
 * serialized lengths.
 *
 * @package MigrationUrlFixer
 */

namespace MUF;

defined( 'ABSPATH' ) || exit;

class Serialized {

	/**
	 * Recursively replace $from with $to inside $data.
	 *
	 * Supported option flags:
	 *   - bool 'regex'            Treat $from as a PCRE regex delimited by `#` (no delimiters required).
	 *   - bool 'case_insensitive' Case-insensitive matching (plain mode only).
	 *
	 * @param mixed  $data       Data (string, array, object, scalar).
	 * @param string $from       Search string.
	 * @param string $to         Replacement string.
	 * @param array  $opts       Options.
	 * @param bool   $serialized Whether $data came from a serialize() call (internal).
	 *
	 * @return mixed Data with replacements applied.
	 */
	public static function replace( $data, $from, $to, array $opts = array(), $serialized = false ) {
		$opts = wp_parse_args(
			$opts,
			array(
				'regex'            => false,
				'case_insensitive' => false,
			)
		);

		try {
			if ( is_string( $data ) && '' !== $data ) {
				$unserialized = @unserialize( $data, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( false !== $unserialized || 'b:0;' === $data ) {
					$data = self::replace( $unserialized, $from, $to, $opts, true );
				} elseif ( self::is_json( $data ) ) {
					$decoded = json_decode( $data, true );
					if ( is_array( $decoded ) ) {
						$decoded = self::replace( $decoded, $from, $to, $opts, false );
						$data    = function_exists( 'wp_json_encode' )
							? wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
							: json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
					} else {
						$data = self::string_replace( $data, $from, $to, $opts );
					}
				} else {
					$data = self::string_replace( $data, $from, $to, $opts );
				}
			} elseif ( is_array( $data ) ) {
				$new = array();
				foreach ( $data as $key => $value ) {
					$new[ $key ] = self::replace( $value, $from, $to, $opts, false );
				}
				$data = $new;
			} elseif ( is_object( $data ) && ! ( $data instanceof \__PHP_Incomplete_Class ) ) {
				$props = get_object_vars( $data );
				foreach ( $props as $key => $value ) {
					$data->{$key} = self::replace( $value, $from, $to, $opts, false );
				}
			}

			if ( $serialized ) {
				return serialize( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			}
		} catch ( \Throwable $e ) {
			return $data;
		}

		return $data;
	}

	/**
	 * String replace helper. Handles regex, case-insensitive, URL-encoded and
	 * slash-escaped variants.
	 *
	 * @param string $haystack Source string.
	 * @param string $from     Needle (or regex pattern).
	 * @param string $to       Replacement.
	 * @param array  $opts     Options.
	 * @return string
	 */
	public static function string_replace( $haystack, $from, $to, array $opts = array() ) {
		if ( '' === $haystack ) {
			return $haystack;
		}
		$regex   = ! empty( $opts['regex'] );
		$case_i  = ! empty( $opts['case_insensitive'] );

		if ( $regex ) {
			$pattern  = self::prepare_regex( $from, $case_i );
			$replaced = @preg_replace( $pattern, $to, $haystack ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null === $replaced ? $haystack : $replaced;
		}

		if ( $case_i ) {
			$haystack = str_ireplace( $from, $to, $haystack );
		} else {
			$haystack = str_replace( $from, $to, $haystack );
		}

		$from_enc = rawurlencode( $from );
		$to_enc   = rawurlencode( $to );
		if ( $from_enc !== $from ) {
			$haystack = $case_i
				? str_ireplace( $from_enc, $to_enc, $haystack )
				: str_replace( $from_enc, $to_enc, $haystack );
		}

		$from_slashed = addcslashes( $from, '/' );
		$to_slashed   = addcslashes( $to, '/' );
		if ( $from_slashed !== $from ) {
			$haystack = $case_i
				? str_ireplace( $from_slashed, $to_slashed, $haystack )
				: str_replace( $from_slashed, $to_slashed, $haystack );
		}

		return $haystack;
	}

	/**
	 * Count how many times $from appears inside $data (recursive).
	 *
	 * @param mixed  $data Data.
	 * @param string $from Needle or pattern.
	 * @param array  $opts Options.
	 * @return int
	 */
	public static function count( $data, $from, array $opts = array() ) {
		$count   = 0;
		$regex   = ! empty( $opts['regex'] );
		$case_i  = ! empty( $opts['case_insensitive'] );

		if ( is_string( $data ) ) {
			if ( $regex ) {
				$pattern = self::prepare_regex( $from, $case_i );
				$n       = @preg_match_all( $pattern, $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$count  += $n ? $n : 0;
			} else {
				$count += $case_i ? substr_count( strtolower( $data ), strtolower( $from ) ) : substr_count( $data, $from );
				$from_enc = rawurlencode( $from );
				if ( $from_enc !== $from ) {
					$count += $case_i ? substr_count( strtolower( $data ), strtolower( $from_enc ) ) : substr_count( $data, $from_enc );
				}
			}
		} elseif ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				$count += self::count( $value, $from, $opts );
			}
		} elseif ( is_object( $data ) && ! ( $data instanceof \__PHP_Incomplete_Class ) ) {
			foreach ( get_object_vars( $data ) as $value ) {
				$count += self::count( $value, $from, $opts );
			}
		}
		return $count;
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
	 * Wrap a user-supplied regex with `#` delimiters if missing, add `i` flag when requested.
	 *
	 * @param string $pattern Pattern.
	 * @param bool   $case_i  Case-insensitive.
	 * @return string
	 */
	private static function prepare_regex( $pattern, $case_i ) {
		$first = $pattern[0] ?? '';
		$has_delim = false;
		if ( $first && in_array( $first, array( '#', '/', '~', '@' ), true ) ) {
			$last_delim = strrpos( $pattern, $first );
			if ( false !== $last_delim && $last_delim > 0 ) {
				$has_delim = true;
			}
		}
		if ( ! $has_delim ) {
			$pattern = '#' . str_replace( '#', '\\#', $pattern ) . '#';
		}
		if ( $case_i && false === strpos( substr( $pattern, strrpos( $pattern, substr( $pattern, 0, 1 ) ) + 1 ), 'i' ) ) {
			$pattern .= 'i';
		}
		return $pattern;
	}
}
