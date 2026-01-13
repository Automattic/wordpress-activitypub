<?php
/**
 * Accept handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Following;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\object_to_uri;

/**
 * Handle Accept requests.
 */
class Accept {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_accept', array( self::class, 'handle_accept' ), 10, 2 );
		\add_filter( 'activitypub_validate_object', array( self::class, 'validate_object' ), 10, 3 );
	}

	/**
	 * Handles "Accept" requests.
	 *
	 * @param array     $accept   The activity-object.
	 * @param int|int[] $user_ids The id of the local blog-user.
	 */
	public static function handle_accept( $accept, $user_ids ) {
		// Validate that there is a Follow Activity.
		$outbox_post = Outbox::get_by_guid( $accept['object']['id'] );

		if (
			\is_wp_error( $outbox_post ) ||
			'Follow' !== \get_post_meta( $outbox_post->ID, '_activitypub_activity_type', true )
		) {
			return;
		}

		$actor_post = Remote_Actors::get_by_uri( object_to_uri( $accept['object']['object'] ) );

		if ( \is_wp_error( $actor_post ) ) {
			return;
		}

		$user_id = is_array( $user_ids ) ? reset( $user_ids ) : $user_ids;
		$result  = Following::accept( $actor_post, $user_id );
		$success = ! \is_wp_error( $result );

		/**
		 * Fires after an ActivityPub Accept activity has been handled.
		 *
		 * @param array              $accept   The ActivityPub activity data.
		 * @param int[]              $user_ids The local user IDs.
		 * @param bool               $success  True on success, false otherwise.
		 * @param \WP_Post|\WP_Error $result   The remote actor post or error.
		 */
		\do_action( 'activitypub_handled_accept', $accept, (array) $user_ids, $success, $result );
	}

	/**
	 * Validate the object.
	 *
	 * @param bool             $valid   The validation state.
	 * @param string           $param   The object parameter.
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return bool The validation state: true if valid, false if not.
	 */
	public static function validate_object( $valid, $param, $request ) {
		$activity = $request->get_json_params();

		if ( empty( $activity['type'] ) ) {
			return false;
		}

		if ( 'Accept' !== $activity['type'] ) {
			return $valid;
		}

		if ( ! isset( $activity['actor'], $activity['object'] ) ) {
			return false;
		}

		if ( ! \is_array( $activity['object'] ) ) {
			return false;
		}

		if ( ! isset( $activity['object']['id'], $activity['object']['type'], $activity['object']['actor'], $activity['object']['object'] ) ) {
			return false;
		}

		return $valid;
	}
}
