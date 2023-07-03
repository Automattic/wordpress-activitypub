<?php
namespace Activitypub;

use WP_Post;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Users;
use Activitypub\Collection\Followers;
use Activitypub\Transformer\Post;

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

		\add_action( 'activitypub_send_activity', array( self::class, 'send_user_activity' ), 10, 2 );
		\add_action( 'activitypub_send_activity', array( self::class, 'send_blog_activity' ), 10, 2 );
	}

	/**
	 * Send Activities to followers and mentioned users.
	 *
	 * @param WP_Post $post The ActivityPub Post.
	 * @param string  $type The Activity-Type.
	 *
	 * @return void
	 */
	public static function send_user_activity( WP_Post $post, $type ) {
		// check if a migration is needed before sending new posts
		Migration::maybe_migrate();

		$object = Post::transform( $post )->to_object();

		$activity = new Activity();
		$activity->set_type( $type );
		$activity->set_object( $object );

		$user_id           = $post->post_author;
		$follower_inboxes  = Followers::get_inboxes( $user_id );
		$mentioned_inboxes = Mention::get_inboxes( $activity->get_cc() );

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
	 * @param WP_Post $post The ActivityPub Post.
	 * @param string  $type The Activity-Type.
	 *
	 * @return void
	 */
	public static function send_blog_activity( WP_Post $post, $type ) {
		// check if a migration is needed before sending new posts
		Migration::maybe_migrate();

		$user = Users::get_by_id( Users::BLOG_USER_ID );

		$object = Post::transform( $post )->to_object();
		$object->set_attributed_to( $user->get_url() );

		$activity = new Activity();
		$activity->set_type( $type );
		$activity->set_object( $object );

		$follower_inboxes  = Followers::get_inboxes( $user->get__id() );
		$mentioned_inboxes = Mention::get_inboxes( $activity->get_cc() );

		$inboxes = array_merge( $follower_inboxes, $mentioned_inboxes );
		$inboxes = array_unique( $inboxes );

		$array = $activity->to_array();

		foreach ( $inboxes as $inbox ) {
			safe_remote_post( $inbox, $array, $user_id );
		}
	}
}
