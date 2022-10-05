<?php
namespace Activitypub;

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
		\add_action( 'activitypub_send_post_activity', array( '\Activitypub\Activity_Dispatcher', 'send_post_activity' ) );
		\add_action( 'activitypub_send_announce_activity', array( '\Activitypub\Activity_Dispatcher', 'send_announce_activity' ), 10, 2 );
		\add_action( 'activitypub_send_update_activity', array( '\Activitypub\Activity_Dispatcher', 'send_update_activity' ) );
		\add_action( 'activitypub_send_delete_activity', array( '\Activitypub\Activity_Dispatcher', 'send_delete_activity' ) );
		\add_action( 'activitypub_send_delete_url_activity', array( '\Activitypub\Activity_Dispatcher', 'send_delete_url_activity' ), 10, 2 );

		\add_action( 'activitypub_send_comment_activity', array( '\Activitypub\Activity_Dispatcher', 'send_comment_activity' ) );
		\add_action( 'activitypub_send_update_comment_activity', array( '\Activitypub\Activity_Dispatcher', 'send_update_comment_activity' ) );
		\add_action( 'activitypub_inbox_forward_activity', array( '\Activitypub\Activity_Dispatcher', 'inbox_forward_activity' ) );
		\add_action( 'activitypub_send_delete_comment_activity', array( '\Activitypub\Activity_Dispatcher', 'send_delete_comment_activity' ) );
	}

	/**
	 * Send "create" activities.
	 *
	 * @param \Activitypub\Model\Post $activitypub_post
	 */
	public static function send_post_activity( $activitypub_post ) {
		// get latest version of post
		$user_id = $activitypub_post->get_post_author();

		$activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_post( $activitypub_post->to_array() );

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json(); // phpcs:ignore

			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}

	/**
	 * Send "announce" activities.
	 *
	 * @param str $activitypub_url (ActivityPub object ID)
	 * @param absint $user_id 
	 */
	public static function send_announce_activity( $activitypub_url, $user_id ) {
		// get latest version of post
		$time = \current_datetime()->format( DATE_ISO8601 );
		$post = \get_post( \url_to_postid( $activitypub_url ) );

		$activitypub_announce = new \Activitypub\Model\Activity( 'Announce', \Activitypub\Model\Activity::TYPE_SIMPLE );
		$activitypub_announce->set_published( $time );

		if ( $post ) {
			$activitypub_id = $post->guid;
			$activitypub_post = new \Activitypub\Model\Post( $post );
			$activitypub_announce->from_post( $activitypub_post->to_array() );
		} else {
			$activitypub_id = $activitypub_url;
			$activitypub_announce->set_object( $activitypub_id );
		}
		$activitypub_announce->set_id( add_query_arg( 'activity', 'announce', $activitypub_id ) );
		$activitypub_announce->set_actor( \get_author_posts_url( $user_id ) );

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			$activitypub_announce->set_to( $to );
			$activity = $activitypub_announce->to_json(); // phpcs:ignore
			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}

	/**
	 * Send "update" activities.
	 *
	 * @param \Activitypub\Model\Post $activitypub_post
	 */
	public static function send_update_activity( $activitypub_post ) {
		// get latest version of post
		$user_id = $activitypub_post->get_post_author();
		$updated = \wp_date( 'Y-m-d\TH:i:s\Z', \strtotime( $activitypub_post->get_updated() ) );

		$activitypub_activity = new \Activitypub\Model\Activity( 'Update', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_post( $activitypub_post->to_array() );

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json(); // phpcs:ignore

			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}

	/**
	 * Send "delete" activities.
	 *
	 * @param str $activitypub_url
	 * @param int $user_id
	 */
	public static function send_delete_activity( $activitypub_post ) {
		// get latest version of post
		$deleted = \current_time( 'Y-m-d\TH:i:s\Z', true );
		$activitypub_post->set_deleted( $deleted );

		$activitypub_activity = new \Activitypub\Model\Activity( 'Delete', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_post( $activitypub_post->to_array() );
		$activitypub_activity->set_deleted( $deleted );

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json(); // phpcs:ignore

			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
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
		$mentions[] = \get_comment_meta( $activitypub_comment_id, 'mentions', true );// mention[href, name]

		$activitypub_comment = new \Activitypub\Model\Comment( $activitypub_comment );
		$activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_comment( $activitypub_comment->to_array() );

		$mentioned_actors = array();
		foreach ( \Activitypub\get_mentioned_inboxes( $mentions ) as $inbox => $to ) {
			$activitypub_activity->set_to( $to );//all users at shared inbox
			$activity = $activitypub_activity->to_json(); // phpcs:ignore
			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );

			$mentioned_actors[] = $to;
		}

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $cc ) {
			$activitypub_activity->set_cc( $cc );//set_cc
			$activity = $activitypub_activity->to_json(); // phpcs:ignore

			// Send reply to followers, skip if mentioned followers (avoid duplicate replies)
			if ( in_array( $cc, $mentioned_actors ) ) {
				continue;
			}
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

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json(); // phpcs:ignore
			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}
}
