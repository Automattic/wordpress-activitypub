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
	 *
	 * @return void
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
	 * @return \WP_Post|\WP_Comment|\WP_Error|false The deleted object, an error if it may not or could not be deleted, or false without an object.
	 */
	public static function handle_delete( $data, $user_id = null ) {
		$object_id = object_to_uri( $data['object'] ?? '' );

		if ( empty( $object_id ) ) {
			return false;
		}

		// Try to delete a comment first, then fall back to a post.
		$result = self::maybe_delete_comment( $object_id, $user_id );

		if ( false === $result ) {
			$result = self::maybe_delete_post( $object_id, $user_id );
		}

		if ( $result && ! \is_wp_error( $result ) ) {
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
	 * @return \WP_Comment|\WP_Error|false The deleted comment, an error if it may not or could not be deleted, or false if there is no such comment.
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

		/*
		 * A user may delete their own comment, also on someone else's post. The blog actor owns no
		 * comments, so its user needs the right to edit the comment instead.
		 */
		if ( $user_id > 0 ? (int) $comment->user_id !== $user_id : ! \current_user_can( 'edit_comment', $comment->comment_ID ) ) {
			return new \WP_Error(
				'activitypub_forbidden',
				\__( 'You can only delete your own posts and comments.', 'activitypub' ),
				array(
					'status' => 403,
					'type'   => 'https://w3id.org/fep/c180#actor-not-authorized',
					'title'  => 'Actor not authorized',
				)
			);
		}

		if ( 'trash' === \wp_get_comment_status( $comment ) ) {
			return new \WP_Error(
				'activitypub_object_not_found',
				\__( 'The referenced object was not found.', 'activitypub' ),
				array(
					'status' => 400,
					'type'   => 'https://w3id.org/fep/c180#object-does-not-exist',
					'title'  => 'Object does not exist',
				)
			);
		}

		if ( \wp_trash_comment( $comment ) ) {
			return $comment;
		}

		return new \WP_Error(
			'activitypub_delete_failed',
			\__( 'The object could not be deleted.', 'activitypub' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * Try to delete a post by its ActivityPub ID.
	 *
	 * @param string $object_id The ActivityPub object ID (URL).
	 * @param int    $user_id   The user ID.
	 *
	 * @return \WP_Post|\WP_Error The deleted post, or an error if there is no such post or it may not or could not be deleted.
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
			return new \WP_Error(
				'activitypub_object_not_found',
				\__( 'The referenced object was not found.', 'activitypub' ),
				array(
					'status' => 400,
					'type'   => 'https://w3id.org/fep/c180#object-does-not-exist',
					'title'  => 'Object does not exist',
				)
			);
		}

		// Verify the user owns this post and may delete it.
		if ( ( (int) $post->post_author !== $user_id && $user_id > 0 ) || ! \current_user_can( 'delete_post', $post->ID ) ) {
			return new \WP_Error(
				'activitypub_forbidden',
				\__( 'You can only delete your own posts and comments.', 'activitypub' ),
				array(
					'status' => 403,
					'type'   => 'https://w3id.org/fep/c180#actor-not-authorized',
					'title'  => 'Actor not authorized',
				)
			);
		}

		if ( 'trash' === $post->post_status ) {
			return new \WP_Error(
				'activitypub_object_not_found',
				\__( 'The referenced object was not found.', 'activitypub' ),
				array(
					'status' => 400,
					'type'   => 'https://w3id.org/fep/c180#object-does-not-exist',
					'title'  => 'Object does not exist',
				)
			);
		}

		if ( \wp_trash_post( $post->ID ) ) {
			return $post;
		}

		return new \WP_Error(
			'activitypub_delete_failed',
			\__( 'The object could not be deleted.', 'activitypub' ),
			array( 'status' => 500 )
		);
	}
}
