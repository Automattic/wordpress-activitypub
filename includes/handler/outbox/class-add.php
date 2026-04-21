<?php
/**
 * Outbox Add handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Actors;

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
	 * post is made sticky. The sticky action triggers the scheduler which
	 * creates the outbox entry automatically.
	 *
	 * @since 8.1.0
	 *
	 * @param array $data    The activity data array.
	 * @param int   $user_id The user ID.
	 *
	 * @return \WP_Post|\WP_Error|array The post object on success, WP_Error on failure, or original data if unhandled.
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

		// Making the post sticky triggers the scheduler which adds to outbox.
		\stick_post( $post_id );

		return $post;
	}
}
