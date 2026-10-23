<?php
/**
 * Comment media cache class.
 *
 * @package Activitypub
 */

namespace Activitypub\Cache;

use Activitypub\Blocks;

/**
 * Comment media cache class.
 *
 * Handles lazy caching of images attached to remote comments.
 * Images are stored in /wp-content/uploads/activitypub/comments/{comment_id}/
 * and removed as soon as the comment is deleted, edited, or no longer approved.
 *
 * @since unreleased
 */
class Comment_Media extends Media {
	/**
	 * Context identifier for the filter.
	 *
	 * @var string
	 */
	const CONTEXT = 'comment_media';

	/**
	 * Base directory for comment images.
	 *
	 * @var string
	 */
	const BASE_DIR = '/activitypub/comments/';

	/**
	 * Get the cache type identifier.
	 *
	 * @return string Cache type.
	 */
	public static function get_type() {
		return 'comment_media';
	}

	/**
	 * Get the base directory path relative to uploads.
	 *
	 * @return string Base directory path.
	 */
	public static function get_base_dir() {
		return self::BASE_DIR;
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
	 * Initialize the cache handler.
	 *
	 * The cleanup is registered separately by register_cleanup().
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_enabled() ) {
			return;
		}

		\add_filter( 'activitypub_remote_media_url', array( self::class, 'maybe_cache' ), 10, 4 );
	}

	/**
	 * Register the hooks that remove cached images with their comment.
	 *
	 * Removing a comment has to remove its images from the server, also when caching was
	 * disabled after they were cached, so this runs whether caching is enabled or not.
	 * Files are cached lazily, so a restored comment downloads them again.
	 *
	 * @return void
	 */
	public static function register_cleanup() {
		\add_action( 'edit_comment', array( self::class, 'clear_cached_images' ) );
		// Deleting a comment also transitions it, to 'delete'.
		\add_action( 'transition_comment_status', array( self::class, 'maybe_cleanup_comment' ), 10, 3 );
		\add_action( 'update_option_activitypub_allow_comment_images', array( self::class, 'maybe_clear_all_images' ), 10, 2 );
	}

	/**
	 * Remove all cached comment images when comment images are turned off.
	 *
	 * Files are cached lazily, so turning the option on again downloads them again.
	 *
	 * @param mixed $old_value The old option value.
	 * @param mixed $value     The new option value.
	 *
	 * @return void
	 */
	public static function maybe_clear_all_images( $old_value, $value ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Required for action signature.
		if ( ! $value ) {
			self::delete_directory( \wp_upload_dir()['basedir'] . self::BASE_DIR );
		}
	}

	/**
	 * Cache a remote comment image locally.
	 *
	 * @param string     $url       The remote URL.
	 * @param string|int $entity_id The entity identifier (comment ID).
	 * @param array      $options   Optional. Additional options.
	 *
	 * @return string|false The local URL on success, false on failure.
	 */
	public static function cache( $url, $entity_id, $options = array() ) {
		$local_url = parent::cache( $url, $entity_id, $options );

		/*
		 * A comment removed or edited while its image downloads loses its directory, and
		 * File::cache() re-creates it to store the file. Remove it again unless the comment is
		 * still approved and still has the image, nothing would clean it up later.
		 */
		if ( $local_url && ! self::shows_image( $entity_id, $url ) ) {
			self::clear_cached_images( (int) $entity_id );

			return false;
		}

		return $local_url;
	}

	/**
	 * Whether a comment is approved and still has an image.
	 *
	 * @param string|int $comment_id The comment ID.
	 * @param string     $url        The remote image URL.
	 *
	 * @return bool True if the comment shows the image.
	 */
	private static function shows_image( $comment_id, $url ) {
		// The comment may have changed in another request while the image downloaded.
		\wp_cache_delete( (int) $comment_id, 'comment' );
		$comment = \get_comment( $comment_id );

		if ( ! $comment || 'approved' !== \wp_get_comment_status( $comment ) ) {
			return false;
		}

		foreach ( \parse_blocks( $comment->comment_content ) as $block ) {
			if ( 'activitypub/image' === $block['blockName'] && Blocks::get_image_url( $block['attrs'] ) === $url ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Maybe clean up cached images when a comment is no longer approved.
	 *
	 * @param string      $new_status The new comment status.
	 * @param string      $old_status The old comment status.
	 * @param \WP_Comment $comment    The comment.
	 *
	 * @return void
	 */
	public static function maybe_cleanup_comment( $new_status, $old_status, $comment ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Required for action signature.
		if ( 'approved' !== $new_status ) {
			self::clear_cached_images( (int) $comment->comment_ID );
		}
	}

	/**
	 * Remove the cached images of a comment.
	 *
	 * Files are cached lazily, so the next render downloads them again.
	 *
	 * @param int $comment_id The comment ID.
	 *
	 * @return void
	 */
	public static function clear_cached_images( $comment_id ) {
		self::invalidate_entity( $comment_id );
	}
}
