<?php
/**
 * Request functions.
 *
 * Functions for HTTP requests and remote communication.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Remote_Actors;

/**
 * Send a POST request to a remote server.
 *
 * @param string $url     The URL endpoint.
 * @param string $body    The Post Body.
 * @param int    $user_id The WordPress user ID.
 *
 * @return array|\WP_Error The POST Response or an WP_Error.
 */
function safe_remote_post( $url, $body, $user_id ) {
	return Http::post( $url, $body, $user_id );
}

/**
 * Send a GET request to a remote server.
 *
 * @param string $url The URL endpoint.
 *
 * @return array|\WP_Error The GET Response or an WP_Error.
 */
function safe_remote_get( $url ) {
	return Http::get( $url );
}

/**
 * Check if Authorized-Fetch is enabled.
 *
 * @see https://docs.joinmastodon.org/admin/config/#authorized_fetch
 *
 * @return boolean True if Authorized-Fetch is enabled, false otherwise.
 */
function use_authorized_fetch() {
	$use = (bool) \get_option( 'activitypub_authorized_fetch' );

	/**
	 * Filters whether to use Authorized-Fetch.
	 *
	 * @param boolean $use_authorized_fetch True if Authorized-Fetch is enabled, false otherwise.
	 */
	return apply_filters( 'activitypub_use_authorized_fetch', $use );
}

/**
 * Check for Tombstone Objects.
 *
 * @deprecated 7.3.0 Use {@see Tombstone::exists_in_error()}.
 * @see https://www.w3.org/TR/activitypub/#delete-activity-outbox
 *
 * @param \WP_Error $wp_error A WP_Error-Response of an HTTP-Request.
 *
 * @return boolean True if HTTP-Code is 410 or 404.
 */
function is_tombstone( $wp_error ) {
	\_deprecated_function( __FUNCTION__, '7.3.0', 'Activitypub\Tombstone::exists_in_error' );

	return Tombstone::exists_in_error( $wp_error );
}

/**
 * Check if a request is for an ActivityPub request.
 *
 * @return bool False by default.
 */
function is_activitypub_request() {
	return Query::get_instance()->is_activitypub_request();
}

/**
 * Check if content negotiation is allowed for a request.
 *
 * @return bool True if content negotiation is allowed, false otherwise.
 */
function should_negotiate_content() {
	return Query::get_instance()->should_negotiate_content();
}

/**
 * Requests the Meta-Data from the Actors profile.
 *
 * @param array|string $actor  The Actor array or URL.
 * @param bool         $cached Optional. Whether the result should be cached. Default true.
 *
 * @return array|\WP_Error The Actor profile as array or WP_Error on failure.
 */
function get_remote_metadata_by_actor( $actor, $cached = true ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	/**
	 * Filters the metadata before it is retrieved from a remote actor.
	 *
	 * Passing a non-false value will effectively short-circuit the remote request,
	 * returning that value instead.
	 *
	 * @param mixed  $pre   The value to return instead of the remote metadata.
	 *                      Default false to continue with the remote request.
	 * @param string $actor The actor URL.
	 */
	$pre = apply_filters( 'pre_get_remote_metadata_by_actor', false, $actor );
	if ( $pre ) {
		return $pre;
	}

	$remote_actor = Remote_Actors::fetch_by_various( $actor );

	if ( is_wp_error( $remote_actor ) ) {
		return $remote_actor;
	}

	return json_decode( $remote_actor->post_content, true );
}

/**
 * Resolve a hostname or IP literal to a public IP address.
 *
 * Used as an SSRF guard before opening connections to user-supplied URLs.
 * `wp_safe_remote_get()` ultimately calls `wp_http_validate_url()`, which has
 * a same-host carve-out that lets local/private addresses through when the
 * WordPress site itself is hosted on one. This helper performs an explicit
 * resolve-and-validate without that carve-out, and returns the resolved IP so
 * callers can pin the connection to it (defends against DNS rebinding).
 *
 * Both IPv4 and IPv6 literals are accepted (bracketed IPv6 like `[::1]` is
 * normalised first). Hostname resolution uses `gethostbynamel()`, which is
 * IPv4-only, mirroring the default behaviour of `wp_http_validate_url`. If any
 * returned address is private or reserved the helper rejects, defending
 * against split-horizon DNS that returns a public answer to one resolver and a
 * private one to another.
 *
 * @param string $host The hostname or IP literal to resolve.
 *
 * @return string|false A safe public IP, or false when no safe address is available.
 */
function resolve_public_host( $host ) {
	if ( ! is_string( $host ) || '' === $host ) {
		return false;
	}

	// Normalise bracketed IPv6 literals (parse_url returns "[::1]").
	$host = \trim( $host, '[]' );

	// Already an IP literal — validate directly. Accepts IPv4 and IPv6.
	if ( \filter_var( $host, FILTER_VALIDATE_IP ) ) {
		/*
		 * Reject IPv4-mapped IPv6 literals (`::ffff:0:0/96`). PHP's
		 * FILTER_FLAG_NO_RES_RANGE catches them on some builds but not
		 * others, so let the SSRF guard not depend on that. These forms
		 * serve no legitimate purpose for our callers anyway.
		 */
		$packed = \inet_pton( $host );
		if ( false !== $packed && 16 === \strlen( $packed ) && "\0\0\0\0\0\0\0\0\0\0\xff\xff" === \substr( $packed, 0, 12 ) ) {
			return false;
		}

		return \filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE )
			? $host
			: false;
	}

	$ips = \gethostbynamel( $host );
	if ( ! $ips ) {
		return false;
	}

	// Reject if any resolved address is private/reserved.
	foreach ( $ips as $ip ) {
		if ( ! \filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}
	}

	return $ips[0];
}
