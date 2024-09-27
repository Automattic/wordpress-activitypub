<?php
namespace Activitypub;

use WP_Post;
use WP_Comment;
use Activitypub\Http;
use Activitypub\Collection\Users;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Followers;
use Activitypub\Transformer\Factory;
use Activitypub\Transformer\Post;
use Activitypub\Transformer\Comment;

use function Activitypub\object_to_uri;
use function Activitypub\is_single_user;
use function Activitypub\is_user_disabled;
use function Activitypub\safe_remote_post;
use function Activitypub\set_wp_object_state;

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
		\add_action( 'activitypub_send_post', array( self::class, 'send_post' ), 10, 2 );
		\add_action( 'activitypub_send_comment', array( self::class, 'send_comment' ), 10, 2 );

		\add_action( 'activitypub_send_activity', array( self::class, 'send_activity' ), 10, 2 );
		\add_action( 'activitypub_send_activity', array( self::class, 'send_activity_or_announce' ), 10, 2 );
		\add_action( 'activitypub_send_update_profile_activity', array( self::class, 'send_profile_update' ), 10, 1 );

		// Default filters to add Inboxes to sent to
		\add_filter( 'activitypub_send_to_inboxes', array( self::class, 'add_inboxes_of_follower' ), 10, 2 );
		\add_filter( 'activitypub_send_to_inboxes', array( self::class, 'add_inboxes_by_mentioned_actors' ), 10, 3 );
		\add_filter( 'activitypub_send_to_inboxes', array( self::class, 'add_inboxes_of_replied_urls' ), 10, 3 );
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
		$transformer = Factory::get_transformer( $wp_object ); // Could potentially return a `\WP_Error` instance.

		if ( \is_wp_error( $transformer ) ) {
			return;
		}

		if ( null !== $user_id ) {
			$transformer->change_wp_user_id( $user_id );
		}

		$user_id = $transformer->get_wp_user_id();

		if ( is_user_disabled( $user_id ) ) {
			return;
		}

		$activity = $transformer->to_activity( $type );

		self::send_activity_to_followers( $activity, $user_id, $wp_object );
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
		if ( ! in_array( $type, array( 'Create', 'Update', 'Delete' ), true ) ) {
			return;
		}

		if ( is_user_disabled( Users::BLOG_USER_ID ) ) {
			return;
		}

		$transformer = Factory::get_transformer( $wp_object );

		if ( \is_wp_error( $transformer ) ) {
			return;
		}

		$user_id  = Users::BLOG_USER_ID;
		$activity = $transformer->to_activity( $type );
		$user     = Users::get_by_id( Users::BLOG_USER_ID );

		$announce = new Activity();
		$announce->set_type( 'Announce' );
		$announce->set_object( $activity );
		$announce->set_actor( $user->get_id() );

		self::send_activity_to_followers( $announce, $user_id, $wp_object );
	}

	/**
	 * Send a "Update" Activity when a user updates their profile.
	 *
	 * @param int $user_id The user ID to send an update for.
	 *
	 * @return void
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
		self::send_activity_to_followers( $activity, $user_id, $user );
	}

	/**
	 * Send an Activity to all followers and mentioned users.
	 *
	 * @param Activity                   $activity  The ActivityPub Activity.
	 * @param int                        $user_id   The user ID.
	 * @param WP_User|WP_Post|WP_Comment $wp_object The WordPress object.
	 *
	 * @return void
	 */
	private static function send_activity_to_followers( $activity, $user_id, $wp_object ) {
		// check if the Activity should be send to the followers
		if ( ! apply_filters( 'activitypub_send_activity_to_followers', true, $activity, $user_id, $wp_object ) ) {
			return;
		}

		$inboxes = apply_filters( 'activitypub_send_to_inboxes', array(), $user_id, $activity );
		$inboxes = array_unique( $inboxes );

		if ( empty( $inboxes ) ) {
			return;
		}

		$json = $activity->to_json();

		foreach ( $inboxes as $inbox ) {
			safe_remote_post( $inbox, $json, $user_id );
		}

		set_wp_object_state( $wp_object, 'federated' );
	}

	/**
	 * Send a "Create" or "Update" Activity for a WordPress Post.
	 *
	 * @param int    $id   The WordPress Post ID.
	 * @param string $type The Activity-Type.
	 *
	 * @return void
	 */
	public static function send_post( $id, $type ) {
		$post = get_post( $id );

		if ( ! $post ) {
			return;
		}

		do_action( 'activitypub_send_activity', $post, $type );
		do_action(
			sprintf(
				'activitypub_send_%s_activity',
				\strtolower( $type )
			),
			$post
		);
	}

	/**
	 * Send a "Create" or "Update" Activity for a WordPress Comment.
	 *
	 * @param int    $id   The WordPress Comment ID.
	 * @param string $type The Activity-Type.
	 *
	 * @return void
	 */
	public static function send_comment( $id, $type ) {
		$comment = get_comment( $id );

		if ( ! $comment ) {
			return;
		}

		do_action( 'activitypub_send_activity', $comment, $type );
		do_action(
			sprintf(
				'activitypub_send_%s_activity',
				\strtolower( $type )
			),
			$comment
		);
	}

	/**
	 * Default filter to add Inboxes of Followers
	 *
	 * @param array $inboxes  The list of Inboxes
	 * @param int   $user_id  The WordPress User-ID
	 *
	 * @return array The filtered Inboxes
	 */
	public static function add_inboxes_of_follower( $inboxes, $user_id ) {
		$follower_inboxes = Followers::get_inboxes( $user_id );

		return array_merge( $inboxes, $follower_inboxes );
	}

	/**
	 * Default filter to add Inboxes of Mentioned Actors
	 *
	 * @param array $inboxes  The list of Inboxes
	 * @param int   $user_id  The WordPress User-ID
	 * @param array $activity The ActivityPub Activity
	 *
	 * @return array The filtered Inboxes
	 */
	public static function add_inboxes_by_mentioned_actors( $inboxes, $user_id, $activity ) {
		$cc = $activity->get_cc();
		if ( $cc ) {
			$mentioned_inboxes = Mention::get_inboxes( $cc );

			return array_merge( $inboxes, $mentioned_inboxes );
		}

		return $inboxes;
	}

	/**
	 * Default filter to add Inboxes of Posts that are set as `in-reply-to`
	 *
	 * @param array $inboxes  The list of Inboxes
	 * @param int   $user_id  The WordPress User-ID
	 * @param array $activity The ActivityPub Activity
	 *
	 * @return array The filtered Inboxes
	 */
	public static function add_inboxes_of_replied_urls( $inboxes, $user_id, $activity ) {
		$in_reply_to = $activity->get_in_reply_to();

		if ( ! $in_reply_to ) {
			return $inboxes;
		}

		if ( ! is_array( $in_reply_to ) ) {
			$in_reply_to = array( $in_reply_to );
		}

		foreach ( $in_reply_to as $url ) {
			$object = Http::get_remote_object( $url );

			if ( ! $object || empty( $object['attributedTo'] ) ) {
				continue;
			}

			$actor = object_to_uri( $object['attributedTo'] );
			$actor = Http::get_remote_object( $actor );

			if ( ! $actor ) {
				continue;
			}

			if ( ! empty( $actor['endpoints']['sharedInbox'] ) ) {
				$inboxes[] = $actor['endpoints']['sharedInbox'];
			} elseif ( ! empty( $actor['inbox'] ) ) {
				$inboxes[] = $actor['inbox'];
			}
		}

		return $inboxes;
	}
}
