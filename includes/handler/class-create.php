<?php
/**
 * Create handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;

use function Activitypub\is_self_ping;
use function Activitypub\is_activity_reply;
use function Activitypub\is_activity_public;
use function Activitypub\object_id_to_comment;

/**
 * Handle Create requests.
 */
class Create {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_create',
			array( self::class, 'handle_create' ),
			10,
			3
		);

		\add_filter(
			'activitypub_validate_object',
			array( self::class, 'validate_object' ),
			10,
			3
		);
	}

	/**
	 * Handles "Create" requests.
	 *
	 * @param array                          $activity        The activity-object.
	 * @param int                            $user_id         The id of the local blog-user.
	 * @param \Activitypub\Activity\Activity $activity_object Optional. The activity object. Default null.
	 */
	public static function handle_create( $activity, $user_id, $activity_object = null ) {
		// Check if Activity is public or not.
		if (
			! is_activity_public( $activity ) ||
			! is_activity_reply( $activity )
		) {
			return;
		}

		$check_dupe = object_id_to_comment( $activity['object']['id'] );

		// If comment exists, call update action.
		if ( $check_dupe ) {
			/**
			 * Fires when a Create activity is received for an existing comment.
			 *
			 * @param array                          $activity        The activity-object.
			 * @param int                            $user_id         The id of the local blog-user.
			 * @param \WP_Comment|\WP_Error          $check_dupe      The comment object or WP_Error.
			 * @param \Activitypub\Activity\Activity $activity_object The activity object.
			 */
			\do_action( 'activitypub_inbox_update', $activity, $user_id, $activity_object );
			return;
		}

		if ( is_self_ping( $activity['object']['id'] ) ) {
			return;
		}

		$state    = Interactions::add_comment( $activity );
		$reaction = null;

		if ( $state && ! \is_wp_error( $state ) ) {
			$reaction = \get_comment( $state );
		}

		/**
		 * Fires after a Create activity has been handled.
		 *
		 * @param array                      $activity The activity-object.
		 * @param int                        $user_id  The id of the local blog-user.
		 * @param \WP_Comment|\WP_Error      $state    The comment object or WP_Error.
		 * @param \WP_Comment|\WP_Error|null $reaction The reaction object or WP_Error.
		 */
		\do_action( 'activitypub_handled_create', $activity, $user_id, $state, $reaction );
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
		$json_params = $request->get_json_params();

		if ( empty( $json_params['type'] ) ) {
			return false;
		}

		if (
			'Create' !== $json_params['type'] ||
			is_wp_error( $request )
		) {
			return $valid;
		}

		$object = $json_params['object'];

		if ( ! is_array( $object ) ) {
			return false;
		}

		$required = array(
			'id',
			'content',
		);

		if ( array_intersect( $required, array_keys( $object ) ) !== $required ) {
			return false;
		}

		return $valid;
	}
}
