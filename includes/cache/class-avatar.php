<?php
/**
 * Avatar cache class.
 *
 * @package Activitypub
 */

namespace Activitypub\Cache;

use Activitypub\Collection\Remote_Actors;

use function Activitypub\object_to_uri;

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

		// Invalidate cached avatar when actor is updated so it re-downloads on next access.
		\add_action( 'save_post_' . Remote_Actors::POST_TYPE, array( self::class, 'clear_cached_avatar' ) );

		// Clean up files when actor is deleted.
		\add_action( 'before_delete_post', array( self::class, 'maybe_cleanup' ) );
	}

	/**
	 * Clear the cached avatar when an actor is updated.
	 *
	 * Invalidates cached files so the avatar is re-downloaded on next access.
	 *
	 * @param int $post_id The actor post ID.
	 */
	public static function clear_cached_avatar( $post_id ) {
		// Invalidate cached files so next access re-downloads.
		self::invalidate_entity( $post_id );

		// Clean up legacy meta from previous versions.
		\delete_post_meta( $post_id, '_activitypub_avatar_url' );
	}

	/**
	 * Maybe cache an avatar URL.
	 *
	 * Hooked to the activitypub_remote_media_url filter.
	 * Uses filesystem-based caching via get_or_cache() — no persistent meta storage.
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

		// A pure cache hit leaves no new files behind, so skip the stale-file
		// sweep to keep the hot render path cheap. Only a fresh download can
		// orphan an older avatar version.
		if ( static::get( $url, $entity_id ) ) {
			return self::get_or_cache( $url, $entity_id, array( 'max_dimension' => self::MAX_DIMENSION ) );
		}

		$cached_url = self::get_or_cache( $url, $entity_id, array( 'max_dimension' => self::MAX_DIMENSION ) );

		if ( $cached_url ) {
			// Remove older avatar versions that no longer match the current icon.
			self::prune_stale_files( $entity_id, self::generate_hash( $url ) );
		}

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

	/**
	 * Get the hash of the avatar currently referenced by an actor.
	 *
	 * Reads the actor's icon directly from post content without running the
	 * remote media filter, so this never triggers a lazy download.
	 *
	 * @since unreleased
	 *
	 * @param int $post_id The actor post ID.
	 *
	 * @return string|false The md5 hash of the current avatar URL, or false if none.
	 */
	public static function get_actor_avatar_hash( $post_id ) {
		$post = \get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}

		$actor_data = \json_decode( $post->post_content, true );
		if ( empty( $actor_data['icon'] ) ) {
			return false;
		}

		$avatar_url = object_to_uri( $actor_data['icon'] );
		if ( empty( $avatar_url ) || ! \filter_var( $avatar_url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		return self::generate_hash( $avatar_url );
	}

	/**
	 * Remove cached avatar files that no longer match the current icon.
	 *
	 * Keeps any file whose basename starts with the current hash and deletes
	 * the rest. Runs after the current avatar is written, so the active file
	 * is never removed.
	 *
	 * @since unreleased
	 *
	 * @param int    $entity_id    The actor post ID.
	 * @param string $current_hash The hash of the current avatar URL.
	 */
	public static function prune_stale_files( $entity_id, $current_hash ) {
		$paths = static::get_storage_paths( $entity_id );
		if ( ! \is_dir( $paths['basedir'] ) ) {
			return;
		}

		$files = \glob( $paths['basedir'] . '/*' );
		if ( empty( $files ) ) {
			return;
		}

		$prefix = $current_hash . '.';

		foreach ( $files as $file ) {
			if ( 0 === \strpos( \basename( $file ), $prefix ) ) {
				continue;
			}

			if ( \is_dir( $file ) ) {
				static::delete_directory( $file );
			} else {
				static::get_filesystem()->delete( $file );
			}
		}
	}

	/**
	 * Clean up stale cached avatars.
	 *
	 * Run daily by cron. Deletes orphaned actor directories that no longer
	 * match an actor post and removes older avatar versions for surviving
	 * actors. Processed in batches so a large backlog drains over several runs.
	 *
	 * @since unreleased
	 */
	public static function cleanup_actors() {
		// Lock the cleanup with an autoload-disabled option so overlapping
		// cron workers never run it twice at once.
		if ( ! \add_option( 'activitypub_avatar_cache_cleanup_lock', time(), '', false ) ) {
			$lock_time = (int) \get_option( 'activitypub_avatar_cache_cleanup_lock' );
			if ( $lock_time && ( time() - $lock_time ) < 30 * MINUTE_IN_SECONDS ) {
				return;
			}
			\delete_option( 'activitypub_avatar_cache_cleanup_lock' );
			if ( ! \add_option( 'activitypub_avatar_cache_cleanup_lock', time(), '', false ) ) {
				return;
			}
		}

		$upload_dir = \wp_upload_dir();
		$root       = $upload_dir['basedir'] . static::get_base_dir();
		$dirs       = \glob( $root . '/*', GLOB_ONLYDIR );

		if ( empty( $dirs ) ) {
			\delete_option( 'activitypub_avatar_cache_cleanup_lock' );
			\delete_option( 'activitypub_avatar_cache_cursor' );
			return;
		}

		// Sort so the resume position stays stable between runs.
		\sort( $dirs );

		/**
		 * Filters how many actor directories are scanned per cleanup run.
		 *
		 * @since unreleased
		 *
		 * @param int $limit The maximum number of directories to scan.
		 */
		$limit = \apply_filters( 'activitypub_cleanup_actor_cache_limit', 100 );

		// Resume where the previous run stopped so a large backlog drains
		// over several runs instead of always revisiting the first batch.
		$total = \count( $dirs );
		$start = (int) \get_option( 'activitypub_avatar_cache_cursor', 0 ) % $total;
		$batch = \array_slice( $dirs, $start, \max( 1, (int) $limit ) );

		foreach ( $batch as $dir ) {
			$dirname = \basename( $dir );

			// Only touch directories with a numeric name, to stay clear of junk.
			if ( ! \preg_match( '/^\d+$/', $dirname ) ) {
				continue;
			}

			$post_id = (int) $dirname;
			$post    = \get_post( $post_id );

			// Remove directories that no longer belong to an actor post.
			if ( ! $post || Remote_Actors::POST_TYPE !== $post->post_type ) {
				static::delete_directory( $dir );
				continue;
			}

			$hash = self::get_actor_avatar_hash( $post_id );
			if ( $hash ) {
				self::prune_stale_files( $post_id, $hash );
			}
		}

		\update_option( 'activitypub_avatar_cache_cursor', ( $start + \count( $batch ) ) % $total, false );
		\delete_option( 'activitypub_avatar_cache_cleanup_lock' );
	}
}
