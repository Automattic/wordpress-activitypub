<?php
namespace Activitypub\Handler;

use Activitypub\Http;

/**
 * Handle Create requests
 */
class Announce {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_announce',
			array( self::class, 'handle_announce' ),
			10,
			3
		);
	}

	/**
	 * Handles "Announce" requests
	 *
	 * @param array                $array    The activity-object
	 * @param int                  $user_id  The id of the local blog-user
	 * @param Activitypub\Activity $activity The activity object
	 *
	 * @return void
	 */
	public static function handle_announce( $array, $user_id, $activity = null ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
		if ( ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS ) {
			return;
		}

		if ( ! isset( $array['object'] ) ) {
			return;
		}

		// check if Activity is public or not
		if ( ! is_activity_public( $array ) ) {
			// @todo maybe send email
			return;
		}

		// @todo save the `Announce`-Activity itself

		if ( is_string( $array['object'] ) ) {
			$object = Http::get_remote_object( $array['object'] );
		} else {
			$object = $array['object'];
		}

		if ( ! $object || is_wp_error( $object ) ) {
			return;
		}

		if ( ! isset( $object['type'] ) ) {
			return;
		}

		$type = \strtolower( $object['type'] );

		\do_action( 'activitypub_inbox', $object, $user_id, $type, $activity );
		\do_action( "activitypub_inbox_{$type}", $object, $user_id, $activity );
	}
}
