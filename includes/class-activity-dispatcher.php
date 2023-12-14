<?php
namespace Activitypub;

use WP_Post;
use WP_Comment;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Users;
use Activitypub\Collection\Followers;
use Activitypub\Transformer\Factory;
use Activitypub\Transformer\Post;
use Activitypub\Transformer\Comment;

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
	}

	/**
	 * Send Activities to followers and mentioned users or `Announce` (boost) a blog post.
	 *
	 * @param mixed  $wp_object The ActivityPub Post.
	 * @param string $type      The Activity-Type.
	 *
	 * @return void
	 */
	public static function send_activity_or_announce( $wp_object, $type ) {
		// check if a migration is needed before sending new posts
		Migration::maybe_migrate();

		if ( is_user_type_disabled( 'blog' ) ) {
			return;
		}

		if ( is_single_user() ) {
			self::send_activity( $wp_object, $type, Users::BLOG_USER_ID );
		} else {
			self::send_announce( $wp_object, $type );
		}
	}

	/**
	 * Send Activities to followers and mentioned users.
	 *
	 * @param mixed  $wp_object The ActivityPub Post.
	 * @param string $type      The Activity-Type.
	 *
	 * @return void
	 */
	public static function send_activity( $wp_object, $type, $user_id = null ) {
		$transformer = Factory::get_transformer( $wp_object );

		if ( null !== $user_id ) {
			$transformer->change_wp_user_id( $user_id );
		}

		$user_id = $transformer->get_wp_user_id();

		if ( is_user_disabled( $user_id ) ) {
			return;
		}

		$activity = $transformer->to_activity( 'Create' );

		$follower_inboxes  = Followers::get_inboxes( $user_id );
		$mentioned_inboxes = Mention::get_inboxes( $activity->get_cc() );

		$inboxes = array_merge( $follower_inboxes, $mentioned_inboxes );
		$inboxes = array_unique( $inboxes );

		$json = $activity->to_json();

		foreach ( $inboxes as $inbox ) {
			safe_remote_post( $inbox, $json, $user_id );
		}
	}

	/**
	 * Send Announces to followers and mentioned users.
	 *
	 * @param mixed  $wp_object The ActivityPub Post.
	 * @param string $type      The Activity-Type.
	 *
	 * @return void
	 */
	public static function send_announce( $wp_object, $type ) {
		if ( ! in_array( $type, array( 'Create', 'Update' ), true ) ) {
			return;
		}

		if ( is_user_disabled( Users::BLOG_USER_ID ) ) {
			return;
		}

		$transformer = Factory::get_transformer( $wp_object );
		$transformer->change_wp_user_id( Users::BLOG_USER_ID );

		$user_id = $transformer->get_wp_user_id();
		$object  = $transformer->to_object();

		$activity = new Activity();
		$activity->set_type( 'Announce' );
		// to pre-fill attributes like "published" and "id"
		$activity->set_object( $object );
		// send only the id
		$activity->set_object( $object->get_id() );

		$follower_inboxes  = Followers::get_inboxes( $user_id );
		$mentioned_inboxes = Mention::get_inboxes( $activity->get_cc() );

		$inboxes = array_merge( $follower_inboxes, $mentioned_inboxes );
		$inboxes = array_unique( $inboxes );

		$json = $activity->to_json();

		foreach ( $inboxes as $inbox ) {
			safe_remote_post( $inbox, $json, $user_id );
		}
	}
}
