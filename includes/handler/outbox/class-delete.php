<?php
/**
 * Outbox Delete handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Posts;

use function Activitypub\object_to_uri;

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
	 * Deletes a WordPress post.
	 *
	 * @param array $data    The activity data array.
	 * @param int   $user_id The user ID.
	 */
	public static function handle_delete( $data, $user_id = null ) {
		$object = $data['object'] ?? '';

		// Get the object ID (can be a string URL or an object with an id).
		$object_id = object_to_uri( $object );

		if ( empty( $object_id ) ) {
			return;
		}

		/*
		 * Find the post by its ActivityPub ID.
		 * First try to find a local post by permalink (for C2S-created posts).
		 */
		$post_id = \url_to_postid( $object_id );
		$post    = $post_id ? \get_post( $post_id ) : null;

		// Fall back to Posts collection for remote posts (ap_post type).
		if ( ! $post instanceof \WP_Post ) {
			$post = Posts::get_by_guid( $object_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		/*
		 * Verify the user owns this post.
		 * The blog actor ($user_id === 0) can delete any post since it
		 * represents the site itself.
		 */
		if ( (int) $post->post_author !== $user_id && $user_id > 0 ) {
			return;
		}

		// Trash the post (use wp_delete_post with false to move to trash).
		$result = \wp_trash_post( $post->ID );

		if ( ! $result ) {
			return;
		}

		/**
		 * Fires after a post has been deleted from an outgoing Delete activity.
		 *
		 * @param int   $post_id  The deleted post ID.
		 * @param array $data     The activity data.
		 * @param int   $user_id  The user ID.
		 */
		\do_action( 'activitypub_outbox_deleted_post', $post->ID, $data, $user_id );
	}
}
