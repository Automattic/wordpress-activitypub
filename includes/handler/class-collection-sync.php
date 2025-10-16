<?php
/**
 * Collection Sync file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;
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

		// Check if sync-header is part of signature (required by FEP).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$signature = $_SERVER['HTTP_SIGNATURE'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
		if ( false === \stripos( \wp_unslash( $signature ), 'collection-synchronization' ) ) {
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
		if ( preg_match( '#/followers(?:/sync)?(?:\?|$)#', $url ) ) {
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

		$local_actor_urls = self::get_local_actor_urls_for_remote( $actor_url, $our_authority );

		if ( \is_wp_error( $local_actor_urls ) ) {
			return;
		}

		$remote_digest = strtolower( trim( $params['digest'] ) );

		if ( 64 !== strlen( $remote_digest ) || preg_match( '/[^0-9a-f]/', $remote_digest ) ) {
			return;
		}

		$local_digest = self::compute_digest_from_actor_urls( $local_actor_urls );

		if ( \hash_equals( $local_digest, $remote_digest ) ) {
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

		$expected_collection = self::get_followers_collection_id( $actor_url );

		if ( $expected_collection ) {
			if ( self::normalize_collection_url( $params['collectionId'] ) !== self::normalize_collection_url( $expected_collection ) ) {
				return false;
			}
		} else {
			$default_collection = rtrim( $actor_url, '/' ) . '/followers';

			if ( self::normalize_collection_url( $params['collectionId'] ) !== self::normalize_collection_url( $default_collection ) ) {
				return false;
			}
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
	 * Retrieve local actor URLs that follow the remote actor and share the given authority.
	 *
	 * @param string $actor_url The remote actor URL.
	 * @param string $authority The authority to filter by.
	 *
	 * @return array|\WP_Error Array of actor URLs or WP_Error on failure.
	 */
	protected static function get_local_actor_urls_for_remote( $actor_url, $authority ) {
		$snapshot = Following::get_local_followers_snapshot( $actor_url );

		if ( \is_wp_error( $snapshot ) ) {
			return $snapshot;
		}

		$actor_urls = array_keys( $snapshot['followers'] );
		$actor_urls = self::filter_actor_urls_by_authority( $actor_urls, $authority );
		sort( $actor_urls );

		return $actor_urls;
	}

	/**
	 * Filter actor URLs by authority.
	 *
	 * @param array  $actor_urls Array of actor URLs.
	 * @param string $authority  Authority to match.
	 *
	 * @return array Filtered list of actor URLs.
	 */
	protected static function filter_actor_urls_by_authority( array $actor_urls, $authority ) {
		$matched = array();

		foreach ( $actor_urls as $actor_uri ) {
			$actor_authority = Http::get_authority( $actor_uri );

			if ( $actor_authority && $actor_authority === $authority ) {
				$matched[] = $actor_uri;
			}
		}

		return $matched;
	}

	/**
	 * Compute the partial collection digest from a list of actor URLs.
	 *
	 * @param array $actor_urls Actor URLs to include in the digest.
	 *
	 * @return string The computed digest.
	 */
	protected static function compute_digest_from_actor_urls( array $actor_urls ) {
		$digest = str_repeat( '0', 64 );

		foreach ( $actor_urls as $actor_uri ) {
			$digest = Http::xor_hex_strings( $digest, hash( 'sha256', $actor_uri ) );
		}

		return $digest;
	}

	/**
	 * Retrieve the followers collection ID for the remote actor if known.
	 *
	 * @param string $actor_url The remote actor URL.
	 *
	 * @return string|null The followers collection ID or null if unavailable.
	 */
	protected static function get_followers_collection_id( $actor_url ) {
		$post = Remote_Actors::get_by_uri( $actor_url );

		if ( \is_wp_error( $post ) ) {
			$post = Remote_Actors::fetch_by_uri( $actor_url );

			if ( \is_wp_error( $post ) ) {
				return null;
			}
		}

		$actor = Remote_Actors::get_actor( $post );

		if ( \is_wp_error( $actor ) ) {
			return null;
		}

		return $actor->get_followers();
	}

	/**
	 * Normalize a collection URL for comparison.
	 *
	 * @param string $url The URL to normalize.
	 *
	 * @return string Normalized URL without trailing slash.
	 */
	protected static function normalize_collection_url( $url ) {
		if ( ! \is_string( $url ) ) {
			return '';
		}

		return rtrim( $url, '/' );
	}
}
