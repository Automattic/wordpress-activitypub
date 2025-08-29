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
 *
 * Handles detection and management of tombstoned (deleted) ActivityPub resources.
 * A tombstone in ActivityPub represents a deleted object that was previously available.
 * This class provides methods to detect tombstones across various data formats including
 * URLs, ActivityPub objects, arrays, and WordPress error responses.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-tombstone
 */
class Tombstone {
	/**
	 * HTTP status codes that indicate a tombstoned resource.
	 *
	 * - 404: Not Found - Resource no longer exists
	 * - 410: Gone - Resource was intentionally removed
	 *
	 * @var int[] Array of HTTP status codes indicating tombstones.
	 */
	private static $codes = array( 404, 410 );

	/**
	 * Check if a tombstone exists for the given resource.
	 *
	 * This is the main entry point for tombstone detection. It accepts various
	 * data types and routes them to the appropriate checking method:
	 * - URLs (string): Checks remote or local tombstone status
	 * - WP_Error objects: Checks for tombstone-indicating HTTP status codes
	 * - Arrays: Checks for ActivityPub Tombstone type
	 * - Objects: Checks for ActivityPub Tombstone type or Base_Object instances
	 *
	 * @param string|\WP_Error|array|object $various The resource data to check for tombstone status.
	 *                                               Can be a URL, error object, ActivityPub array, or object.
	 *
	 * @return bool True if the resource is tombstoned, false otherwise.
	 */
	public static function exists( $various ) {
		if ( \is_wp_error( $various ) ) {
			return self::exists_in_error( $various );
		}

		if ( \is_string( $various ) ) {
			if ( is_same_domain( $various ) ) {
				return self::exists_local( $various );
			}
			return self::exists_remote( $various );
		}

		if ( \is_array( $various ) ) {
			return self::check_array( $various );
		}

		if ( \is_object( $various ) ) {
			return self::check_object( $various );
		}

		return false;
	}

	/**
	 * Check if a remote URL is tombstoned.
	 *
	 * Makes an HTTP request to the remote URL with ActivityPub headers
	 * and checks for tombstone indicators:
	 * - HTTP 404/410 status codes
	 * - ActivityPub Tombstone object type in response body
	 *
	 * @param string $url The remote URL to check for tombstone status.
	 *
	 * @return bool True if the remote URL is tombstoned, false otherwise.
	 */
	public static function exists_remote( $url ) {
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

		return self::check_array( $data );
	}

	/**
	 * Check if a local URL is tombstoned.
	 *
	 * Checks against the local tombstone URL registry stored in WordPress options.
	 * Local URLs are normalized before comparison to ensure consistent matching.
	 *
	 * @param string $url The local URL to check for tombstone status.
	 *
	 * @return bool True if the local URL is in the tombstone registry, false otherwise.
	 */
	public static function exists_local( $url ) {
		$urls = get_option( 'activitypub_tombstone_urls', array() );

		return in_array( normalize_url( $url ), $urls, true );
	}

	/**
	 * Check if a WP_Error object indicates a tombstoned resource.
	 *
	 * Examines the error data for HTTP status codes that indicate tombstones.
	 * This is typically used when HTTP requests return error responses.
	 *
	 * @param \WP_Error $wp_error The WordPress error object to examine.
	 *
	 * @return bool True if the error indicates a tombstoned resource, false otherwise.
	 */
	public static function exists_in_error( $wp_error ) {
		if ( ! \is_wp_error( $wp_error ) ) {
			return false;
		}

		$data = $wp_error->get_error_data();
		if ( isset( $data['status'] ) && in_array( (int) $data['status'], self::$codes, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if an array represents an ActivityPub Tombstone object.
	 *
	 * Examines the array for the ActivityPub 'type' property set to 'Tombstone'.
	 * This follows the ActivityStreams specification for tombstone objects.
	 *
	 * @param array|mixed $data The array data to check. Non-arrays return false.
	 *
	 * @return bool True if the array represents a Tombstone object, false otherwise.
	 */
	private static function check_array( $data ) {
		if ( ! \is_array( $data ) ) {
			return false;
		}

		if ( isset( $data['type'] ) && 'Tombstone' === $data['type'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if an object represents an ActivityPub Tombstone.
	 *
	 * Checks for tombstone indicators in objects:
	 * - Standard objects: 'type' property set to 'Tombstone'
	 * - Base_Object instances: Uses get_type() method to check for 'Tombstone'
	 *
	 * @param object|mixed $data The object data to check. Non-objects return false.
	 *
	 * @return bool True if the object represents a Tombstone, false otherwise.
	 */
	private static function check_object( $data ) {
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
	 * Add a URL to the local tombstone registry.
	 *
	 * "Buries" a URL by adding it to the local tombstone URL registry.
	 * The URL is normalized before storage and duplicates are automatically removed.
	 * This marks the URL as tombstoned for future local checks.
	 *
	 * @param string $url The URL to add to the tombstone registry.
	 *
	 * @return void
	 */
	public static function bury( $url ) {
		$urls   = \get_option( 'activitypub_tombstone_urls', array() );
		$urls[] = normalize_url( $url );
		$urls   = \array_unique( $urls );

		\update_option( 'activitypub_tombstone_urls', $urls );
	}

	/**
	 * Remove a URL from the local tombstone registry.
	 *
	 * Removes a URL from the local tombstone URL registry.
	 * The URL is normalized before comparison to ensure consistent matching.
	 * This marks the URL as no longer tombstoned for future local checks.
	 *
	 * @param string $url The URL to remove from the tombstone registry.
	 */
	public static function remove( $url ) {
		$urls = \get_option( 'activitypub_tombstone_urls', array() );
		$urls = \array_diff( $urls, array( normalize_url( $url ) ) );
		\update_option( 'activitypub_tombstone_urls', $urls );
	}
}
