<?php
namespace Activitypub;

use WP_Post;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Users;
use Activitypub\Collection\Followers;
use Activitypub\Transformer\Post;

use function Activitypub\is_user_disabled;
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
		\add_action( 'activitypub_send_activity', array( self::class, 'send_user_activity' ), 10, 2 );
		\add_action( 'activitypub_send_activity', array( self::class, 'send_blog_activity' ), 10, 2 );
	}

	/**
	 * Send Activities to followers and mentioned users.
	 *
	 * @param WP_Post $wp_post The ActivityPub Post.
	 * @param string  $activity_type    The Activity-Type.
	 *
	 * @return void
	 */
	public static function send_user_activity( WP_Post $wp_post, $activity_type ) {
		// check if a migration is needed before sending new posts
		Migration::maybe_migrate();

		if ( is_user_disabled( $wp_post->post_author ) ) {
			return;
		}

		$post = new Post( $wp_post );

		$activitypub_activity = new Activity( $activity_type );
		$activitypub_activity->from_post( $post );

		$user_id           = $wp_post->post_author;
		$follower_inboxes  = Followers::get_inboxes( $user_id );
		$mentioned_inboxes = Mention::get_inboxes( $activitypub_activity->get_cc() );

		$inboxes = array_merge( $follower_inboxes, $mentioned_inboxes );
		$inboxes = array_unique( $inboxes );

		$array = $activity->to_json();

		foreach ( $inboxes as $inbox ) {
			safe_remote_post( $inbox, $array, $user_id );
		}
	}

	/**
	 * Send Activities to followers and mentioned users.
	 *
	 * @param WP_Post $wp_post The ActivityPub Post.
	 * @param string  $activity_type    The Activity-Type.
	 *
	 * @return void
	 */
	public static function send_blog_activity( WP_Post $wp_post, $activity_type ) {
		// check if a migration is needed before sending new posts
		Migration::maybe_migrate();

		if ( is_user_disabled( User_Factory::BLOG_USER_ID ) ) {
			return;
		}

		$post = new Post( $wp_post, User_Factory::BLOG_USER_ID );

		$activitypub_activity = new Activity( $activity_type );
		$activitypub_activity->from_post( $post );

		$user_id           = User_Factory::BLOG_USER_ID;
		$follower_inboxes  = Followers::get_inboxes( $user_id );
		$mentioned_inboxes = Mention::get_inboxes( $activitypub_activity->get_cc() );

		$inboxes = array_merge( $follower_inboxes, $mentioned_inboxes );
		$inboxes = array_unique( $inboxes );

		$array = $activity->to_json();

		foreach ( $inboxes as $inbox ) {
			safe_remote_post( $inbox, $array, $user_id );
		}
	}
}
