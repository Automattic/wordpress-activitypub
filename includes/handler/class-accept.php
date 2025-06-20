<?php
/**
 * Accept handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Following;

/**
 * Handle Accept requests.
 */
class Accept {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_accept',
			array( self::class, 'handle_accept' ),
			10,
			2
		);
	}

	/**
	 * Handles "Accept" requests.
	 *
	 * @param array $accept The activity-object.
	 * @param int   $user_id The id of the local blog-user.
	 */
	public static function handle_accept( $accept, $user_id ) {
		Following::accept( $accept['object']['id'], $user_id );
	}
}
