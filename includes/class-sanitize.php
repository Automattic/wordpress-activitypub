<?php
/**
 * Sanitization file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Model\Blog;

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
		if ( ! \is_array( $value ) ) {
			$value = \explode( PHP_EOL, $value );
		}

		$value = \array_map( 'trim', $value );
		$value = \array_filter( $value );
		$value = \array_map( 'sanitize_url', $value );
		$value = \array_unique( $value );

		return \array_values( $value );
	}

	/**
	 * Sanitize a list of hosts.
	 *
	 * @param string $value The value to sanitize.
	 * @return string The sanitized list of hosts.
	 */
	public static function host_list( $value ) {
		$value = \explode( PHP_EOL, $value );
		$value = \array_map(
			function ( $host ) {
				$host = \trim( $host );
				$host = \strtolower( $host );
				$host = \set_url_scheme( $host );
				$host = \sanitize_url( $host, array( 'http', 'https' ) );

				// Remove protocol.
				if ( \str_contains( $host, 'http' ) ) {
					$host = \wp_parse_url( $host, PHP_URL_HOST );
				}

				return \filter_var( $host, FILTER_VALIDATE_DOMAIN );
			},
			$value
		);

		return \implode( PHP_EOL, \array_filter( $value ) );
	}

	/**
	 * Sanitize a blog identifier.
	 *
	 * @param string $value The value to sanitize.
	 * @return string The sanitized blog identifier.
	 */
	public static function blog_identifier( $value ) {
		// Hack to allow dots in the username.
		$parts     = \explode( '.', $value );
		$sanitized = \array_map( 'sanitize_title', $parts );
		$sanitized = \implode( '.', $sanitized );

		// Check for login or nicename.
		$user = new \WP_User_Query(
			array(
				'search'         => $sanitized,
				'search_columns' => array( 'user_login', 'user_nicename' ),
				'number'         => 1,
				'hide_empty'     => true,
				'fields'         => 'ID',
			)
		);

		if ( $user->get_results() ) {
			\add_settings_error(
				'activitypub_blog_identifier',
				'activitypub_blog_identifier',
				\esc_html__( 'You cannot use an existing author&#8217;s name for the blog profile ID.', 'activitypub' )
			);

			return Blog::get_default_username();
		}

		return $sanitized;
	}

	/**
	 * Get the sanitized value of a constant.
	 *
	 * @param mixed $value The constant value.
	 *
	 * @return string The sanitized value.
	 */
	public static function constant_value( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_string( $value ) ) {
			return esc_attr( $value );
		}

		return $value;
	}
}
