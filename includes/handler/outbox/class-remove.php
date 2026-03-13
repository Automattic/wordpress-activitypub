<?php
/**
 * Outbox Remove handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Actors;
use Activitypub\Scheduler\Post as Post_Scheduler;

use function Activitypub\add_to_outbox;
use function Activitypub\object_to_uri;

/**
 * Handle outgoing Remove activities.
 *
 * Supports removing objects from an actor's featured collection
 * by unsticking the corresponding WordPress post.
 */
class Remove {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_remove', array( self::class, 'handle_remove' ), 10, 2 );
	}

	/**
	 * Handle outgoing "Remove" activities from local actors.
	 *
	 * When the target is the actor's featured collection, the referenced
	 * post is unstuck. The activity is then added to the outbox for
	 * federation.
	 *
	 * @since unreleased
	 *
	 * @param array $data    The activity data array.
	 * @param int   $user_id The user ID.
	 *
	 * @return int|\WP_Error The outbox post ID on success, or WP_Error on failure.
	 */
	public static function handle_remove( $data, $user_id = null ) {
		$object_uri = object_to_uri( $data['object'] ?? '' );
		$target     = object_to_uri( $data['target'] ?? '' );

		if ( empty( $object_uri ) || empty( $target ) ) {
			return $data;
		}

		$actor = Actors::get_by_id( $user_id );

		if ( \is_wp_error( $actor ) ) {
			return $actor;
		}

		// Only handle featured collection targets.
		if ( $target !== $actor->get_featured() ) {
			return $data;
		}

		$post_id = \url_to_postid( $object_uri );

		if ( ! $post_id ) {
			return new \WP_Error(
				'activitypub_object_not_found',
				\__( 'The referenced object was not found.', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$post = \get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error(
				'activitypub_object_not_found',
				\__( 'The referenced object was not found.', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		// Verify the user owns this post.
		if ( $user_id > 0 && (int) $post->post_author !== $user_id ) {
			return new \WP_Error(
				'activitypub_forbidden',
				\__( 'You can only unfeature your own posts.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		// Temporarily unhook the scheduler to avoid a duplicate outbox entry.
		\remove_action( 'post_unstuck', array( Post_Scheduler::class, 'schedule_featured_remove' ) );
		\unstick_post( $post_id );
		\add_action( 'post_unstuck', array( Post_Scheduler::class, 'schedule_featured_remove' ) );

		return add_to_outbox( $data, 'Remove', $user_id );
	}
}
