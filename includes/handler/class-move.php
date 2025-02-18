<?php
/**
 * Move handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

/**
 * Handle Move requests.
 */
class Move {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_move',
			array( self::class, 'handle_move' ),
			10,
			2
		);
	}

	/**
	 * Handle Move requests.
	 *
	 * @param array    $activity The JSON "Move" Activity.
	 * @param int|null $user_id  The ID of the user who initiated the "Move" activity.
	 */
	public static function handle_move( $activity, $user_id ) {
		$activity;
		$user_id;
		// TODO: Implement handle_move() method.
	}
}
