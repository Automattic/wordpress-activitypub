<?php
/**
 * Outbox Delete handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Remote_Posts;

use function Activitypub\object_to_uri;
use function Activitypub\url_to_commentid;

/**
 * Handle outgoing Delete activities.
 */
class Delete {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_delete', array( self::class, 'handle_delete' ), 10, 2 );
	}

	/**
	 * Handle outgoing "Delete" activities from local actors.
	 *
	 * Deletes a WordPress post or comment.
	 *
	 * @param array $data    The activity data array.
	 * @param int   $user_id The user ID.
	 *
	 * @return \WP_Post|\WP_Comment|false The deleted object, or false on failure.
	 */
	public static function handle_delete( $data, $user_id = null ) {
		$object_id = object_to_uri( $data['object'] ?? '' );

		if ( empty( $object_id ) ) {
			return false;
		}

		// Try to delete a comment first, then fall back to a post.
		$result = self::maybe_delete_comment( $object_id, $user_id );

		if ( ! $result ) {
			$result = self::maybe_delete_post( $object_id, $user_id );
		}

		if ( $result ) {
			/**
			 * Fires after content has been deleted via an outgoing Delete activity.
			 *
			 * @param \WP_Post|\WP_Comment $result  The deleted object.
			 * @param array                $data    The activity data.
			 * @param int                  $user_id The user ID.
			 */
			\do_action( 'activitypub_outbox_handled_delete', $result, $data, $user_id );
		}

		return $result;
	}

	/**
	 * Try to delete a comment by its ActivityPub ID.
	 *
	 * @param string $object_id The ActivityPub object ID (URL).
	 * @param int    $user_id   The user ID.
	 *
	 * @return \WP_Comment|false The deleted comment, or false on failure.
	 */
	private static function maybe_delete_comment( $object_id, $user_id ) {
		$comment_id = url_to_commentid( $object_id );

		if ( ! $comment_id ) {
			return false;
		}

		$comment = \get_comment( $comment_id );

		if ( ! $comment ) {
			return false;
		}

		// Verify the user owns this comment.
		if ( (int) $comment->user_id !== $user_id && $user_id > 0 ) {
			return false;
		}

		if ( \wp_trash_comment( $comment ) ) {
			return $comment;
		}

		return false;
	}

	/**
	 * Try to delete a post by its ActivityPub ID.
	 *
	 * @param string $object_id The ActivityPub object ID (URL).
	 * @param int    $user_id   The user ID.
	 *
	 * @return \WP_Post|false The deleted post, or false on failure.
	 */
	private static function maybe_delete_post( $object_id, $user_id ) {
		// Try to find a local post by permalink.
		$post_id = \url_to_postid( $object_id );
		$post    = $post_id ? \get_post( $post_id ) : null;

		// Fall back to Posts collection for remote posts (ap_post type).
		if ( ! $post instanceof \WP_Post ) {
			$post = Remote_Posts::get_by_guid( $object_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		// Verify the user owns this post.
		if ( (int) $post->post_author !== $user_id && $user_id > 0 ) {
			return false;
		}

		if ( \wp_trash_post( $post->ID ) ) {
			return $post;
		}

		return false;
	}
}
