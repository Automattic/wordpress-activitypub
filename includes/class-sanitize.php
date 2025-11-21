<?php
/**
 * Sanitization file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Remote_Actors;
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
			$value = \explode( PHP_EOL, (string) $value );
		}

		$value = \array_filter( $value );
		$value = \array_map( 'trim', $value );
		$value = \array_map( 'sanitize_url', $value );
		$value = \array_unique( $value );

		return \array_values( $value );
	}

	/**
	 * Sanitize and normalize a list of account identifiers to ActivityPub IDs.
	 *
	 * This function processes various identifier formats, such as URLs and
	 * webfinger identifiers, and normalizes them into a consistent format.
	 *
	 * @param string|array $value The value to sanitize.
	 *
	 * @return array The sanitized and normalized list of account identifiers.
	 */
	public static function identifier_list( $value ) {
		if ( ! \is_array( $value ) ) {
			$value = \explode( PHP_EOL, (string) $value );
		}

		$value = \array_filter( $value );
		$uris  = array();

		foreach ( $value as $uri ) {
			$uri = \trim( $uri );
			$uri = \ltrim( $uri, '@' );

			if ( \is_email( $uri ) ) {
				$_uri = Webfinger::resolve( $uri );
				if ( \is_wp_error( $_uri ) ) {
					$uris[] = $uri;
					continue;
				}

				$uri = $_uri;
			}

			$uri   = \sanitize_url( $uri );
			$actor = Remote_Actors::fetch_by_uri( $uri );
			if ( \is_wp_error( $actor ) ) {
				$uris[] = $uri;
			} else {
				$uris[] = \sanitize_url( $actor->guid );
			}
		}

		return \array_values( \array_unique( $uris ) );
	}

	/**
	 * Sanitize a list of hosts.
	 *
	 * @param string $value The value to sanitize.
	 * @return string The sanitized list of hosts.
	 */
	public static function host_list( $value ) {
		$value = \explode( PHP_EOL, (string) $value );
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
		$parts     = \explode( '.', (string) $value );
		$sanitized = \array_map( 'sanitize_title', $parts );
		$sanitized = \implode( '.', $sanitized );

		if ( empty( $sanitized ) ) {
			return Blog::get_default_username();
		}

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

		if ( is_array( $value ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			return print_r( $value, true );
		}

		return $value;
	}

	/**
	 * Sanitize a webfinger identifier.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string The sanitized webfinger identifier.
	 */
	public static function webfinger( $value ) {
		$value = \str_replace( 'acct:', '', $value );
		$value = \trim( $value, '@' );

		return $value;
	}

	/**
	 * Sanitize content for ActivityPub.
	 *
	 * @param string $content The content to convert.
	 *
	 * @return string The converted content.
	 */
	public static function content( $content ) {
		// Only make URLs clickable if no anchor tags exist, to avoid corrupting existing links.
		if ( false === \strpos( $content, '<a ' ) ) {
			$content = \make_clickable( $content );
		}

		$content = \wpautop( $content );
		$content = \wp_kses_post( $content );

		return $content;
	}

	/**
	 * Remove hashtags from content.
	 *
	 * Removes hashtags that appear at the end of the content.
	 * Handles both plain text and HTML content, including hashtags within anchor tags.
	 *
	 * @param string $content  The content to process.
	 * @param array  $hashtags Array of hashtag strings (with or without #).
	 *
	 * @return string The content with trailing hashtags removed.
	 */
	public static function remove_hashtags( $content, $hashtags ) {
		if ( empty( $content ) || empty( $hashtags ) || ! \is_array( $hashtags ) ) {
			return $content;
		}

		// Normalize hashtags: remove # prefix and trim whitespace.
		$normalized_tags = \array_map(
			function ( $tag ) {
				return \trim( \ltrim( $tag, '#' ) );
			},
			$hashtags
		);

		// Remove empty tags.
		$normalized_tags = \array_filter( $normalized_tags );

		if ( empty( $normalized_tags ) ) {
			return $content;
		}

		// Build pattern to match trailing hashtags (at end of content or before closing tags).
		$tag_patterns = array();
		foreach ( $normalized_tags as $tag ) {
			$escaped_tag    = \preg_quote( $tag, '/' );
			$tag_patterns[] = '(?:<a[^>]*>\s*)?#' . $escaped_tag . '(?=\s|<|$)(?:\s*<\/a>)?';
		}

		$combined_pattern = '(?:' . \implode( '|', $tag_patterns ) . ')';

		/*
		 * Pattern explanation:
		 * Match one or more hashtags (plain or in anchor tags) at the end of content.
		 * The pattern matches trailing hashtags before closing HTML tags or at end of string.
		 */
		$pattern = '/(?:\s+' . $combined_pattern . ')+(?=\s*(?:<\/[^>]+>)*\s*$)/i';

		$content = \preg_replace( $pattern, '', $content );

		// Clean up any extra whitespace at end of paragraphs.
		$content = \preg_replace( '/<p>\s*<\/p>/', '', $content );
		$content = \preg_replace( '/\s+<\/p>/', '</p>', $content );
		$content = \preg_replace( '/\s+<\/strong>/', '</strong>', $content );
		$content = \trim( $content );

		return $content;
	}
}
