<?php

namespace Activitypub;

class Sanitizer {

	/**
	 * Sanitize a multi-dimensional array
	 *
	 * @param array $array The array to sanitize.
	 *
	 * @return array The sanitized array.
	 */
	public static function sanitize_array( $array ) {
		$sanitized_array = array();

		foreach ( $array as $key => $value ) {
			$key = self::sanitize_key( $key );

			if (
				in_array(
					$key,
					array(
						'summary_map',
						'summaryMap',
						'content_map',
						'contentMap',
					),
					true
				)
			) {
				$sanitized_array[ $key ] = self::sanitize_map( $value );
			} elseif (
				in_array(
					$key,
					array(
						'inbox',
						'outbox',
						'followers',
						'following',
					),
					true
				)
			) {
				if ( is_string( $value ) ) {
					$sanitized_array[ $key ] = sanitize_url( $value );
				} else {
					$sanitized_array[ $key ] = '';
				}
			} elseif ( in_array( $key, array( 'summary', 'content' ), true ) ) {
				$sanitized_array[ $key ] = self::sanitize_html( $value );
			} elseif ( is_array( $value ) ) {
				$sanitized_array[ $key ] = self::sanitize_array( $value );
			} else {
				$sanitized_array[ $key ] = self::sanitize_value( $value );
			}
		}

		return $sanitized_array;
	}

	/**
	 * Sanitize a value.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string The sanitized value.
	 */
	public static function sanitize_value( $value ) {
		if ( is_email( $value ) ) {
			return sanitize_email( $value );
		}

		if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return sanitize_url( $value );
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize HTML.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string The sanitized value.
	 */
	public static function sanitize_html( $value ) {
		if ( is_array( $value ) ) {
			return '';
		}

		global $allowedtags;
		$tags = array_merge(
			$allowedtags,
			array( 'p' => array() )
		);

		$value = \preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $value );
		$value = \strip_shortcodes( $value );
		$value = \wptexturize( $value );
		$value = \wp_kses( $value, $tags );

		return $value;
	}

	/**
	 * Sanitize a translation map
	 *
	 * @param array $map The map to sanitize.
	 *
	 * @return array The sanitized map.
	 */
	public static function sanitize_map( $map ) {
		$sanitized_map = array();

		foreach ( $map as $key => $value ) {
			$key = self::sanitize_key( $key );

			$sanitized_map[ $key ] = self::sanitize_html( $value );
		}

		return $sanitized_map;
	}

	/**
	 * Sanitize an array key
	 *
	 * @param string $key The key to sanitize.
	 *
	 * @return string The sanitized key.
	 */
	public static function sanitize_key( $key ) {
		return \preg_replace( '/[^a-zA-Z0-9_\-]/', '', $key );
	}
}
