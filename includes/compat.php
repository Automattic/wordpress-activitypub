<?php
/**
 * ActivityPub implementation for WordPress/PHP functions either missing from older WordPress/PHP versions or not included by default.
 *
 * @package Activitypub
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

if ( ! function_exists( 'str_ends_with' ) ) {
	/**
	 * Polyfill for `str_ends_with()` function added in PHP 8.0.
	 *
	 * Performs a case-sensitive check indicating if
	 * the haystack ends with needle.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The substring to search for in the `$haystack`.
	 *
	 * @return bool True if `$haystack` ends with `$needle`, otherwise false.
	 */
	function str_ends_with( $haystack, $needle ) {
		return strlen( $needle ) === 0 || substr( $haystack, - strlen( $needle ) ) === $needle;
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
		return is_array( $value ) || $value instanceof Countable;
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
	/**
	 * Check if an array is a list.
	 *
	 * An array is considered a list if its keys are a range of numbers
	 * starting from 0 and ending at count( $array ) - 1.
	 *
	 * @param array $input The array to check.
	 *
	 * @return bool True if `$input` is a list, otherwise false.
	 */
	function array_is_list( $input ) {
		if ( ! is_array( $input ) ) {
			return false;
		}

		if ( array_values( $input ) === $input ) {
			return true;
		}

		$next_key = -1;

		foreach ( $input as $k => $v ) {
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
