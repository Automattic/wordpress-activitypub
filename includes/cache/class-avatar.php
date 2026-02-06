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

		// Hook into the universal remote media URL filter.
		\add_filter( 'activitypub_remote_media_url', array( self::class, 'maybe_cache' ), 10, 3 );

		// Clean up when actor is deleted.
		\add_action( 'before_delete_post', array( self::class, 'maybe_cleanup' ) );
	}

	/**
	 * Maybe cache an avatar URL.
	 *
	 * Hooked to the activitypub_remote_media_url filter.
	 * Always invalidates existing cached avatars before caching the new one
	 * to prevent accumulating old avatar files.
	 *
	 * @param string     $url       The remote URL.
	 * @param string     $context   The context ('avatar', 'media', 'emoji', etc.).
	 * @param string|int $entity_id The entity identifier (actor post ID).
	 *
	 * @return string The local URL if cached successfully, otherwise the original URL.
	 */
	public static function maybe_cache( $url, $context, $entity_id = null ) {
		if ( self::CONTEXT !== $context || empty( $url ) || empty( $entity_id ) ) {
			return $url;
		}

		// Always invalidate existing avatar before caching new one.
		// This prevents accumulating old avatar files when avatars are updated.
		self::invalidate_entity( $entity_id );

		$cached_url = self::cache(
			$url,
			$entity_id,
			array( 'max_dimension' => self::MAX_DIMENSION )
		);

		return $cached_url ?: $url;
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
