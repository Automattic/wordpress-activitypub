<?php
/**
 * Avatar cache class.
 *
 * @package Activitypub
 */

namespace Activitypub\Cache;

use Activitypub\Collection\Remote_Actors;

/**
 * Avatar cache class.
 *
 * Handles caching of remote actor avatars locally.
 * Avatars are stored in /wp-content/uploads/activitypub/actors/{actor_id}/
 * and cleaned up automatically when the actor is deleted.
 *
 * @since 5.6.0
 */
class Avatar extends File {
	/**
	 * Maximum dimension for avatars in pixels.
	 *
	 * @var int
	 */
	const MAX_DIMENSION = 512;

	/**
	 * Context identifier for the filter.
	 *
	 * @var string
	 */
	const CONTEXT = 'avatar';

	/**
	 * Get the cache type identifier.
	 *
	 * @return string Cache type.
	 */
	public static function get_type() {
		return 'avatar';
	}

	/**
	 * Get the base directory path relative to uploads.
	 *
	 * @return string Base directory path.
	 */
	public static function get_base_dir() {
		return '/activitypub/actors/';
	}

	/**
	 * Get the context identifier for the filter.
	 *
	 * @return string Context identifier.
	 */
	public static function get_context() {
		return self::CONTEXT;
	}

	/**
	 * Get the maximum dimension for avatars.
	 *
	 * @return int Maximum width/height in pixels.
	 */
	public static function get_max_dimension() {
		return self::MAX_DIMENSION;
	}

	/**
	 * Initialize the cache handler.
	 */
	public static function init() {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Hook into the universal remote media URL filter for lazy caching.
		\add_filter( 'activitypub_remote_media_url', array( self::class, 'maybe_cache' ), 10, 4 );

		// Clear cached avatar URL when actor is updated (allows lazy re-caching).
		\add_action( 'save_post_' . Remote_Actors::POST_TYPE, array( self::class, 'clear_avatar_meta' ) );

		// Clean up files when actor is deleted.
		\add_action( 'before_delete_post', array( self::class, 'maybe_cleanup' ) );
	}

	/**
	 * Clear the cached avatar URL meta when an actor is updated.
	 *
	 * This allows lazy re-caching of the avatar on next access,
	 * ensuring updated avatars are fetched.
	 *
	 * @param int $post_id The actor post ID.
	 */
	public static function clear_avatar_meta( $post_id ) {
		// Invalidate cached files.
		self::invalidate_entity( $post_id );

		// Clear the meta so get_avatar_url() will re-cache on next access.
		\delete_post_meta( $post_id, '_activitypub_avatar_url' );
	}

	/**
	 * Maybe cache an avatar URL.
	 *
	 * Hooked to the activitypub_remote_media_url filter.
	 * Returns cached URL from meta if available, otherwise downloads and caches.
	 *
	 * @param string     $url       The remote URL.
	 * @param string     $context   The context ('avatar', 'media', 'emoji', etc.).
	 * @param string|int $entity_id The entity identifier (actor post ID).
	 * @param array      $options   Optional. Additional options (unused for avatars).
	 *
	 * @return string The local URL if cached successfully, otherwise the original URL.
	 */
	public static function maybe_cache( $url, $context, $entity_id = null, $options = array() ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Required for filter signature.
		if ( self::CONTEXT !== $context || empty( $url ) || empty( $entity_id ) ) {
			return $url;
		}

		// Check if we have a cached avatar URL in meta.
		$cached_url = \get_post_meta( $entity_id, '_activitypub_avatar_url', true );
		if ( $cached_url ) {
			return $cached_url;
		}

		// Download and cache the avatar.
		$local_url = self::cache(
			$url,
			$entity_id,
			array( 'max_dimension' => self::MAX_DIMENSION )
		);

		// Store the result in meta (local URL if cached, remote URL if not).
		$avatar_url = $local_url ?: $url;
		\update_post_meta( $entity_id, '_activitypub_avatar_url', \esc_url_raw( $avatar_url ) );

		return $avatar_url;
	}

	/**
	 * Maybe clean up cached avatar when actor is deleted.
	 *
	 * @param int $post_id The post ID being deleted.
	 */
	public static function maybe_cleanup( $post_id ) {
		if ( Remote_Actors::POST_TYPE !== \get_post_type( $post_id ) ) {
			return;
		}

		self::invalidate_entity( $post_id );
	}

	/**
	 * Save an avatar for an actor.
	 *
	 * This is a convenience method that wraps get_or_cache with the correct options.
	 * It also invalidates any existing avatar before caching the new one.
	 *
	 * @param int    $actor_id   The actor post ID.
	 * @param string $avatar_url The remote avatar URL.
	 *
	 * @return string|false The local avatar URL on success, false on failure.
	 */
	public static function save( $actor_id, $avatar_url ) {
		// Validate actor_id is a positive integer.
		$actor_id = (int) $actor_id;
		if ( $actor_id <= 0 ) {
			return false;
		}

		if ( empty( $avatar_url ) || ! \filter_var( $avatar_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Delete existing avatar files before saving new one.
		self::invalidate_entity( $actor_id );

		return self::cache(
			$avatar_url,
			$actor_id,
			array( 'max_dimension' => self::MAX_DIMENSION )
		);
	}
}
