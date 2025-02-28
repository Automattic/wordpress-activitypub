<?php
/**
 * Sanitization file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Sanitization class.
 */
class Sanitize {
	/**
	 * Sanitize a list of URLs.
	 *
	 * @param string|array $value The value to sanitize.
	 * @return array The sanitized list of URLs.
	 */
	public static function url_list( $value ) {
		if ( ! is_array( $value ) ) {
			$value = explode( PHP_EOL, $value );
		}

		$value = array_map( 'trim', $value );
		$value = array_map( 'sanitize_url', $value );
		$value = array_unique( $value );

		return array_filter( $value );
	}
}
