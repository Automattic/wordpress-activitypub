<?php
/**
 * URL functions.
 *
 * Functions for URL manipulation.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Get the REST URL relative to this plugin's namespace.
 *
 * @param string $path Optional. REST route path. Default ''.
 *
 * @return string REST URL relative to this plugin's namespace.
 */
function get_rest_url_by_path( $path = '' ) {
	// We'll handle the leading slash.
	$path            = ltrim( $path, '/' );
	$namespaced_path = sprintf( '/%s/%s', ACTIVITYPUB_REST_NAMESPACE, $path );
	return \get_rest_url( null, $namespaced_path );
}

/**
 * Normalize a URL.
 *
 * @param string $url The URL.
 *
 * @return string The normalized URL.
 */
function normalize_url( $url ) {
	// Remove ActivityPub-specific query parameters.
	$url = \remove_query_arg( array( 'activitypub', 'preview' ), $url );
	$url = \untrailingslashit( $url );
	$url = \preg_replace( '/^https?:\/\/(www\.)?/', '', $url );

	return $url;
}

/**
 * Normalize a host.
 *
 * @param string $host The host.
 *
 * @return string The normalized host.
 */
function normalize_host( $host ) {
	return \preg_replace( '/^www\./', '', $host );
}

/**
 * Check if a URL is from the same domain as the site.
 *
 * @param string $url The URL to check.
 *
 * @return boolean True if the URL is from the same domain, false otherwise.
 */
function is_same_domain( $url ) {
	$remote = \wp_parse_url( $url, PHP_URL_HOST );

	if ( ! $remote ) {
		return false;
	}

	$remote = normalize_host( $remote );
	$self   = normalize_host( home_host() );

	return $remote === $self;
}

/**
 * Retrieves the Host for the current site where the front end is accessible.
 *
 * @return string The host for the current site.
 */
function home_host() {
	return \wp_parse_url( \home_url(), PHP_URL_HOST );
}

/**
 * Get the base URL for uploads.
 *
 * @return string The upload base URL.
 */
function get_upload_baseurl() {
	/**
	 * Early filter to allow plugins to set the upload base URL.
	 *
	 * @param string|false $maybe_upload_dir The upload base URL or false if not set.
	 */
	$maybe_upload_dir = apply_filters( 'pre_activitypub_get_upload_baseurl', false );
	if ( false !== $maybe_upload_dir ) {
		return $maybe_upload_dir;
	}

	$upload_dir = \wp_get_upload_dir();

	/**
	 * Filters the upload base URL.
	 *
	 * @param string $upload_dir The upload base URL. Default \wp_get_upload_dir()['baseurl']
	 */
	return apply_filters( 'activitypub_get_upload_baseurl', $upload_dir['baseurl'] );
}

/**
 * Get the authority (scheme + host) from a URL.
 *
 * @param string $url The URL to parse.
 *
 * @return string|false The authority, or false on failure.
 */
function get_url_authority( $url ) {
	$parsed = wp_parse_url( $url );

	if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
		return false;
	}

	return $parsed['scheme'] . '://' . $parsed['host'];
}

/**
 * Infer a shortname from the Actor ID or URL. Used only for fallbacks,
 * we will try to use what's supplied.
 *
 * @param string $uri The URI.
 *
 * @return string Hopefully the name of the Follower.
 */
function extract_name_from_uri( $uri ) {
	$name = $uri;

	if ( \filter_var( $name, FILTER_VALIDATE_URL ) ) {
		$name = \rtrim( $name, '/' );
		$path = \wp_parse_url( $name, PHP_URL_PATH );
		if ( $path && '/' !== $path ) {
			if ( \strpos( $name, '@' ) !== false ) {
				// Expected: https://example.com/@user (default URL pattern).
				$name = \preg_replace( '|^/@?|', '', $path );
			} else {
				// Expected: https://example.com/users/user (default ID pattern).
				$parts = \explode( '/', $path );
				$name  = \array_pop( $parts );
			}
		} else {
			$name = \wp_parse_url( $name, PHP_URL_HOST );
			$name = \str_replace( 'www.', '', $name );
		}
	} elseif (
		\is_email( $name ) ||
		\strpos( $name, 'acct' ) === 0 ||
		\strpos( $name, '@' ) === 0
	) {
		// Expected: user@example.com or acct:user@example (WebFinger).
		$name = \ltrim( $name, '@' );
		if ( str_starts_with( $name, 'acct:' ) ) {
			$name = \substr( $name, 5 );
		}
		$parts = \explode( '@', $name );
		$name  = $parts[0];
	}

	return $name;
}
