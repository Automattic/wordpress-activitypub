<?php
/**
 * Comment media cache class.
 *
 * @package Activitypub
 */

namespace Activitypub\Cache;

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
	 * Get the base directory path relative to uploads.
	 *
	 * @return string Base directory path.
	 */
	public static function get_base_dir() {
		return self::BASE_DIR_COMMENTS;
	}

	/**
	 * Get the context identifier for the filter.
	 *
	 * @return string Context identifier.
	 */
	public static function get_context() {
		return self::CONTEXT_COMMENT;
	}

	/**
	 * Initialize the cache handler.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::is_enabled() ) {
			\add_filter( 'activitypub_remote_media_url', array( self::class, 'maybe_cache' ), 10, 4 );
		}
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
		\add_action( 'edit_comment', array( Media::class, 'invalidate_comment' ) );
		// Deleting a comment also transitions it, to 'delete'.
		\add_action( 'transition_comment_status', array( self::class, 'maybe_cleanup_comment' ), 10, 3 );
	}

	/**
	 * Remove cached images when a comment is no longer approved.
	 *
	 * @param string      $new_status The new comment status.
	 * @param string      $old_status The old comment status.
	 * @param \WP_Comment $comment    The comment.
	 *
	 * @return void
	 */
	public static function maybe_cleanup_comment( $new_status, $old_status, $comment ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Required for action signature.
		if ( 'approved' !== $new_status ) {
			Media::invalidate_comment( $comment->comment_ID );
		}
	}
}
