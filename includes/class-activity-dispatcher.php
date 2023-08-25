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

		$follower_inboxes  = Followers::get_inboxes( $wp_post->post_author );
		$mentioned_inboxes = Mention::get_inboxes( $activity->get_cc() );

		$inboxes = array_merge( $follower_inboxes, $mentioned_inboxes );
		$inboxes = array_unique( $inboxes );

		$json = $activity->to_json();

		foreach ( $inboxes as $inbox ) {
			safe_remote_post( $inbox, $json, $wp_post->post_author );
		}
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

		$follower_inboxes  = Followers::get_inboxes( $wp_post->post_author );
		$mentioned_inboxes = Mention::get_inboxes( $activity->get_cc() );

		$inboxes = array_merge( $follower_inboxes, $mentioned_inboxes );
		$inboxes = array_unique( $inboxes );

		$json = $activity->to_json();

		foreach ( $inboxes as $inbox ) {
			safe_remote_post( $inbox, $json, $wp_post->post_author );
		}
	}

	/**
	 * Send "delete" activities.
	 *
	 * @param str $activitypub_url
	 * @param int $user_id
	 */
	public static function send_delete_url_activity( $activitypub_url, $user_id ) {
		// get latest version of post
		$actor = \get_author_posts_url( $user_id );
		$deleted = \current_time( 'Y-m-d\TH:i:s\Z', true );

		$activitypub_activity = new \Activitypub\Model\Activity( 'Delete', \Activitypub\Model\Activity::TYPE_SIMPLE );
		$activitypub_activity->set_id( $activitypub_url . '#delete' );
		$activitypub_activity->set_actor( $actor );
		$activitypub_activity->set_object(
			array(
				'id' => $activitypub_url,
				'type' => 'Tombstone',
			)
		);
		$activitypub_activity->set_deleted( $deleted );

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json(); // phpcs:ignore
			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}

	/**
	 * Send "create" activities for comments
	 *
	 * @param \Activitypub\Model\Comment $activitypub_comment
	 */
	public static function send_comment_activity( $activitypub_comment_id ) {
		//ONLY FOR LOCAL USERS ?
		$activitypub_comment = \get_comment( $activitypub_comment_id );
		$user_id = $activitypub_comment->user_id;
		$activitypub_comment = new \Activitypub\Model\Comment( $activitypub_comment );
		$activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_comment( $activitypub_comment->to_array() );

		$inboxes = \Activitypub\get_follower_inboxes( $user_id );

		$followers_url = \get_rest_url( null, '/activitypub/1.0/users/' . intval( $user_id ) . '/followers' );
		foreach ( $activitypub_activity->get_cc() as $cc ) {
			if ( $cc === $followers_url ) {
				continue;
			}
			$inbox = \Activitypub\get_inbox_by_actor( $cc );
			if ( ! $inbox || \is_wp_error( $inbox ) ) {
				continue;
			}
			// init array if empty
			if ( ! isset( $inboxes[ $inbox ] ) ) {
				$inboxes[ $inbox ] = array();
			}
			$inboxes[ $inbox ][] = $cc;
		}

		foreach ( $inboxes as $inbox => $to ) {
			$to = array_values( array_unique( $to ) );
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json();
			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}

	/**
	 * Forward replies to followers
	 *
	 * @param \Activitypub\Model\Comment $activitypub_comment
	 */
	public static function inbox_forward_activity( $activitypub_comment_id ) {
		$activitypub_comment = \get_comment( $activitypub_comment_id );

		//original author should NOT recieve a copy of their own post
		$replyto[] = $activitypub_comment->comment_author_url;
		$activitypub_activity = \unserialize( get_comment_meta( $activitypub_comment->comment_ID, 'ap_object', true ) );

		//will be forwarded to the parent_comment->author or post_author followers collection
		$parent_comment = \get_comment( $activitypub_comment->comment_parent );
		if ( ! is_null( $parent_comment ) ) {
			$user_id = $parent_comment->user_id;
		} else {
			$original_post = \get_post( $activitypub_comment->comment_post_ID );
			$user_id = $original_post->post_author;
		}

		unset( $activitypub_activity['user_id'] ); // remove user_id from $activitypub_comment

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $cc ) {
			//Forward reply to followers, skip sender
			if ( in_array( $cc, $replyto ) ) {
				continue;
			}

			$activitypub_activity['object']['cc'] = $cc;
			$activitypub_activity['cc'] = $cc;

			$activity = \wp_json_encode( $activitypub_activity, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT );
			\Activitypub\forward_remote_post( $inbox, $activity, $user_id );
		}
	}

	/**
	 * Send "update" activities.
	 *
	 * @param \Activitypub\Model\Comment $activitypub_comment
	 */
	public static function send_update_comment_activity( $activitypub_comment_id ) {
		$activitypub_comment = \get_comment( $activitypub_comment_id );
		$updated = \get_comment_meta( $activitypub_comment_id, 'ap_last_modified', true );

		$user_id = $activitypub_comment->user_id;
		if ( ! $user_id ) { // Prevent sending received/anonymous comments.
			return;
		}
		$activitypub_comment = new \Activitypub\Model\Comment( $activitypub_comment );
		$activitypub_comment->set_update( $updated );
		$activitypub_activity = new \Activitypub\Model\Activity( 'Update', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_comment( $activitypub_comment->to_array() );
		$activitypub_activity->set_update( $updated );

		$inboxes = \Activitypub\get_follower_inboxes( $user_id );
		$followers_url = \get_rest_url( null, '/activitypub/1.0/users/' . intval( $user_id ) . '/followers' );

		foreach ( $activitypub_activity->get_cc() as $cc ) {
			if ( $cc === $followers_url ) {
				continue;
			}
			$inbox = \Activitypub\get_inbox_by_actor( $cc );
			if ( ! $inbox || \is_wp_error( $inbox ) ) {
				continue;
			}
			// init array if empty
			if ( ! isset( $inboxes[ $inbox ] ) ) {
				$inboxes[ $inbox ] = array();
			}
			$inboxes[ $inbox ][] = $cc;
		}

		foreach ( $inboxes as $inbox => $to ) {
			$to = array_values( array_unique( $to ) );
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json();
			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}

	/**
	 * Send "delete" activities.
	 *
	 * @param \Activitypub\Model\Comment $activitypub_comment
	 */
	public static function send_delete_comment_activity( $activitypub_comment_id ) {
		// get comment
		$activitypub_comment = \get_comment( $activitypub_comment_id );
		$user_id = $activitypub_comment->user_id;
		// Prevent sending received/anonymous comments
		if ( ! $user_id ) {
			return;
		}

		$deleted = \wp_date( 'Y-m-d\TH:i:s\Z', \strtotime( $activitypub_comment->comment_date_gmt ) );

		$activitypub_comment = new \Activitypub\Model\Comment( $activitypub_comment );
		$activitypub_comment->set_deleted( $deleted );
		$activitypub_activity = new \Activitypub\Model\Activity( 'Delete', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_comment( $activitypub_comment->to_array() );
		$activitypub_activity->set_deleted( $deleted );

		$inboxes = \Activitypub\get_follower_inboxes( $user_id );
		$followers_url = \get_rest_url( null, '/activitypub/1.0/users/' . intval( $user_id ) . '/followers' );

		foreach ( $activitypub_activity->get_cc() as $cc ) {
			if ( $cc === $followers_url ) {
				continue;
			}
			$inbox = \Activitypub\get_inbox_by_actor( $cc );
			if ( ! $inbox || \is_wp_error( $inbox ) ) {
				continue;
			}
			// init array if empty
			if ( ! isset( $inboxes[ $inbox ] ) ) {
				$inboxes[ $inbox ] = array();
			}
			$inboxes[ $inbox ][] = $cc;
		}

		foreach ( $inboxes as $inbox => $to ) {
			$to = array_values( array_unique( $to ) );
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json();
			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}
}
