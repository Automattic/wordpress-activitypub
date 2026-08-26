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

		$cached_url = self::get_or_cache( $url, $entity_id, array( 'max_dimension' => self::MAX_DIMENSION ) );

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
	 * Drop older cached avatar versions once a new one is written.
	 *
	 * Hooked into the shared write path via File::cache(), so every avatar
	 * write removes the files it replaced. Runs after the final filename is
	 * known, so the file just written is always kept, even when a collision
	 * renamed it to hash-1.webp.
	 *
	 * @since unreleased
	 *
	 * @param string     $url       The remote URL that was cached.
	 * @param string|int $entity_id The entity identifier (actor post ID).
	 * @param string     $file_path The path of the file that was written.
	 * @param string     $file_name The basename of the file that was written.
	 * @param array      $options   Cache options.
	 */
	protected static function after_cache( $url, $entity_id, $file_path, $file_name, $options ) {
		self::prune_stale_files( $entity_id, self::generate_hash( $url ), $file_name );
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
	 * @return string|false The md5 hash of the current avatar URL, false if the
	 *                      post is missing or not an actor, empty string when
	 *                      the actor has no usable icon.
	 */
	public static function get_actor_avatar_hash( $post_id ) {
		$post = \get_post( $post_id );
		if ( ! $post || Remote_Actors::POST_TYPE !== $post->post_type ) {
			return false;
		}

		return self::get_actor_avatar_hash_from_post( $post );
	}

	/**
	 * Get avatar hash from an actor post.
	 *
	 * @since unreleased
	 *
	 * @param \WP_Post $post Actor post.
	 *
	 * @return string The avatar hash, empty string when the actor has no usable icon.
	 */
	protected static function get_actor_avatar_hash_from_post( $post ) {
		$actor_data = \json_decode( $post->post_content, true );
		if ( empty( $actor_data['icon'] ) ) {
			return '';
		}

		$avatar_url = object_to_uri( $actor_data['icon'] );
		if ( empty( $avatar_url ) || ! \filter_var( $avatar_url, FILTER_VALIDATE_URL ) ) {
			return '';
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
	 * @param int         $entity_id      The actor post ID.
	 * @param string      $current_hash   The hash of the current avatar URL.
	 * @param string|null $current_file   The basename of the cached file to keep.
	 */
	public static function prune_stale_files( $entity_id, $current_hash, $current_file = null ) {
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
			$filename = \basename( $file );
			if ( $filename === $current_file || 0 === \strpos( $filename, $prefix ) ) {
				continue;
			}

			if ( \is_dir( $file ) ) {
				static::delete_directory( $file );
			} else {
				static::get_filesystem()->delete( $file );
			}
		}

		// Drop the entity directory itself when pruning left it empty.
		$remaining = \glob( $paths['basedir'] . '/*' );
		if ( empty( $remaining ) ) {
			static::delete_directory( $paths['basedir'] );
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
		$now = \time();
		if ( ! \add_option( 'activitypub_avatar_cache_cleanup_lock', $now, '', false ) ) {
			$lock_time = (int) \get_option( 'activitypub_avatar_cache_cleanup_lock' );
			if ( $lock_time && ( $now - $lock_time ) < 30 * MINUTE_IN_SECONDS ) {
				return;
			}
			\update_option( 'activitypub_avatar_cache_cleanup_lock', $now, false );
		}

		$upload_dir = \wp_upload_dir();
		$root       = $upload_dir['basedir'] . static::get_base_dir();
		if ( ! \is_dir( $root ) ) {
			\delete_option( 'activitypub_avatar_cache_cleanup_lock' );
			\delete_option( 'activitypub_avatar_cache_cursor' );
			return;
		}

		/**
		 * Filters how many actor directories are scanned per cleanup run.
		 *
		 * @since unreleased
		 *
		 * @param int $limit The maximum number of directories to scan.
		 */
		$limit  = \max( 1, (int) \apply_filters( 'activitypub_cleanup_actor_cache_limit', 100 ) );
		$cursor = (string) \get_option( 'activitypub_avatar_cache_cursor', '' );
		$dirs   = array();
		$found  = empty( $cursor );

		$iterator = new \DirectoryIterator( $root );
		foreach ( $iterator as $directory ) {
			if ( $directory->isDot() || ! $directory->isDir() ) {
				continue;
			}

			$dirname = $directory->getFilename();
			if ( ! $found ) {
				if ( $dirname === $cursor ) {
					$found = true;
				}
				continue;
			}
			if ( ! \preg_match( '/^\\d+$/', $dirname ) ) {
				continue;
			}

			$dirs[ (int) $dirname ] = $directory->getPathname();
			if ( \count( $dirs ) >= $limit ) {
				break;
			}
		}

		// Restart from the beginning after reaching the end of the directory tree.
		if ( empty( $dirs ) && ! empty( $cursor ) ) {
			\delete_option( 'activitypub_avatar_cache_cursor' );
			\delete_option( 'activitypub_avatar_cache_cleanup_lock' );
			return;
		}
		if ( empty( $dirs ) ) {
			\delete_option( 'activitypub_avatar_cache_cleanup_lock' );
			return;
		}

		$post_ids = \array_keys( $dirs );
		$posts    = \get_posts(
			array(
				'post__in'       => $post_ids,
				'post_type'      => Remote_Actors::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => \count( $post_ids ),
				'orderby'        => 'post__in',
			)
		);
		$post_map = array();
		foreach ( $posts as $post ) {
			$post_map[ $post->ID ] = $post;
		}

		$last_dir = null;
		foreach ( $dirs as $post_id => $dir ) {
			if ( empty( $post_map[ $post_id ] ) ) {
				static::delete_directory( $dir );
				continue;
			}

			$hash     = self::get_actor_avatar_hash_from_post( $post_map[ $post_id ] );
			self::prune_stale_files( $post_id, $hash );
			$last_dir = (string) $post_id;
		}

		// Resume after the last surviving directory. A directory pruned as an
		// orphan is gone from disk, so it can never be matched next run; only
		// anchor the cursor on a directory that still exists.
		if ( null !== $last_dir ) {
			\update_option( 'activitypub_avatar_cache_cursor', $last_dir, false );
		}
		\delete_option( 'activitypub_avatar_cache_cleanup_lock' );
	}
}
