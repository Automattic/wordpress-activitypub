<?php
namespace Activitypub;

use WP_Error;
use Activitypub\Collection\Users;

use function Activitypub\get_masked_wp_version;

/**
 * ActivityPub HTTP Class
 *
 * @author Matthias Pfefferle
 */
class Http {
	/**
	 * Send a POST Request with the needed HTTP Headers
	 *
	 * @param string $url     The URL endpoint
	 * @param string $body    The Post Body
	 * @param int    $user_id The WordPress User-ID
	 *
	 * @return array|WP_Error The POST Response or an WP_ERROR
	 */
	public static function post( $url, $body, $user_id ) {
		\do_action( 'activitypub_pre_http_post', $url, $body, $user_id );

		$date = \gmdate( 'D, d M Y H:i:s T' );
		$digest = Signature::generate_digest( $body );
		$signature = Signature::generate_signature( $user_id, 'post', $url, $date, $digest );

		$wp_version = get_masked_wp_version();

		/**
		 * Filter the HTTP headers user agent.
		 *
		 * @param string $user_agent The user agent string.
		 */
		$user_agent = \apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . \get_bloginfo( 'url' ) );
		$args = array(
			'timeout' => 100,
			'limit_response_size' => 1048576,
			'redirection' => 3,
			'user-agent' => "$user_agent; ActivityPub",
			'headers' => array(
				'Accept' => 'application/activity+json',
				'Content-Type' => 'application/activity+json',
				'Digest' => $digest,
				'Signature' => $signature,
				'Date' => $date,
			),
			'body' => $body,
		);

		$response = \wp_safe_remote_post( $url, $args );
		$code     = \wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$response = new WP_Error( $code, __( 'Failed HTTP Request', 'activitypub' ), array( 'status' => $code ) );
		}

		\do_action( 'activitypub_safe_remote_post_response', $response, $url, $body, $user_id );

		return $response;
	}

	/**
	 * Send a GET Request with the needed HTTP Headers
	 *
	 * @param string $url     The URL endpoint
	 * @param bool|int   $cached If the result should be cached, or its duration. Default: 1hr.
	 *
	 * @return array|WP_Error The GET Response or an WP_ERROR
	 */
	public static function get( $url, $cached = false ) {
		\do_action( 'activitypub_pre_http_get', $url );

		if ( $cached ) {
			$transient_key = self::generate_cache_key( $url );

			$response = \get_transient( $transient_key );

			if ( $response ) {
				\do_action( 'activitypub_safe_remote_get_response', $response, $url );

				return $response;
			}
		}

		$date = \gmdate( 'D, d M Y H:i:s T' );
		$signature = Signature::generate_signature( Users::APPLICATION_USER_ID, 'get', $url, $date );

		$wp_version = get_masked_wp_version();

		/**
		 * Filter the HTTP headers user agent.
		 *
		 * @param string $user_agent The user agent string.
		 */
		$user_agent = \apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . \get_bloginfo( 'url' ) );

		$args = array(
			'timeout' => apply_filters( 'activitypub_remote_get_timeout', 100 ),
			'limit_response_size' => 1048576,
			'redirection' => 3,
			'user-agent' => "$user_agent; ActivityPub",
			'headers' => array(
				'Accept' => 'application/activity+json',
				'Content-Type' => 'application/activity+json',
				'Signature' => $signature,
				'Date' => $date,
			),
		);

		$response = \wp_safe_remote_get( $url, $args );
		$code     = \wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			$response = new WP_Error( $code, __( 'Failed HTTP Request', 'activitypub' ), array( 'status' => $code ) );
		}

		\do_action( 'activitypub_safe_remote_get_response', $response, $url );

		if ( $cached ) {
			$cache_duration = $cached;
			if ( ! is_int( $cache_duration ) ) {
				$cached = HOUR_IN_SECONDS;
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
		\do_action( 'activitypub_pre_http_is_tombstone', $url );

		$response = \wp_safe_remote_get( $url );
		$code     = \wp_remote_retrieve_response_code( $response );

		if ( in_array( (int) $code, array( 404, 410 ), true ) ) {
			return true;
		}

		return false;
	}

	public static function generate_cache_key( $url ) {
		return 'activitypub_http_' . \md5( $url );
	}
}
