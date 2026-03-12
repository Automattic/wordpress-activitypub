<?php
/**
 * Outbox Announce handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use function Activitypub\object_to_uri;

/**
 * Handle outgoing Announce activities.
 */
class Announce {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_announce', array( self::class, 'handle_announce' ), 10, 2 );
	}

	/**
	 * Handle outgoing "Announce" activities from local actors.
	 *
	 * Records an announce/boost from the local user on remote content.
	 *
	 * @param array $data    The activity data array.
	 * @param int   $user_id The user ID.
	 */
	public static function handle_announce( $data, $user_id = null ) {
		$object_url = object_to_uri( $data['object'] ?? '' );

		if ( empty( $object_url ) ) {
			return $data;
		}

		/**
		 * Fires after an outgoing Announce activity has been processed.
		 *
		 * @param string $object_url The URL of the announced object.
		 * @param array  $data       The activity data.
		 * @param int    $user_id    The user ID.
		 */
		\do_action( 'activitypub_outbox_announce_sent', $object_url, $data, $user_id );

		return $data;
	}
}
