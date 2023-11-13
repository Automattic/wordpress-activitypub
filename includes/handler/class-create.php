<?php
namespace Activitypub\Handler;

use WP_Error;
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
		\add_action( 'activitypub_inbox_create', array( self::class, 'handle_create' ), 10, 3 );
	}

	/**
	 * Handles "Create" requests
	 *
	 * @param array                $array   The activity-object
	 * @param int                  $user_id The id of the local blog-user
	 * @param Activitypub\Activity $object  The activity object
	 *
	 * @return void|WP_Error WP_Error on failure
	 */
	public static function handle_create( $array, $user_id, $object = null ) {
		if (
			! isset( $array['object'] ) ||
			! isset( $array['object']['id'] )
		) {
			return new WP_Error(
				'activitypub_no_valid_object',
				__( 'No object id found.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// check if Activity is public or not
		if ( ! is_activity_public( $array ) ) {
			// @todo maybe send email
			return new WP_Error(
				'activitypub_activity_not_public',
				__( 'Activity is not public.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		$check_dupe = object_id_to_comment( $array['object']['id'] );

		// if comment exists, call update action
		if ( $check_dupe ) {
			\do_action( 'activitypub_inbox_update', $array, $user_id, $object );
			return new WP_Error(
				'activitypub_comment_exists',
				__( 'Comment already exists, initiated Update process.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		$reaction = Interactions::add_comment( $array );
		$state    = null;

		if ( $reaction ) {
			$state = $reaction['comment_ID'];
		}

		\do_action( 'activitypub_handled_create', $array, $user_id, $state, $reaction );
	}
}
