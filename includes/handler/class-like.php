<?php
/**
 * Like handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;
use Activitypub\Comment;

use function Activitypub\object_to_uri;

/**
 * Handle Like requests.
 */
class Like {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_like', array( self::class, 'handle_like' ), 10, 2 );
		\add_filter( 'activitypub_get_outbox_activity', array( self::class, 'outbox_activity' ) );
	}

	/**
	 * Handles "Like" requests.
	 *
	 * @param array $like    The Activity array.
	 * @param int   $user_id The ID of the local blog user.
	 */
	public static function handle_like( $like, $user_id ) {
		if ( ! Comment::is_comment_type_enabled( 'like' ) ) {
			return;
		}

		$url = object_to_uri( $like['object'] );

		if ( empty( $url ) ) {
			return;
		}

		$exists = Comment::object_id_to_comment( esc_url_raw( $url ) );
		if ( $exists ) {
			return;
		}

		$success = false;
		$result  = Interactions::add_reaction( $like );

		if ( $result && ! is_wp_error( $result ) ) {
			$success = true;
			$result  = get_comment( $result );
		}

		/**
		 * Fires after an ActivityPub Like activity has been handled.
		 *
		 * @param array                                        $like    The ActivityPub activity data.
		 * @param int                                          $user_id The local user ID.
		 * @param bool                                         $success True on success, false otherwise.
		 * @param array|false|int|string|\WP_Comment|\WP_Error $result  The WP_Comment object of the created like comment, or null if creation failed.
		 */
		\do_action( 'activitypub_handled_like', $like, $user_id, $success, $result );
	}

	/**
	 * Set the object to the object ID.
	 *
	 * @param \Activitypub\Activity\Activity $activity The Activity object.
	 * @return \Activitypub\Activity\Activity The filtered Activity object.
	 */
	public static function outbox_activity( $activity ) {
		if ( 'Like' === $activity->get_type() ) {
			$activity->set_object( object_to_uri( $activity->get_object() ) );
		}

		return $activity;
	}
}
