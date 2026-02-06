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
 * Handles caching of remote post and comment media locally.
 * Media is stored in /wp-content/uploads/activitypub/ap_posts/{post_id}/ or
 * /wp-content/uploads/activitypub/comments/{comment_id}/ and cleaned up
 * automatically when the parent post is deleted.
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
	 * @var string
	 */
	const CONTEXT_COMMENT = 'comment_media';

	/**
	 * Base directory for post media.
	 *
	 * @var string
	 */
	const BASE_DIR_POSTS = '/activitypub/ap_posts/';

	/**
	 * Base directory for comment media.
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
		if ( ! self::is_enabled() ) {
			return;
		}

		// Hook into the universal remote media URL filter.
		\add_filter( 'activitypub_remote_media_url', array( self::class, 'maybe_cache' ), 10, 3 );

		// Clean up when post is deleted.
		\add_action( 'before_delete_post', array( self::class, 'maybe_cleanup' ) );
	}

	/**
	 * Maybe cache a media URL.
	 *
	 * Hooked to the activitypub_remote_media_url filter.
	 *
	 * @param string     $url       The remote URL.
	 * @param string     $context   The context ('media', 'comment_media', etc.).
	 * @param string|int $entity_id The entity identifier (post or comment ID).
	 *
	 * @return string The local URL if cached successfully, otherwise the original URL.
	 */
	public static function maybe_cache( $url, $context, $entity_id = null ) {
		if ( ! \in_array( $context, array( self::CONTEXT, self::CONTEXT_COMMENT ), true ) ) {
			return $url;
		}

		if ( empty( $url ) || empty( $entity_id ) ) {
			return $url;
		}

		$cached_url = self::cache_with_context(
			$url,
			$entity_id,
			$context,
			array( 'max_dimension' => self::MAX_DIMENSION )
		);

		return $cached_url ?: $url;
	}

	/**
	 * Cache a file with context-aware storage.
	 *
	 * @param string     $url       The remote URL.
	 * @param string|int $entity_id The entity identifier.
	 * @param string     $context   The context for path resolution.
	 * @param array      $options   Optional. Additional options.
	 *
	 * @return string|false The local URL on success, false on failure.
	 */
	public static function cache_with_context( $url, $entity_id, $context, $options = array() ) {
		if ( empty( $url ) || ! \filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Check if already cached.
		$paths = self::get_storage_paths_for_context( $entity_id, $context );
		$hash  = self::generate_hash( $url );

		if ( \is_dir( $paths['basedir'] ) ) {
			$matches = \glob( $paths['basedir'] . '/' . $hash . '.*' );
			if ( ! empty( $matches ) && \is_file( $matches[0] ) ) {
				return $paths['baseurl'] . '/' . \basename( $matches[0] );
			}
		}

		// Download and validate.
		$result = self::download_and_validate( $url );
		if ( \is_wp_error( $result ) || empty( $result['file'] ) ) {
			return false;
		}

		$tmp_file = $result['file'];

		// Create directory if it doesn't exist.
		if ( ! \wp_mkdir_p( $paths['basedir'] ) ) {
			\wp_delete_file( $tmp_file );
			return false;
		}

		// Generate hash-based filename.
		$ext = \pathinfo( $tmp_file, PATHINFO_EXTENSION );
		if ( empty( $ext ) ) {
			$ext = self::mime_to_extension( $result['mime_type'] );
		}
		$file_name = $hash . '.' . $ext;
		$file_path = $paths['basedir'] . '/' . $file_name;

		// Initialize filesystem.
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			\WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			\wp_delete_file( $tmp_file );
			return false;
		}

		// Move file to destination.
		if ( ! $wp_filesystem->move( $tmp_file, $file_path, true ) ) {
			\wp_delete_file( $tmp_file );
			return false;
		}

		// Optimize image.
		$max_dimension = $options['max_dimension'] ?? self::MAX_DIMENSION;
		$file_path     = self::optimize_image( $file_path, $max_dimension );
		$file_name     = \basename( $file_path );

		return $paths['baseurl'] . '/' . $file_name;
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
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			\WP_Filesystem();
		}

		if ( ! $wp_filesystem ) {
			return false;
		}

		$paths = self::get_storage_paths_for_context( $comment_id, self::CONTEXT_COMMENT );

		if ( $wp_filesystem->is_dir( $paths['basedir'] ) ) {
			return $wp_filesystem->rmdir( $paths['basedir'], true );
		}

		return true;
	}
}
