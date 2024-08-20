<?php
/**
 * ActivityPub implementation for WordPress/PHP functions either missing from older WordPress/PHP versions or not included by default.
 */

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * Polyfill for `str_starts_with()` function added in PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if
	 * the haystack begins with needle.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 * @return bool True if `$haystack` starts with `$needle`, otherwise false.
	 */
	function str_starts_with( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}

		return 0 === strpos( $haystack, $needle );
	}
}

if ( ! function_exists( 'get_self_link' ) ) {
	/**
	 * Returns the link for the currently displayed feed.
	 *
	 * @return string Correct link for the atom:self element.
	 */
	function get_self_link() {
		$host = wp_parse_url( home_url() );
		$path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return esc_url( apply_filters( 'self_link', set_url_scheme( 'http://' . $host['host'] . $path ) ) );
	}
}

if ( ! function_exists( 'is_countable' ) ) {
	/**
	 * Polyfill for `is_countable()` function added in PHP 7.3.
	 *
	 * @param mixed $value The value to check.
	 * @return bool True if `$value` is countable, otherwise false.
	 */
	function is_countable( $value ) {
		return is_array( $value ) || $value instanceof \Countable;
	}
}

/**
 * Polyfill for `array_is_list()` function added in PHP 7.3.
 *
 * @param array $array The array to check.
 *
 * @return bool True if `$array` is a list, otherwise false.
 */
if ( ! function_exists( 'array_is_list' ) ) {
	// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
	function array_is_list( $array ) {
		if ( ! is_array( $array ) ) {
			return false;
		}

		if ( array_values( $array ) === $array ) {
			return true;
		}

		$next_key = -1;

		foreach ( $array as $k => $v ) {
			if ( ++$next_key !== $k ) {
				return false;
			}
		}

		return true;
	}
}

if ( ! function_exists( 'str_contains' ) ) {
	/**
	 * Polyfill for `str_contains()` function added in PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if needle is
	 * contained in haystack.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 *
	 * @return bool True if `$needle` is in `$haystack`, otherwise false.
	 */
	function str_contains( $haystack, $needle ) {
		if ( '' === $needle ) {
			return true;
		}

		return false !== strpos( $haystack, $needle );
	}
}
