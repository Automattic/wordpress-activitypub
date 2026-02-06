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
 * - Posts: /wp-content/uploads/activitypub/ap_posts/{post_id}/
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

		// Cache media when post is saved/updated.
		\add_action( 'save_post_' . Posts::POST_TYPE, array( self::class, 'cache_post_media' ), 20 );

		// Clean up when post is deleted.
		\add_action( 'before_delete_post', array( self::class, 'maybe_cleanup' ) );
	}

	/**
	 * Cache media from post content and attachments meta.
	 *
	 * Caches remote image URLs from both:
	 * - Image tags in post content
	 * - Attachment URLs stored in _activitypub_attachments meta
	 *
	 * Updates the post content with cached URLs.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function cache_post_media( $post_id ) {
		// Check if caching is still enabled (allows runtime disabling via filter).
		if ( ! self::is_enabled() ) {
			return;
		}

		$post = \get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// Collect URLs from both content and attachments meta.
		$urls_to_cache = array();
		$upload_base   = \wp_upload_dir()['baseurl'];

		// Find image URLs in content.
		if ( ! empty( $post->post_content ) ) {
			\preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches );
			if ( ! empty( $matches[1] ) ) {
				$urls_to_cache = array_merge( $urls_to_cache, $matches[1] );
			}
		}

		// Get attachment URLs from meta.
		$attachment_urls = \get_post_meta( $post_id, '_activitypub_attachments', true );
		if ( ! empty( $attachment_urls ) && \is_array( $attachment_urls ) ) {
			$urls_to_cache = array_merge( $urls_to_cache, $attachment_urls );
		}

		// Remove duplicates.
		$urls_to_cache = array_unique( $urls_to_cache );

		// Filter to only remote URLs that need caching.
		$remote_urls = array();
		foreach ( $urls_to_cache as $url ) {
			// Skip non-http URLs (data URIs, relative paths).
			if ( ! \preg_match( '#^https?://#i', $url ) ) {
				continue;
			}

			// Skip if already a local URL.
			if ( \str_contains( $url, $upload_base ) ) {
				continue;
			}

			$remote_urls[] = $url;
		}

		// Clear the attachments meta after processing (regardless of whether we cache).
		\delete_post_meta( $post_id, '_activitypub_attachments' );

		// Only proceed if there are remote URLs to cache.
		if ( empty( $remote_urls ) ) {
			return;
		}

		// Invalidate existing cached media before re-caching.
		self::invalidate_entity( $post_id );

		$content      = $post->post_content;
		$urls_changed = false;

		foreach ( $remote_urls as $url ) {
			// Cache the image.
			$cached_url = self::cache_url( $url, $post_id );

			if ( $cached_url && $cached_url !== $url && ! empty( $content ) ) {
				$content      = \str_replace( $url, $cached_url, $content );
				$urls_changed = true;
			}
		}

		// Update post content if URLs were replaced.
		if ( $urls_changed ) {
			// Unhook to prevent infinite loop.
			\remove_action( 'save_post_' . Posts::POST_TYPE, array( self::class, 'cache_post_media' ), 20 );

			\wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $content,
				)
			);

			// Re-hook.
			\add_action( 'save_post_' . Posts::POST_TYPE, array( self::class, 'cache_post_media' ), 20 );
		}
	}

	/**
	 * Cache a single URL.
	 *
	 * @param string $url       The remote URL to cache.
	 * @param int    $entity_id The entity ID (post or comment).
	 *
	 * @return string|false The cached local URL, or false on failure.
	 */
	public static function cache_url( $url, $entity_id ) {
		$paths = self::get_storage_paths_for_context( $entity_id, self::CONTEXT );
		$hash  = self::generate_hash( $url );

		// Check if already cached.
		if ( \is_dir( $paths['basedir'] ) ) {
			$matches = \glob( $paths['basedir'] . '/' . $hash . '.*' );
			if ( ! empty( $matches ) && \is_file( $matches[0] ) ) {
				return $paths['baseurl'] . '/' . \basename( $matches[0] );
			}
		}

		// Download and cache.
		$result = self::download_and_validate( $url );
		if ( \is_wp_error( $result ) || empty( $result['file'] ) ) {
			return false;
		}

		$tmp_file = $result['file'];

		// Create directory if needed.
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

		if ( ! $wp_filesystem || ! $wp_filesystem->move( $tmp_file, $file_path, true ) ) {
			\wp_delete_file( $tmp_file );
			return false;
		}

		// Optimize image.
		$file_path = self::optimize_image( $file_path, self::MAX_DIMENSION );
		$file_name = \basename( $file_path );

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
