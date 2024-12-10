<?php
/**
 * Akismet integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use function Activitypub\was_comment_received;

/**
 * Compatibility with the Akismet plugin.
 *
 * @see https://wordpress.org/plugins/akismet/
 */
class Akismet {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'comment_row_actions', array( self::class, 'comment_row_actions' ), 10, 2 );
	}

	/**
	 * Remove the "history" action from the comment row actions.
	 *
	 * @param array           $actions The existing actions.
	 * @param int|\WP_Comment $comment The comment object or ID.
	 *
	 * @return array The modified actions.
	 */
	public static function comment_row_actions( $actions, $comment ) {
		if ( was_comment_received( $comment ) ) {
			unset( $actions['history'] );
		}

		return $actions;
	}
}
