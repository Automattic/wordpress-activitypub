<?php
/**
 * Outbox Like handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use function Activitypub\object_to_uri;

/**
 * Handle outgoing Like activities.
 */
class Like {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_like', array( self::class, 'handle_like' ), 10, 2 );
	}

	/**
	 * Handle outgoing "Like" activities from local actors.
	 *
	 * Records a like from the local user on remote content.
	 *
	 * @param array $data    The activity data array.
	 * @param int   $user_id The user ID.
	 */
	public static function handle_like( $data, $user_id = null ) {
		$object_url = object_to_uri( $data['object'] ?? '' );

		if ( empty( $object_url ) ) {
			return $data;
		}

		/**
		 * Fires after an outgoing Like activity has been processed.
		 *
		 * @param string $object_url The URL of the liked object.
		 * @param array  $data       The activity data.
		 * @param int    $user_id    The user ID.
		 */
		\do_action( 'activitypub_outbox_like_sent', $object_url, $data, $user_id );

		return $data;
	}
}
