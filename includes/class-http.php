<?php
/**
 * ActivityPub HTTP Class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;

/**
 * ActivityPub HTTP Class
 *
 * @author Matthias Pfefferle
 */
class Http {
	/**
	 * Send a POST Request with the needed HTTP Headers
	 *
	 * @param string   $url     The URL endpoint.
	 * @param string   $body    The Post Body.
	 * @param int|null $user_id The WordPress User-ID, or null to sign with the Application key.
	 *
	 * @return array|\WP_Error The POST Response or an WP_Error.
	 */
	public static function post( $url, $body, $user_id ) {
		/**
		 * Fires before an HTTP POST request is made.
		 *
		 * @param string   $url     The URL endpoint.
		 * @param string   $body    The POST body.
		 * @param int|null $user_id The WordPress User ID, or null when signing with the Application key.
		 */
		\do_action( 'activitypub_pre_http_post', $url, $body, $user_id );

		/**
		 * Filters the HTTP headers user agent string.
		 *
		 * @param string $user_agent The user agent string.
		 * @param string $url        The request URL.
		 */
		$user_agent = \apply_filters( 'http_headers_useragent', 'WordPress/' . get_masked_wp_version() . '; ' . \get_bloginfo( 'url' ), $url );

		/**
		 * Filters the timeout duration for remote POST requests in ActivityPub.
		 *
		 * @param int $timeout The timeout value in seconds. Default 10 seconds.
		 */
		$timeout = \apply_filters( 'activitypub_remote_post_timeout', 10 );

		/*
		 * Get the private key for signing the request. If a user ID is provided,
		 * get the user's private key; otherwise, use the application's private key.
		 */
		$private_key = null === $user_id ? Application::get_private_key() : Actors::get_private_key( $user_id );

		$args = array(
			'timeout'             => $timeout,
			'limit_response_size' => 1048576,
			'redirection'         => 3,
			'user-agent'          => "$user_agent; ActivityPub",
			'headers'             => array(
				'Accept'       => 'application/activity+json',
				'Content-Type' => 'application/activity+json',
				'Date'         => \gmdate( 'D, d M Y H:i:s T' ),
			),
			'body'                => $body,
			'key_id'              => \json_decode( $body )->actor . '#main-key',
			'private_key'         => $private_key,
			'user_id'             => $user_id,
		);

		$response = \wp_safe_remote_post( $url, $args );
		$code     = \wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$response = new \WP_Error(
				$code,
				\__( 'Failed HTTP Request', 'activitypub' ),
				array(
					'status'   => $code,
					'response' => $response,
				)
			);
		}

		/**
		 * Action to save the response of the remote POST request.
		 *
		 * @param array|\WP_Error $response The response of the remote POST request.
		 * @param string          $url      The URL endpoint.
		 * @param string          $body     The Post Body.
		 * @param int|null        $user_id  The WordPress User-ID, or null when signing with the Application key.
		 */
		\do_action( 'activitypub_safe_remote_post_response', $response, $url, $body, $user_id );

		return $response;
	}

	/**
	 * Send a GET Request with the needed HTTP Headers.
	 *
	 * @param string   $url    The URL endpoint.
	 * @param array    $args   Optional. Additional arguments to customize the request.
	 *                         - 'headers': Array of headers to override defaults.
	 * @param bool|int $cached Optional. Whether to cache the response, or the cache duration in seconds for
	 *                         successful responses. Failed responses use a fixed short backoff duration. Default false.
	 *
	 * @return array|\WP_Error The GET Response or a WP_Error.
	 */
	public static function get( $url, $args = array(), $cached = false ) {
		// Backward compatibility: if $args is boolean/int, it's the old $cached parameter.
		if ( ! \is_array( $args ) ) {
			\_deprecated_argument(
				__METHOD__,
				'7.9.0',
				\esc_html__( 'The $cached parameter should now be passed as the third argument.', 'activitypub' )
			);
			$cached = $args;
			$args   = array();
		}

		/**
		 * Fires before an HTTP GET request is made.
		 *
		 * @param string $url The URL endpoint.
		 */
		\do_action( 'activitypub_pre_http_get', $url );

		$transient_key = self::generate_cache_key( $url );

		// Check cache only if caching is requested.
		if ( $cached ) {
			$response = \get_transient( $transient_key );

			if ( $response ) {
				/**
				 * Action to save the response of the remote GET request.
				 *
				 * @param array|\WP_Error $response The response of the remote GET request.
				 * @param string          $url      The URL endpoint.
				 */
				\do_action( 'activitypub_safe_remote_get_response', $response, $url );

				return $response;
			}
		}

		/**
		 * Filters the HTTP headers user agent string.
		 *
		 * @param string $user_agent The user agent string.
		 * @param string $url        The request URL.
		 */
		$user_agent = \apply_filters( 'http_headers_useragent', 'WordPress/' . get_masked_wp_version() . '; ' . \get_bloginfo( 'url' ), $url );

		/**
		 * Filters the timeout duration for remote GET requests in ActivityPub.
		 *
		 * @param int $timeout The timeout value in seconds. Default 10 seconds.
		 */
		$timeout = \apply_filters( 'activitypub_remote_get_timeout', 10 );

		$defaults = array(
			'timeout'             => $timeout,
			'limit_response_size' => 1048576,
			'redirection'         => 3,
			'user-agent'          => "$user_agent; ActivityPub",
			'headers'             => array(
				'Accept'       => 'application/activity+json',
				'Content-Type' => 'application/activity+json',
				'Date'         => \gmdate( 'D, d M Y H:i:s T' ),
			),
			'key_id'              => Application::get_key_id(),
			'private_key'         => Application::get_private_key(),
		);

		$args            = \wp_parse_args( $args, $defaults );
		$args['headers'] = \wp_parse_args( $args['headers'], $defaults['headers'] );

		$response = \wp_safe_remote_get( $url, $args );
		$code     = \wp_remote_retrieve_response_code( $response );

		if ( \is_wp_error( $response ) || $code >= 400 ) {
			// Capture the served-from URL before $response is replaced by the error below.
			$effective_url = \is_wp_error( $response ) ? '' : self::effective_url( $response );

			if ( ! $code ) {
				$code = 0;
			}
			$response = new \WP_Error( $code, \__( 'Failed HTTP Request', 'activitypub' ), array( 'status' => $code ) );

			/*
			 * Cache errors to prevent repeated timeout waits, but never one reached via a
			 * cross-host redirect: cached under the requested URL's key, a one-off open
			 * redirect on the requested host would let a transient 4xx be replayed as a
			 * repeatable federation outage (the same caching concern as the success path
			 * below, just with a WP_Error instead of a document).
			 *
			 * - Retriable errors (timeouts, 5xx): 1 minute (server may recover quickly).
			 * - Other errors (4xx): 15 minutes (client errors are more permanent).
			 */
			if ( $cached && ( ! $effective_url || is_same_host( $url, $effective_url ) ) ) {
				if ( \in_array( $code, ACTIVITYPUB_RETRY_ERROR_CODES, true ) || 0 === $code ) {
					$cache_duration = MINUTE_IN_SECONDS;
				} else {
					$cache_duration = 15 * MINUTE_IN_SECONDS;
				}

				\set_transient( $transient_key, $response, $cache_duration );
			}

			return $response;
		}

		/**
		 * Action to save the response of the remote GET request.
		 *
		 * @param array|\WP_Error $response The response of the remote GET request.
		 * @param string          $url      The URL endpoint.
		 */
		\do_action( 'activitypub_safe_remote_get_response', $response, $url );

		/*
		 * Never persist a response that was redirected to a different host. The cache
		 * is keyed on the requested URL, so caching cross-origin content under that key
		 * would let a one-off open redirect on the requested host durably associate the
		 * other host's document with that URL — a later lookup (e.g. a public-key fetch)
		 * would then return it. Same-host redirects (e.g. http→https) stay cacheable.
		 */
		$effective_url = self::effective_url( $response );
		if ( $effective_url && ! is_same_host( $url, $effective_url ) ) {
			return $response;
		}

		// Cache successful responses when caching is requested.
		if ( $cached ) {
			$cache_duration = $cached;
			if ( ! \is_int( $cache_duration ) ) {
				$cache_duration = HOUR_IN_SECONDS;
			}
			\set_transient( $transient_key, $response, $cache_duration );
		}

		return $response;
	}

	/**
	 * Check for URL for Tombstone.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool True if the URL is a tombstone.
	 */
	public static function is_tombstone( $url ) {
		\_deprecated_function( __METHOD__, '7.3.0', 'Activitypub\Tombstone::exists_remote' );

		return Tombstone::exists_remote( $url );
	}

	/**
	 * Generate a cache key for the URL.
	 *
	 * @param string $url The URL to generate the cache key for.
	 *
	 * @return string The cache key.
	 */
	public static function generate_cache_key( $url ) {
		return 'activitypub_http_' . \md5( $url );
	}

	/**
	 * Get a remote object.
	 *
	 * Forwards to {@see Proxy::get()}, which owns the cache and the checks that an
	 * object is served under its own id.
	 *
	 * @param array|string $url_or_object The Object or the Object URL.
	 * @param bool|int     $cached        Optional. Whether to use the cache; an int is a cache lifetime in seconds. Default true.
	 *
	 * @return array|\WP_Error The Object data as array or WP_Error on failure.
	 */
	public static function get_remote_object( $url_or_object, $cached = true ) {
		$args = array( 'cached' => (bool) $cached );

		if ( \is_int( $cached ) && $cached > 1 ) {
			$args['ttl'] = $cached;
		}

		return Proxy::get( $url_or_object, $args );
	}


	/**
	 * Extract the effective URL a response was served from, after redirects.
	 *
	 * WordPress follows redirects transparently and exposes the final URL only on
	 * the underlying Requests response object. Returns an empty string when the URL
	 * cannot be determined, so callers fall back to the URL they requested.
	 *
	 * SECURITY: the redirect protections that build on this (the self-confirmation in
	 * get_remote_object() and the cross-host cache skip in get()) fail OPEN when this
	 * returns an empty string — self-confirmation then compares against the requested
	 * URL, which a redirect could have bounced away from. This relies on the internal
	 * `http_response` → Requests response `url` shape; if a future WordPress release
	 * changes it, re-verify that this still returns the post-redirect URL.
	 *
	 * @param array $response A `wp_remote_get()` response array.
	 *
	 * @return string The final URL, or an empty string when unavailable.
	 */
	public static function effective_url( $response ) {
		if ( empty( $response['http_response'] ) || ! \is_object( $response['http_response'] ) ) {
			return '';
		}

		$requests_response = $response['http_response']->get_response_object();

		if ( ! \is_object( $requests_response ) || empty( $requests_response->url ) ) {
			return '';
		}

		return (string) $requests_response->url;
	}
}
