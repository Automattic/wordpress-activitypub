<?php
/**
 * Collection Sync file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Followers;
use Activitypub\Http;

/**
 * Collection Sync Handler.
 *
 * Handles the Collection-Synchronization header (FEP-8fcf) for various collection types.
 *
 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md
 */
class Collection_Sync {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_create', array( self::class, 'handle_collection_synchronization' ), 10, 2 );
	}

	/**
	 * Process Collection-Synchronization header if present (FEP-8fcf).
	 *
	 * This method handles the FEP-8fcf Collection Synchronization protocol for any collection type.
	 * It detects the collection type from the URL and delegates to the appropriate handler.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md
	 *
	 * @param array $data    The activity data.
	 * @param int   $user_id The local user ID.
	 */
	public static function handle_collection_synchronization( $data, $user_id ) {
		if ( empty( $_SERVER['HTTP_COLLECTION_SYNCHRONIZATION'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$sync_header = \wp_unslash( $_SERVER['HTTP_COLLECTION_SYNCHRONIZATION'] );

		// Parse the header using the generic HTTP parser.
		$params = Http::parse_collection_sync_header( $sync_header );

		if ( false === $params ) {
			return;
		}

		// Ensure we have a URL parameter to determine collection type.
		if ( ! isset( $params['url'] ) ) {
			return;
		}

		// Determine the collection type from the URL.
		$collection_type = self::detect_collection_type( $params['url'] );

		if ( ! $collection_type ) {
			// Unknown or unsupported collection type.
			return;
		}

		// Get the actor URL for validation.
		$actor_url = isset( $data['actor'] ) ? $data['actor'] : null;

		if ( ! $actor_url ) {
			return;
		}

		switch ( $collection_type ) {
			case 'followers':
				self::process_followers_collection_sync( $params, $user_id, $actor_url );
				break;
			default:
				break;
		}
	}

	/**
	 * Detect the collection type from a URL.
	 *
	 * @param string $url The collection URL.
	 * @return string|false The collection type (e.g., 'followers', 'following', 'liked') or false if unknown.
	 */
	protected static function detect_collection_type( $url ) {
		// Check for followers collection.
		if ( preg_match( '#/followers(?:-sync)?(?:\?|$)#', $url ) ) {
			return 'followers';
		}

		/**
		 * Filters the collection type detection.
		 *
		 * Allows plugins to register custom collection types for synchronization.
		 *
		 * @param string|false $type The detected collection type, or false if unknown.
		 * @param string       $url  The collection URL.
		 */
		return \apply_filters( 'activitypub_detect_collection_type', false, $url );
	}

	/**
	 * Process followers collection synchronization.
	 *
	 * @param array  $params    The parsed Collection-Synchronization header parameters.
	 * @param int    $user_id   The local user ID.
	 * @param string $actor_url The remote actor URL.
	 */
	protected static function process_followers_collection_sync( $params, $user_id, $actor_url ) {
		// Validate the header parameters.
		if ( ! self::validate_collection_sync_header_params( $params, $actor_url ) ) {
			return;
		}

		// Get our local authority.
		$our_authority = Http::get_authority( \home_url() );

		if ( ! $our_authority ) {
			return;
		}

		// Compute our local digest for this actor's followers from our instance.
		$local_digest = Followers::compute_partial_digest( $user_id, $our_authority );

		// Compare digests.
		if ( $local_digest === $params['digest'] ) {
			// Digests match, no synchronization needed.
			return;
		}

		// Digests do not match, trigger reconciliation.

		/**
		 * Action triggered when Collection-Synchronization digest mismatch is detected for followers.
		 *
		 * This allows for async processing of the reconciliation.
		 *
		 * @param int    $user_id   The local user ID.
		 * @param string $actor_url The remote actor URL.
		 * @param array  $params    The parsed Collection-Synchronization header parameters.
		 */
		\do_action( 'activitypub_followers_sync_mismatch', $user_id, $actor_url, $params );
	}

	/**
	 * Validate Collection-Synchronization header parameters.
	 *
	 * @param array  $params    Parsed header parameters.
	 * @param string $actor_url The actor URL that sent the activity.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function validate_collection_sync_header_params( $params, $actor_url ) {
		if ( empty( $params['collectionId'] ) || empty( $params['url'] ) ) {
			return false;
		}

		// Parse the actor URL to get the expected followers collection.
		$expected_collection = $actor_url . '/followers';

		// Check if collectionId matches the actor's followers collection.
		if ( $params['collectionId'] !== $expected_collection ) {
			return false;
		}

		// Check if url has the same authority as collectionId (prevent SSRF).
		$collection_parsed = wp_parse_url( $params['collectionId'] );
		$url_parsed        = wp_parse_url( $params['url'] );

		if ( ! $collection_parsed || ! $url_parsed ) {
			return false;
		}

		// Build authorities for comparison.
		$collection_authority = $collection_parsed['scheme'] . '://' . $collection_parsed['host'];
		$url_authority        = $url_parsed['scheme'] . '://' . $url_parsed['host'];

		if ( ! empty( $collection_parsed['port'] ) ) {
			$collection_authority .= ':' . $collection_parsed['port'];
		}

		if ( ! empty( $url_parsed['port'] ) ) {
			$url_authority .= ':' . $url_parsed['port'];
		}

		return $collection_authority === $url_authority;
	}

	/**
	 * Get the authority (scheme + host + port) from a URL.
	 *
	 * @param string $url The URL to parse.
	 *
	 * @return string|false The authority, or false on failure.
	 */
	public static function get_authority( $url ) {
		$parsed = wp_parse_url( $url );

		if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return false;
		}

		$authority = $parsed['scheme'] . '://' . $parsed['host'];

		if ( ! empty( $parsed['port'] ) ) {
			$default_ports = array(
				'http'  => 80,
				'https' => 443,
			);
			if ( ! isset( $default_ports[ $parsed['scheme'] ] ) || $default_ports[ $parsed['scheme'] ] !== $parsed['port'] ) {
				$authority .= ':' . $parsed['port'];
			}
		}

		return $authority;
	}
}
