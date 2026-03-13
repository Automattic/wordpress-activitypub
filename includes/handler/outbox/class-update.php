<?php
/**
 * Outbox Update handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Posts;
use Activitypub\Collection\Remote_Posts;

use function Activitypub\is_activity_public;

/**
 * Handle outgoing Update activities (C2S).
 */
class Update {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_update', array( self::class, 'handle_update' ), 10, 3 );
	}

	/**
	 * Handle outgoing "Update" activities from local actors.
	 *
	 * Updates a WordPress post from the ActivityPub object. The post scheduler
	 * will add it to the outbox and federate it.
	 *
	 * @param array       $activity   The activity data.
	 * @param int         $user_id    The local user ID.
	 * @param string|null $visibility Content visibility.
	 *
	 * @return \WP_Post|\WP_Error|false The updated post on success, WP_Error on failure, false if not handled.
	 */
	public static function handle_update( $activity, $user_id = null, $visibility = null ) {
		// Skip private/direct activities.
		if ( ! is_activity_public( $activity ) ) {
			return false;
		}

		$object = $activity['object'] ?? array();

		if ( ! \is_array( $object ) ) {
			return false;
		}

		$type = $object['type'] ?? '';

		// Only handle Note and Article types.
		if ( ! \in_array( $type, array( 'Note', 'Article' ), true ) ) {
			return false;
		}

		$object_id = $object['id'] ?? '';

		if ( empty( $object_id ) ) {
			return false;
		}

		/*
		 * Find the post by its ActivityPub ID.
		 * First try to find a local post by permalink.
		 */
		$post_id = \url_to_postid( $object_id );
		$post    = $post_id ? \get_post( $post_id ) : null;

		// Fall back to Posts collection for remote posts (ap_post type).
		if ( ! $post instanceof \WP_Post ) {
			$post = Remote_Posts::get_by_guid( $object_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		/*
		 * Verify the user owns this post.
		 * The blog actor ($user_id === 0) can update any post since it
		 * represents the site itself.
		 */
		if ( (int) $post->post_author !== $user_id && $user_id > 0 ) {
			return false;
		}

		// Verify the user has permission to edit this post.
		if ( $user_id > 0 && ! \user_can( $user_id, 'edit_post', $post->ID ) ) {
			return new \WP_Error(
				'activitypub_forbidden',
				\__( 'You do not have permission to edit this post.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$post = Posts::update( $post, $activity, $visibility );

		if ( \is_wp_error( $post ) ) {
			return $post;
		}

		/**
		 * Fires after a post has been updated from an outgoing Update activity.
		 *
		 * @param int    $post_id    The updated post ID.
		 * @param array  $activity   The activity data.
		 * @param int    $user_id    The user ID.
		 */
		\do_action( 'activitypub_outbox_updated_post', $post->ID, $activity, $user_id );

		return $post;
	}
}
