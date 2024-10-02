<?php
namespace Activitypub\Handler;

use WP_Error;
use Activitypub\Model\Notification;
use Activitypub\Collection\Interactions;

use function Activitypub\is_activity_public;
use function Activitypub\object_id_to_comment;

/**
 * Handle Create requests
 */
class Create {
	/**
	 * Initialize the class, registering WordPress hooks
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
	 * Handles "Create" requests
	 *
	 * @param array                $array   The activity-object
	 * @param int                  $user_id The id of the local blog-user
	 * @param Activitypub\Activity $object  The activity object
	 *
	 * @return void
	 */
	public static function handle_create( $array, $user_id, $object = null ) {
		// check if Activity is public or not
		if ( ! is_activity_public( $array ) ) {
			// send notification
			$notification = new Notification(
				'create',
				$array['actor'],
				$array,
				$user_id
			);
			$notification->send();

			return;
		}

		$check_dupe = object_id_to_comment( $array['object']['id'] );

		// if comment exists, call update action
		if ( $check_dupe ) {
			\do_action( 'activitypub_inbox_update', $array, $user_id, $object );
			return;
		}

		$state    = Interactions::add_comment( $array );
		$reaction = null;

		if ( $state && ! \is_wp_error( $state ) ) {
			$reaction = \get_comment( $state );
		}

		\do_action( 'activitypub_handled_create', $array, $user_id, $state, $reaction );
	}

	/**
	 * Validate the object
	 *
	 * @param bool             $valid   The validation state
	 * @param string           $param   The object parameter
	 * @param \WP_REST_Request $request The request object
	 * @param array            $array The activity-object
	 *
	 * @return bool The validation state: true if valid, false if not
	 */
	public static function validate_object( $valid, $param, $request ) {
		$json_params = $request->get_json_params();

		if (
			'Create' !== $json_params['type'] ||
			is_wp_error( $request )
		) {
			return $valid;
		}

		$object   = $json_params['object'];
		$required = array(
			'id',
			'inReplyTo',
			'content',
		);

		if ( array_intersect( $required, array_keys( $object ) ) !== $required ) {
			return false;
		}

		return $valid;
	}
}
