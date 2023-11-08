<?php
namespace Activitypub;

use WP_Post;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Users;
use Activitypub\Collection\Followers;
use Activitypub\Transformer\Post;

use function Activitypub\is_single_user;
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
		\add_action( 'activitypub_send_activity', array( self::class, 'send_activity' ), 10, 2 );
		\add_action( 'activitypub_send_activity', array( self::class, 'send_activity_or_announce' ), 10, 2 );
		\add_action( 'activitypub_send_update_profile_activity', array( self::class, 'send_profile_update' ), 10, 1 );
		\add_action( 'activitypub_send_server_activity', array( self::class, 'send_server_activity' ), 10, 1 );
	}

	/**
	 * Send Activities to followers and mentioned users or `Announce` (boost) a blog post.
	 *
	 * @param WP_Post $wp_post The ActivityPub Post.
	 * @param string  $type    The Activity-Type.
	 *
	 * @return void
	 */
	public static function send_activity_or_announce( WP_Post $wp_post, $type ) {
		// check if a migration is needed before sending new posts
		Migration::maybe_migrate();

		if ( is_user_type_disabled( 'blog' ) ) {
			return;
		}

		$wp_post->post_author = Users::BLOG_USER_ID;

		if ( is_single_user() ) {
			self::send_activity( $wp_post, $type );
		} else {
			self::send_announce( $wp_post, $type );
		}
	}

	/**
	 * Send Activities to followers and mentioned users.
	 *
	 * @param WP_Post $wp_post The ActivityPub Post.
	 * @param string  $type    The Activity-Type.
	 *
	 * @return void
	 */
	public static function send_activity( WP_Post $wp_post, $type ) {
		if ( is_user_disabled( $wp_post->post_author ) ) {
			return;
		}

		$object = Post::transform( $wp_post )->to_object();

		$activity = new Activity();
		$activity->set_type( $type );
		$activity->set_object( $object );

		self::send_activity_to_inboxes( $activity, $wp_post->post_author );
	}

	/**
	 * Send Announces to followers and mentioned users.
	 *
	 * @param WP_Post $wp_post The ActivityPub Post.
	 * @param string  $type    The Activity-Type.
	 *
	 * @return void
	 */
	public static function send_announce( WP_Post $wp_post, $type ) {
		if ( ! in_array( $type, array( 'Create', 'Update' ), true ) ) {
			return;
		}

		if ( is_user_disabled( Users::BLOG_USER_ID ) ) {
			return;
		}

		$object = Post::transform( $wp_post )->to_object();

		$activity = new Activity();
		$activity->set_type( 'Announce' );
		// to pre-fill attributes like "published" and "id"
		$activity->set_object( $object );
		// send only the id
		$activity->set_object( $object->get_id() );

		self::send_activity_to_inboxes( $activity, $wp_post->post_author );
	}

	/**
	 * Send a "Update" Activity when a user updates their profile.
	 *
	 * @param int $user_id The user ID to send an update for.
	 *
	 */
	public static function send_profile_update( $user_id ) {
		$user = Users::get_by_various( $user_id );

		// bail if that's not a good user
		if ( is_wp_error( $user ) ) {
			return;
		}

		// build the update
		$activity = new Activity();
		$activity->set_id( $user->get_url() . '#update' );
		$activity->set_type( 'Update' );
		$activity->set_actor( $user->get_url() );
		$activity->set_object( $user->get_url() );
		$activity->set_to( 'https://www.w3.org/ns/activitystreams#Public' );

		// send the update
		self::send_activity_to_inboxes( $activity, $user_id );
	}

	/**
	 * Send an Activity to all followers and mentioned users.
	 *
	 * @param Activity $activity The ActivityPub Activity.
	 * @param int      $user_id  The user ID.
	 *
	 * @return void
	 */
	private static function send_activity_to_inboxes( $activity, $user_id ) {
		$follower_inboxes  = Followers::get_inboxes( $user_id );

		$mentioned_inboxes = array();
		$cc = $activity->get_cc();
		if ( $cc ) {
			$mentioned_inboxes = Mention::get_inboxes( $cc );
		}

		$inboxes = array_merge( $follower_inboxes, $mentioned_inboxes );
		$inboxes = array_unique( $inboxes );

		if ( empty( $inboxes ) ) {
			return;
		}

		$json = $activity->to_json();

		foreach ( $inboxes as $inbox ) {
			safe_remote_post( $inbox, $json, $user_id );
		}
	}

	/**
	 * Send an Activity to all known (shared_)inboxes.
	 *
	 * @param Activity $activity The ActivityPub Activity.
	 *
	 * @return void
	 */
	private static function send_server_activity( $activity ) {
		$json = $activity->to_json();
		$inboxes = Server::known_inboxes();
		foreach ( $inboxes as $inbox ) {
			safe_remote_post( $inbox, $json, -1 );
		}
	}
}
