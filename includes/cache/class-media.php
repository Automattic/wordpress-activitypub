<?php
/**
 * Media cache class.
 *
 * @package Activitypub
 */

namespace Activitypub\Cache;

use Activitypub\Collection\Remote_Posts;

/**
 * Media cache class.
 *
 * Handles lazy caching of remote post media locally.
 * Media is cached on-demand when URLs pass through the `activitypub_remote_media_url` filter.
 *
 * Media is stored in /wp-content/uploads/activitypub/posts/{post_id}/
 * and cleaned up automatically when the post is deleted.
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
	 * Base directory for post media.
	 *
	 * @var string
	 */
	const BASE_DIR_POSTS = '/activitypub/posts/';

	/**
	 * Context identifier for comment media.
	 *
	 * @deprecated unreleased Use {@see Comment_Media::CONTEXT}.
	 *
	 * @var string
	 */
	const CONTEXT_COMMENT = Comment_Media::CONTEXT;

	/**
	 * Base directory for comment media.
	 *
	 * @deprecated unreleased Use {@see Comment_Media::BASE_DIR}.
	 *
	 * @var string
	 */
	const BASE_DIR_COMMENTS = Comment_Media::BASE_DIR;

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
	 * Initialize the cache handler.
	 *
	 * @return void
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
		if ( static::get_context() !== $context || empty( $url ) || empty( $entity_id ) ) {
			return $url;
		}

		$cached_url = static::get_or_cache( $url, $entity_id );

		return $cached_url ?: $url;
	}

	/**
	 * Maybe clean up cached media when post is deleted.
	 *
	 * @param int $post_id The post ID being deleted.
	 *
	 * @return void
	 */
	public static function maybe_cleanup( $post_id ) {
		if ( Remote_Posts::POST_TYPE !== \get_post_type( $post_id ) ) {
			return;
		}

		self::invalidate_entity( $post_id );
	}

	/**
	 * Get storage paths based on context.
	 *
	 * @deprecated unreleased Use {@see Media::get_storage_paths()} or {@see Comment_Media::get_storage_paths()}.
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
		\_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Cache\Media::get_storage_paths or Activitypub\Cache\Comment_Media::get_storage_paths' );

		return Comment_Media::CONTEXT === $context ? Comment_Media::get_storage_paths( $entity_id ) : self::get_storage_paths( $entity_id );
	}

	/**
	 * Invalidate cached media for a comment.
	 *
	 * @deprecated unreleased Use {@see Comment_Media::clear_cached_images()}.
	 *
	 * @param int $comment_id The comment ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function invalidate_comment( $comment_id ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Activitypub\Cache\Comment_Media::clear_cached_images' );

		return Comment_Media::invalidate_entity( $comment_id );
	}
}
