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
	 * @param string $url     The URL endpoint.
	 * @param string $body    The Post Body.
	 * @param int    $user_id The WordPress User-ID.
	 *
	 * @return array|\WP_Error The POST Response or an WP_Error.
	 */
	public static function post( $url, $body, $user_id ) {
		/**
		 * Fires before an HTTP POST request is made.
		 *
		 * @param string $url     The URL endpoint.
		 * @param string $body    The POST body.
		 * @param int    $user_id The WordPress User ID.
		 */
		\do_action( 'activitypub_pre_http_post', $url, $body, $user_id );

		/**
		 * Filters the HTTP headers user agent string.
		 *
		 * @param string $user_agent The user agent string.
		 */
		$user_agent = \apply_filters( 'http_headers_useragent', 'WordPress/' . get_masked_wp_version() . '; ' . \get_bloginfo( 'url' ) );
		$args       = array(
			'timeout'             => 100,
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
			'private_key'         => Actors::get_private_key( $user_id ),
		);

		$response = \wp_safe_remote_post( $url, $args );
		$code     = \wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$response = new \WP_Error(
				$code,
				__( 'Failed HTTP Request', 'activitypub' ),
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
		 * @param int             $user_id  The WordPress User-ID.
		 */
		\do_action( 'activitypub_safe_remote_post_response', $response, $url, $body, $user_id );

		return $response;
	}

	/**
	 * Send a GET Request with the needed HTTP Headers.
	 *
	 * @param string   $url    The URL endpoint.
	 * @param bool|int $cached Optional. Whether the result should be cached, or its duration. Default false.
	 *
	 * @return array|\WP_Error The GET Response or a WP_Error.
	 */
	public static function get( $url, $cached = false ) {
		/**
		 * Fires before an HTTP GET request is made.
		 *
		 * @param string $url The URL endpoint.
		 */
		\do_action( 'activitypub_pre_http_get', $url );

		if ( $cached ) {
			$transient_key = self::generate_cache_key( $url );

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
		 * This filter allows developers to modify the user agent string that is
		 * sent with HTTP requests.
		 *
		 * @param string $user_agent The user agent string.
		 */
		$user_agent = \apply_filters( 'http_headers_useragent', 'WordPress/' . get_masked_wp_version() . '; ' . \get_bloginfo( 'url' ) );

		/**
		 * Filters the timeout duration for remote GET requests in ActivityPub.
		 *
		 * @param int $timeout The timeout value in seconds. Default 100 seconds.
		 */
		$timeout = \apply_filters( 'activitypub_remote_get_timeout', 100 );

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
			'key_id'              => Actors::get_by_id( Actors::APPLICATION_USER_ID )->get_id() . '#main-key',
			'private_key'         => Actors::get_private_key( Actors::APPLICATION_USER_ID ),
		);

		$response = \wp_safe_remote_get( $url, $args );
		$code     = \wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$response = new \WP_Error( $code, __( 'Failed HTTP Request', 'activitypub' ), array( 'status' => $code ) );
		}

		/**
		 * Action to save the response of the remote GET request.
		 *
		 * @param array|\WP_Error $response The response of the remote GET request.
		 * @param string          $url      The URL endpoint.
		 */
		\do_action( 'activitypub_safe_remote_get_response', $response, $url );

		if ( $cached ) {
			$cache_duration = $cached;
			if ( ! is_int( $cache_duration ) ) {
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
		_deprecated_function( __METHOD__, '7.3.0', 'Activitypub\Tombstone::exists_remote' );

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
	 * Requests the Data from the Object-URL or Object-Array.
	 *
	 * @param array|string $url_or_object The Object or the Object URL.
	 * @param bool         $cached        Optional. Whether the result should be cached. Default true.
	 *
	 * @return array|\WP_Error The Object data as array or WP_Error on failure.
	 */
	public static function get_remote_object( $url_or_object, $cached = true ) {
		/**
		 * Filters the preemptive return value of a remote object request.
		 *
		 * @param array|string|null $response      The response.
		 * @param array|string|null $url_or_object The Object or the Object URL.
		 */
		$response = apply_filters( 'activitypub_pre_http_get_remote_object', null, $url_or_object );
		if ( null !== $response ) {
			return $response;
		}

		$url = object_to_uri( $url_or_object );

		if ( preg_match( '/^@?' . ACTIVITYPUB_USERNAME_REGEXP . '$/i', $url ) ) {
			$url = Webfinger::resolve( $url );
		}

		if ( ! $url ) {
			return new \WP_Error(
				'activitypub_no_valid_actor_identifier',
				\__( 'The "actor" identifier is not valid', 'activitypub' ),
				array(
					'status' => 404,
					'object' => $url,
				)
			);
		}

		if ( is_wp_error( $url ) ) {
			return $url;
		}

		$transient_key = self::generate_cache_key( $url );

		// Only check the cache if needed.
		if ( $cached ) {
			$data = \get_transient( $transient_key );

			if ( $data ) {
				return $data;
			}
		}

		if ( ! \wp_http_validate_url( $url ) ) {
			return new \WP_Error(
				'activitypub_no_valid_object_url',
				\__( 'The "object" is/has no valid URL', 'activitypub' ),
				array(
					'status' => 400,
					'object' => $url,
				)
			);
		}

		$response = self::get( $url );

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$data = \wp_remote_retrieve_body( $response );
		$data = \json_decode( $data, true );

		if ( ! $data ) {
			return new \WP_Error(
				'activitypub_invalid_json',
				\__( 'No valid JSON data', 'activitypub' ),
				array(
					'status' => 400,
					'object' => $url,
				)
			);
		}

		\set_transient( $transient_key, $data, WEEK_IN_SECONDS );

		return $data;
	}
}
