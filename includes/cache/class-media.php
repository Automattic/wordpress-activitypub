<?php
/**
 * Media cache class.
 *
 * @package Activitypub
 */

namespace Activitypub\Cache;

use Activitypub\Collection\Posts;

/**
 * Media cache class.
 *
 * Handles lazy caching of remote post and comment media locally.
 * Media is cached on-demand when URLs pass through the `activitypub_remote_media_url` filter.
 *
 * Storage locations:
 * - Posts: /wp-content/uploads/activitypub/posts/{post_id}/
 * - Comments: /wp-content/uploads/activitypub/comments/{comment_id}/
 *
 * Files are cleaned up automatically when the parent post is deleted.
 *
 * @since 5.6.0
 */
class Media extends File {
	/**
	 * Maximum dimension for media images in pixels.
	 *
	 * @var int
	 */
	const MAX_DIMENSION = 1200;

	/**
	 * Context identifier for post media.
	 *
	 * @var string
	 */
	const CONTEXT = 'media';

	/**
	 * Context identifier for comment media.
	 *
	 * Reserved for future use when comment media caching is implemented.
	 * Currently, only post media caching is active via maybe_cache().
	 *
	 * @var string
	 */
	const CONTEXT_COMMENT = 'comment_media';

	/**
	 * Base directory for post media.
	 *
	 * @var string
	 */
	const BASE_DIR_POSTS = '/activitypub/posts/';

	/**
	 * Base directory for comment media.
	 *
	 * Reserved for future use when comment media caching is implemented.
	 *
	 * @var string
	 */
	const BASE_DIR_COMMENTS = '/activitypub/comments/';

	/**
	 * Get the cache type identifier.
	 *
	 * @return string Cache type.
	 */
	public static function get_type() {
		return 'media';
	}

	/**
	 * Get the base directory path relative to uploads.
	 *
	 * Default to post media directory. Use get_storage_paths_for_context()
	 * for context-aware path resolution.
	 *
	 * @return string Base directory path.
	 */
	public static function get_base_dir() {
		return self::BASE_DIR_POSTS;
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
	 * Get the maximum dimension for media images.
	 *
	 * @return int Maximum width/height in pixels.
	 */
	public static function get_max_dimension() {
		return self::MAX_DIMENSION;
	}

	/**
	 * Get storage paths based on context.
	 *
	 * @param string|int $entity_id The entity identifier.
	 * @param string     $context   The context ('media' or 'comment_media').
	 *
	 * @return array {
	 *     Storage paths for the entity.
	 *
	 *     @type string $basedir Base directory path.
	 *     @type string $baseurl Base URL.
	 * }
	 */
	public static function get_storage_paths_for_context( $entity_id, $context = self::CONTEXT ) {
		$upload_dir = \wp_upload_dir();
		$entity_id  = \sanitize_file_name( (string) $entity_id );
		$base_dir   = self::CONTEXT_COMMENT === $context ? self::BASE_DIR_COMMENTS : self::BASE_DIR_POSTS;

		return array(
			'basedir' => $upload_dir['basedir'] . $base_dir . $entity_id,
			'baseurl' => $upload_dir['baseurl'] . $base_dir . $entity_id,
		);
	}

	/**
	 * Initialize the cache handler.
	 */
	public static function init() {
		// Only register local caching filter when caching is enabled.
		if ( self::is_enabled() ) {
			\add_filter( 'activitypub_remote_media_url', array( self::class, 'maybe_cache' ), 10, 4 );

			// Clean up when post is deleted.
			\add_action( 'before_delete_post', array( self::class, 'maybe_cleanup' ) );
		}
	}

	/**
	 * Maybe cache a media URL.
	 *
	 * Hooked to the activitypub_remote_media_url filter.
	 * Downloads and caches the file locally if not already cached.
	 *
	 * @param string     $url       The remote URL.
	 * @param string     $context   The context ('avatar', 'media', 'emoji', etc.).
	 * @param string|int $entity_id The entity identifier (post ID).
	 * @param array      $options   Optional. Additional options.
	 *
	 * @return string The local URL if cached successfully, otherwise the original URL.
	 */
	public static function maybe_cache( $url, $context, $entity_id = null, $options = array() ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Required for filter signature.
		if ( self::CONTEXT !== $context || empty( $url ) || empty( $entity_id ) ) {
			return $url;
		}

		$cached_url = self::get_or_cache( $url, $entity_id );

		return $cached_url ?: $url;
	}

	/**
	 * Maybe clean up cached media when post is deleted.
	 *
	 * @param int $post_id The post ID being deleted.
	 */
	public static function maybe_cleanup( $post_id ) {
		if ( Posts::POST_TYPE !== \get_post_type( $post_id ) ) {
			return;
		}

		self::invalidate_entity( $post_id );
	}

	/**
	 * Invalidate cached media for a comment.
	 *
	 * @param int $comment_id The comment ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function invalidate_comment( $comment_id ) {
		$paths = self::get_storage_paths_for_context( $comment_id, self::CONTEXT_COMMENT );

		return static::delete_directory( $paths['basedir'] );
	}
}
