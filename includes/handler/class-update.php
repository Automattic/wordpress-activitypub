<?php
namespace Activitypub\Handler;

use WP_Error;
use Activitypub\Collection\Interactions;

/**
 * Handle Update requests.
 */
class Update {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_update', array( self::class, 'handle_update' ), 10, 3 );
	}

	/**
	 * Handle "Update" requests
	 *
	 * @param array                $array   The activity-object
	 * @param int                  $user_id The id of the local blog-user
	 * @param Activitypub\Activity $object  The activity object
	 */
	public static function handle_update( $array, $user_id, $object = null ) {
		if (
			! isset( $array['object'] ) ||
			! isset( $array['object']['id'] )
		) {
			return;
		}

		$state    = Interactions::update_comment( $array );
		$reaction = null;

		if ( $state && ! \is_wp_error( $reaction ) ) {
			$reaction = \get_comment( $state );
		}

		\do_action( 'activitypub_handled_update', $array, $user_id, $state, $reaction );
	}
}
