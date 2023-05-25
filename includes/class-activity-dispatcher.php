<?php
namespace Activitypub;

use Activitypub\Model\Post;
use Activitypub\Model\Activity;
use Activitypub\User_Factory;
use Activitypub\Collection\Followers;

use function Activitypub\safe_remote_post;

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
	 * @param Activitypub\Model\Post $activitypub_post The ActivityPub Post.
	 */
	public static function send_update_activity( Post $activitypub_post ) {
		self::send_activity( $activitypub_post, 'Update' );
	}

	/**
	 * Send "delete" activities.
	 *
	 * @param Activitypub\Model\Post $activitypub_post The ActivityPub Post.
	 */
	public static function send_delete_activity( Post $activitypub_post ) {
		self::send_activity( $activitypub_post, 'Delete' );
	}

	/**
	 * Send Activities to followers and mentioned users.
	 *
	 * @param Activitypub\Model\Post $activitypub_post The ActivityPub Post.
	 * @param string                 $activity_type    The Activity-Type.
	 *
	 * @return void
	 */
	public static function send_activity( Post $activitypub_post, $activity_type ) {
		// check if a migration is needed before sending new posts
		Migration::maybe_migrate();

		if ( ! is_single_user_mode() ) {
			// send User-Activity
			self::send_user_activity( $activitypub_post, $activity_type );
		}

		// send Blog-User-Activity
		self::send_user_activity( $activitypub_post, $activity_type, User_Factory::BLOG_USER_ID );
	}

	public static function send_user_activity( Post $activitypub_post, $activity_type, $user_id = null ) {
		if ( $user_id ) {
			$user  = User_Factory::get_by_id( $user_id );
			$actor = $user->get_url();
		} else {
			// get latest version of post
			$user_id = $activitypub_post->get_post_author();
			$actor   = null;
		}

		$activitypub_activity = new Activity( $activity_type );
		$activitypub_activity->from_post( $activitypub_post );

		if ( $actor ) {
			$activitypub_activity->set_actor( $actor );
		}

		$follower_inboxes = Followers::get_inboxes( $user_id );
		$mentioned_inboxes = Mention::get_inboxes( $activitypub_activity->get_cc() );

		$inboxes = array_merge( $follower_inboxes, $mentioned_inboxes );
		$inboxes = array_unique( $inboxes );

		foreach ( $inboxes as $inbox ) {
			$activity = $activitypub_activity->to_json();

			safe_remote_post( $inbox, $activity, $user_id );
		}
	}
}
