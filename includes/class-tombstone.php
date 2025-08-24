<?php
/**
 * Tombstone class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Base_Object;

/**
 * ActivityPub Tombstone Class.
 */
class Tombstone {
	/**
	 * HTTP codes that indicate a tombstone.
	 *
	 * @var array
	 */
	private static $codes = array( 404, 410 );

	/**
	 * Check for Tombstone.
	 *
	 * @param string|\WP_Error|array|object $various The various data to check.
	 *
	 * @return bool True if the various data is a tombstone.
	 */
	public static function is_gone( $various ) {
		if ( \is_wp_error( $various ) ) {
			return self::is_wp_error( $various );
		}

		if ( \is_string( $various ) ) {
			if ( is_same_domain( $various ) ) {
				return self::is_local_url_gone( $various );
			}
			return self::is_remote_url_gone( $various );
		}

		if ( \is_array( $various ) ) {
			return self::is_array_gone( $various );
		}

		if ( \is_object( $various ) ) {
			return self::is_object_gone( $various );
		}

		return false;
	}

	/**
	 * Check for remote URL for Tombstone.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool True if the URL is a tombstone.
	 */
	public static function is_remote_url_gone( $url ) {
		/**
		 * Fires before checking if the URL is a tombstone.
		 *
		 * @param string $url The URL to check.
		 */
		\do_action( 'activitypub_pre_http_is_tombstone', $url );

		$response = \wp_safe_remote_get( $url, array( 'headers' => array( 'Accept' => 'application/activity+json' ) ) );
		$code     = \wp_remote_retrieve_response_code( $response );

		if ( in_array( (int) $code, self::$codes, true ) ) {
			return true;
		}

		$data = \wp_remote_retrieve_body( $response );
		$data = \json_decode( $data, true );

		return self::is_array_gone( $data );
	}

	/**
	 * Check for local URL for Tombstone.
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool True if the URL is a tombstone.
	 */
	public static function is_local_url_gone( $url ) {
		$urls = get_option( 'activitypub_tombstone_urls', array() );

		return in_array( normalize_url( $url ), $urls, true );
	}

	/**
	 * Check if the response is a WP_Error.
	 *
	 * @param \WP_Error $wp_error The response to check.
	 *
	 * @return bool True if the response is a WP_Error, false otherwise.
	 */
	public static function is_wp_error( $wp_error ) {
		if ( ! \is_wp_error( $wp_error ) ) {
			return false;
		}

		if ( in_array( (int) $wp_error->get_error_code(), self::$codes, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the given array represents a tombstone.
	 *
	 * @param array $data The array to check.
	 *
	 * @return bool True if the array represents a tombstone, false otherwise.
	 */
	public static function is_array_gone( $data ) {
		if ( ! \is_array( $data ) ) {
			return false;
		}

		if ( isset( $data['type'] ) && 'Tombstone' === $data['type'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the given object represents a tombstone.
	 *
	 * @param object $data The object to check.
	 *
	 * @return bool True if the object represents a tombstone, false otherwise.
	 */
	public static function is_object_gone( $data ) {
		if ( ! \is_object( $data ) ) {
			return false;
		}

		if ( isset( $data->type ) && 'Tombstone' === $data->type ) {
			return true;
		}

		if ( $data instanceof Base_Object && 'Tombstone' === $data->get_type() ) {
			return true;
		}

		return false;
	}

	/**
	 * Bury a URL.
	 *
	 * @param string $url The URL to bury.
	 */
	public static function bury( $url ) {
		$urls   = \get_option( 'activitypub_tombstone_urls', array() );
		$urls[] = normalize_url( $url );
		$urls   = \array_unique( $urls );

		\update_option( 'activitypub_tombstone_urls', $urls );
	}
}
