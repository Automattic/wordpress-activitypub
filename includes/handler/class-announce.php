<?php
/**
 * Announce handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;
use Activitypub\Comment;
use Activitypub\Http;

use function Activitypub\is_activity;
use function Activitypub\is_activity_public;
use function Activitypub\object_to_uri;

/**
 * Handle Create requests.
 */
class Announce {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_announce', array( self::class, 'handle_announce' ), 10, 3 );
	}

	/**
	 * Handles "Announce" requests.
	 *
	 * @param array                          $announcement The activity-object.
	 * @param int                            $user_id      The id of the local blog-user.
	 * @param \Activitypub\Activity\Activity $activity     The activity object.
	 */
	public static function handle_announce( $announcement, $user_id, $activity = null ) {
		// Check if Activity is public or not.
		if ( ! is_activity_public( $announcement ) ) {
			// @todo maybe send email
			return;
		}

		// Check if reposts are allowed.
		if ( ! Comment::is_comment_type_enabled( 'repost' ) ) {
			return;
		}

		self::maybe_save_announce( $announcement, $user_id );

		if ( is_string( $announcement['object'] ) ) {
			$object = Http::get_remote_object( $announcement['object'] );
		} else {
			$object = $announcement['object'];
		}

		if ( ! $object || is_wp_error( $object ) ) {
			return;
		}

		if ( ! is_activity( $object ) ) {
			return;
		}

		$type = \strtolower( $object['type'] );

		/**
		 * Fires after an Announce has been received.
		 *
		 * @param array                               $object   The object.
		 * @param int                                 $user_id  The id of the local blog-user.
		 * @param string                              $type     The type of the activity.
		 * @param \Activitypub\Activity\Activity|null $activity The activity object.
		 */
		\do_action( 'activitypub_inbox', $object, $user_id, $type, $activity );

		/**
		 * Fires after an Announce of a specific type has been received.
		 *
		 * @param array                               $object   The object.
		 * @param int                                 $user_id  The id of the local blog-user.
		 * @param \Activitypub\Activity\Activity|null $activity The activity object.
		 */
		\do_action( "activitypub_inbox_{$type}", $object, $user_id, $activity );
	}

	/**
	 * Try to save the Announce.
	 *
	 * @param array $activity The activity-object.
	 * @param int   $user_id  The id of the local blog-user.
	 */
	public static function maybe_save_announce( $activity, $user_id ) {
		$url = object_to_uri( $activity );

		if ( empty( $url ) ) {
			return;
		}

		$exists = Comment::object_id_to_comment( esc_url_raw( $url ) );
		if ( $exists ) {
			return;
		}

		$success = false;
		$result  = Interactions::add_reaction( $activity );

		if ( $result && ! is_wp_error( $result ) ) {
			$success = true;
			$result  = get_comment( $result );
		}

		/**
		 * Fires after an ActivityPub Announce activity has been handled.
		 *
		 * @param array                            $activity The ActivityPub activity data.
		 * @param int                              $user_id  The local user ID.
		 * @param bool                             $success  True on success, false otherwise.
		 * @param array|string|int|\WP_Error|false $result   The WP_Comment object of the created announce/repost comment, or null if creation failed.
		 */
		\do_action( 'activitypub_handled_announce', $activity, $user_id, $success, $result );
	}
}
