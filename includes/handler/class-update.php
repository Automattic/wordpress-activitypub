<?php
namespace Activitypub\Handler;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handle Update requests.
 */
class Update {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_update', array( self::class, 'handle_update' ), 10 );
	}

	/**
	 * Handle "Update" requests
	 *
	 * @param array $activity The JSON "Undo" Activity
	 */
	public static function handle_update( $activity ) {
		// Get the post object.
		$post = null;

		// Check if the post exists.
		if ( ! $post ) {
			return new WP_Error( 'activitypub_post_not_found', __( 'Post not found.', 'activitypub' ), array( 'status' => 404 ) );
		}

		// Check if the user has permission to edit the post.
		if ( ! \current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'activitypub_permission_denied', __( 'You do not have permission to edit this post.', 'activitypub' ), array( 'status' => 403 ) );
		}

		// Update the post content.
		$post_data = array(
			'ID'           => $post->ID,
			'post_content' => $activity['object']['content'],
		);
		wp_update_post( $post_data );
	}
}
