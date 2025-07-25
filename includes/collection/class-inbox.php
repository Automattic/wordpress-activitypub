<?php
/**
 * Inbox collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Activity\Activity;

/**
 * ActivityPub Inbox Collection
 *
 * @link https://www.w3.org/TR/activitypub/#inbox
 */
class Inbox {
	const POST_TYPE = 'ap_inbox';

	/**
	 * Add an activity to the inbox.
	 *
	 * @param Activity|\WP_Error $activity The Activity object.
	 * @param int                $user_id  The id of the local blog-user.
	 */
	public static function add( $activity, $user_id ) {

	}
}
