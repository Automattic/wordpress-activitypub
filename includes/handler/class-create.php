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
	 * @return void
	 */
	public static function handle_create( $array, $user_id, $object = null ) {
		if (
			! isset( $array['object'] ) ||
			! isset( $array['object']['id'] )
		) {
			return;
		}

		// check if Activity is public or not
		if ( ! is_activity_public( $array ) ) {
			// @todo maybe send email
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

		if ( $state && ! \is_wp_error( $reaction ) ) {
			$reaction = \get_comment( $state );
		}

		\do_action( 'activitypub_handled_create', $array, $user_id, $state, $reaction );
	}
}
