<?php
namespace Activitypub;

use Activitypub\Model\Post;
use Activitypub\Model\Activity;
use Activitypub\Collection\Followers;

/**
 * ActivityPub Activity_Dispatcher Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/
 */
class Activity_Dispatcher {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// legacy
		\add_action( 'activitypub_send_post_activity', array( self::class, 'send_create_activity' ) );

		\add_action( 'activitypub_send_create_activity', array( self::class, 'send_create_activity' ) );
		\add_action( 'activitypub_send_update_activity', array( self::class, 'send_update_activity' ) );
		\add_action( 'activitypub_send_delete_activity', array( self::class, 'send_delete_activity' ) );
	}

	/**
	 * Send "create" activities.
	 *
	 * @param Activitypub\Model\Post $activitypub_post
	 */
	public static function send_create_activity( Post $activitypub_post ) {
		self::send_activity( $activitypub_post, 'Create' );
	}

	/**
	 * Send "update" activities.
	 *
	 * @param Activitypub\Model\Post $activitypub_post
	 */
	public static function send_update_activity( Post $activitypub_post ) {
		self::send_activity( $activitypub_post, 'Update' );
	}

	/**
	 * Send "delete" activities.
	 *
	 * @param Activitypub\Model\Post $activitypub_post
	 */
	public static function send_delete_activity( Post $activitypub_post ) {
		self::send_activity( $activitypub_post, 'Delete' );
	}

	/**
	 * Undocumented function
	 *
	 * @param Activitypub\Model\Post $activitypub_post
	 * @param [type] $activity_type
	 *
	 * @return void
	 */
	public static function send_activity( Post $activitypub_post, $activity_type ) {
		// get latest version of post
		$user_id = $activitypub_post->get_post_author();

		$activitypub_activity = new Activity( $activity_type );
		$activitypub_activity->from_post( $activitypub_post );

		$inboxes = FollowerCollection::get_inboxes( $user_id );

		foreach ( $inboxes as $inbox ) {
			$activity = $activitypub_activity->to_json();

			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}
}
