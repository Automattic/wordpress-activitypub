<?php
/**
 * Outbox Add handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Actors;
use Activitypub\Scheduler\Post as Post_Scheduler;

use function Activitypub\add_to_outbox;
use function Activitypub\object_to_uri;

/**
 * Handle outgoing Add activities.
 *
 * Supports adding objects to an actor's featured collection
 * by making the corresponding WordPress post sticky.
 */
class Add {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_add', array( self::class, 'handle_add' ), 10, 2 );
	}

	/**
	 * Handle outgoing "Add" activities from local actors.
	 *
	 * When the target is the actor's featured collection, the referenced
	 * post is made sticky. The activity is then added to the outbox for
	 * federation.
	 *
	 * @since unreleased
	 *
	 * @param array $data    The activity data array.
	 * @param int   $user_id The user ID.
	 *
	 * @return array|int|\WP_Error The original data if unhandled, outbox post ID on success, or WP_Error on failure.
	 */
	public static function handle_add( $data, $user_id = null ) {
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
				\__( 'You can only feature your own posts.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		// Temporarily unhook the scheduler to avoid a duplicate outbox entry.
		\remove_action( 'post_stuck', array( Post_Scheduler::class, 'schedule_featured_add' ) );
		\stick_post( $post_id );
		\add_action( 'post_stuck', array( Post_Scheduler::class, 'schedule_featured_add' ) );

		return add_to_outbox( $data, 'Add', $user_id );
	}
}
