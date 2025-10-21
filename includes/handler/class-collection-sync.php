<?php
/**
 * Collection Sync file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Signature;

use function Activitypub\get_url_authority;

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

		// The Collection-Synchronization header needs to be part of the signature, so it must be added before signing.
		\add_filter( 'http_request_args', array( self::class, 'maybe_add_headers' ), -1, 2 );
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

		// Check if sync-header is part of signature (required by FEP).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$signature = \wp_unslash( $_SERVER['HTTP_SIGNATURE'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '' );
		if ( false === \stripos( $signature, 'collection-synchronization' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$sync_header = \wp_unslash( $_SERVER['HTTP_COLLECTION_SYNCHRONIZATION'] );

		// Parse the header using the generic HTTP parser.
		$params = Signature::parse_collection_sync_header( $sync_header );

		if ( false === $params ) {
			return;
		}

		// Check for followers collection.
		$collection_type = null;
		if ( preg_match( '#/followers(?:/sync)?(?:\?|$)#', $params['url'] ) ) {
			$collection_type = 'followers';
		}

		if ( ! $collection_type ) {
			// Unknown or unsupported collection type.
			return;
		}

		// Get the actor URL for validation.
		$actor_url = $data['actor'] ?? false;

		if ( ! $actor_url ) {
			return;
		}

		// Validate the header parameters.
		if ( ! self::validate_header_params( $params, $actor_url ) ) {
			return;
		}

		$cache_key = 'activitypub_collection_sync_received_' . $user_id . '_' . md5( $actor_url );
		if ( false === \get_transient( $cache_key ) ) {
			$frequency = self::get_frequency();
			\set_transient( $cache_key, time(), $frequency );
		} else {
			return;
		}

		/**
		 * Action triggered Collection Sync.
		 *
		 * This allows for async processing of the reconciliation.
		 *
		 * @param string $collection_type The collection type (e.g., 'followers', 'following', 'liked').
		 * @param int    $user_id         The local user ID.
		 * @param string $actor_url       The remote actor URL.
		 * @param array  $params          The parsed Collection-Synchronization header parameters.
		 */
		\do_action( 'activitypub_collection_sync', $collection_type, $user_id, $actor_url, $params );
	}

	/**
	 * Add Collection-Synchronization header to `Create` activities (FEP-8fcf).
	 *
	 * This method adds the Collection-Synchronization header to outgoing `Create` activities.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md
	 *
	 * @param array  $args The HTTP request arguments.
	 * @param string $url  The request URL.
	 *
	 * @return array Modified HTTP request arguments.
	 */
	public static function maybe_add_headers( $args, $url ) {
		if ( empty( $args['body'] ) ) {
			return $args;
		}

		if ( ! is_array( $args['body'] ) ) {
			$body = \json_decode( $args['body'], true );
			if ( null === $body ) {
				return $args;
			}
		} else {
			$body = $args['body'];
		}

		if ( ! isset( $body['type'] ) || 'Create' !== $body['type'] ) {
			return $args;
		}

		// Only send header if we haven't sent one to this authority in the last day.
		$inbox_authority = get_url_authority( $url );
		$user_id         = $args['user_id'] ?? false;

		if ( false === $user_id || ! $inbox_authority ) {
			return $args;
		}

		// Check if we've already sent a sync header to this authority today.
		$transient_key = 'activitypub_collection_sync_sent_' . $user_id . '_' . md5( $inbox_authority );
		if ( false !== \get_transient( $transient_key ) ) {
			return $args;
		}

		$sync_header = Followers::generate_sync_header( $user_id, $inbox_authority );
		if ( $sync_header ) {
			$args['headers']['Collection-Synchronization'] = $sync_header;

			$frequency = self::get_frequency();
			\set_transient( $transient_key, time(), $frequency );
		}

		return $args;
	}

	/**
	 * Validate Collection-Synchronization header parameters.
	 *
	 * @param array  $params    Parsed header parameters.
	 * @param string $actor_url The actor URL that sent the activity.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public static function validate_header_params( $params, $actor_url ) {
		if ( empty( $params['collectionId'] ) || empty( $params['url'] ) ) {
			return false;
		}

		$post = Remote_Actors::fetch_by_uri( $actor_url );

		if ( \is_wp_error( $post ) ) {
			return false;
		}

		$actor = Remote_Actors::get_actor( $post );

		if ( \is_wp_error( $actor ) ) {
			return false;
		}

		$expected_collection = $actor->get_followers();

		if ( \is_wp_error( $expected_collection ) ) {
			return false;
		}

		if ( trailingslashit( $params['collectionId'] ) !== trailingslashit( $expected_collection ) ) {
			return false;
		}

		// Build authorities for comparison.
		$collection_authority = get_url_authority( $params['collectionId'] );
		$url_authority        = get_url_authority( $params['url'] );

		return $collection_authority === $url_authority;
	}

	/**
	 * Get the frequency for Collection-Synchronization headers.
	 *
	 * @return int Frequency in seconds.
	 */
	private static function get_frequency() {
		/**
		 * Filter the frequency of Collection-Synchronization headers sent to a given authority.
		 *
		 * @param int    $frequency       The frequency in seconds. Default is one week.
		 * @param int    $user_id         The local user ID.
		 * @param string $inbox_authority The inbox authority.
		 */
		return \apply_filters( 'activitypub_collection_sync_frequency', WEEK_IN_SECONDS );
	}
}
