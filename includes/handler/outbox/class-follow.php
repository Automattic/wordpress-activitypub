<?php
/**
 * Outbox Follow handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use function Activitypub\follow;
use function Activitypub\object_to_uri;

/**
 * Handle outgoing Follow activities.
 */
class Follow {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_follow', array( self::class, 'handle_follow' ), 10, 2 );
	}

	/**
	 * Handle outgoing "Follow" activities from local actors.
	 *
	 * Delegates to the follow() function which handles pending state,
	 * proper activity addressing, and adding to the outbox.
	 *
	 * @param array $data    The activity data array.
	 * @param int   $user_id The user ID.
	 *
	 * @return int|\WP_Error The outbox post ID on success, or WP_Error on failure.
	 */
	public static function handle_follow( $data, $user_id = null ) {
		$object = object_to_uri( $data['object'] ?? '' );

		if ( empty( $object ) ) {
			return $data;
		}

		return follow( $object, $user_id );
	}
}
