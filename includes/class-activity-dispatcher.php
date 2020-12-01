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
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'activitypub_send_post_activity', array( '\Activitypub\Activity_Dispatcher', 'send_post_activity' ) );
		\add_action( 'activitypub_send_update_activity', array( '\Activitypub\Activity_Dispatcher', 'send_update_activity' ) );
		\add_action( 'activitypub_send_delete_activity', array( '\Activitypub\Activity_Dispatcher', 'send_delete_activity' ) );
		\add_action( 'activitypub_send_comment_activity', array( '\Activitypub\Activity_Dispatcher', 'send_comment_activity' ) );
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
	 * Send "update" activities.
	 *
	 * @param \Activitypub\Model\Post $activitypub_post
	 */
	public static function send_update_activity( $activitypub_post ) {
		// get latest version of post
		$user_id = $activitypub_post->post_author;

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
	 * @param \Activitypub\Model\Post $activitypub_post
	 */
	public static function send_delete_activity( $activitypub_post ) {
		// get latest version of post
		$user_id = $activitypub_post->post_author;

		$activitypub_activity = new \Activitypub\Model\Activity( 'Delete', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_post( $activitypub_post->to_array() );

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
		$user_id =  $activitypub_comment->user_id;
		$replyto = get_comment_meta( $activitypub_comment->comment_parent, 'comment_author_url', true );//

		//error_log( 'dispatcher:send_comment:$activitypub_comment: ' . print_r( $activitypub_comment, true ) );

		$activitypub_comment = new \Activitypub\Model\Comment( $activitypub_comment );
		$activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_comment( $activitypub_comment->to_array() );

		\error_log( 'Activity_Dispatcher::send_comment_activity: ' . print_r($activitypub_activity, true));
		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			\error_log( '$user_id: ' . $user_id . ', $inbox: '. $inbox . ', $to: '. print_r($to, true ) );			
			$activitypub_activity->set_to( $to[0] );
			$activity = $activitypub_activity->to_json(); // phpcs:ignore

			// Send reply to followers, skip if replying to followers (avoid duplicate replies)
			// if( in_array( $to, $replyto ) || ( $replyto == $to ) ) {
			// 	break;
			// }
			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
		// TODO: Reply (to followers and non-followers)
		// if( is_array( $replyto ) && count( $replyto ) > 1 ) {
		// 	foreach ( $replyto as $to ) {
		// 		$inbox = \Activitypub\get_inbox_by_actor( $to );
		// 		$activitypub_activity->set_to( $to );
		// 		$activity = $activitypub_activity->to_json(); // phpcs:ignore
		// 		error_log( 'dispatches->replyto: ' . $to );
		// 		\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		// 	}
		// } elseif ( !is_array( $replyto ) ) {
		//  $inbox = \Activitypub\get_inbox_by_actor( $to );
		// 	$activitypub_activity->set_to( $replyto );
		// 	$activity = $activitypub_activity->to_json(); // phpcs:ignore
		// 	error_log( 'dispatch->replyto: ' . $replyto );
		// 	\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		// }

	}

	/**
	 * Forward replies to followers
	 *
	 * @param \Activitypub\Model\Comment $activitypub_comment
	 */
	public static function inbox_forward_activity( $activitypub_comment_id ) {
		//\error_log( 'Activity_Dispatcher::inbox_forward_activity' . print_r( $activitypub_comment, true ) );
		$activitypub_comment = \get_comment( $activitypub_comment_id );

		//original author should NOT recieve a copy of ther own post
		$replyto[] = $activitypub_comment->comment_author_url;
		$activitypub_activity = unserialize( get_comment_meta( $activitypub_comment->comment_ID, 'ap_object', true ) );
		
		//will be forwarded to the parent_comment->author or post_author followers collection
		//TODO verify that ... what?
		$parent_comment = \get_comment( $activitypub_comment->comment_parent );
		if ( !is_null( $parent_comment ) ) {
			$user_id = $parent_comment->user_id;
		} else {
			$original_post = \get_post( $activitypub_comment->comment_post_ID ); 
			$user_id = $original_post->post_author;
		}

		//remove user_id from $activitypub_comment
		unset($activitypub_activity['user_id']);
		
		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			\error_log( '$user_id: ' . $user_id . ', $inbox: '. $inbox . ', $to: '. print_r($to, true ) );			
			
			//Forward reply to followers, skip sender
			if( in_array( $to, $replyto ) || ( $replyto == $to ) ) {
				error_log( 'dispatch:forward: nope:' . print_r( $to, true ) );
				break;
			}

			$activitypub_activity['object']['to'] = $to;
			$activitypub_activity['to'] = $to;

			//$activitypub_activity
			//$activitypub_activity->set_to( $to );
			//$activity = $activitypub_activity->to_json(); // phpcs:ignore

			$activity = \wp_json_encode( $activitypub_activity, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT );
			error_log( 'dispatch:forward:activity:' . print_r( $activity, true ) );
			\Activitypub\forward_remote_post( $inbox, $activity, $user_id );
			
			//reset //unnecessary
			//array_pop( $activitypub_activity->object->to[] );
			//array_pop( $activitypub_activity->to[] );
		}
	}

	/**
	 * Send "delete" activities.
	 *
	 * @param \Activitypub\Model\Comment $activitypub_comment
	 */
	public static function send_delete_comment_activity( $activitypub_comment_id ) {
		// get latest version of post
		$activitypub_comment = \get_comment( $activitypub_comment_id );
		$user_id = $activitypub_comment->post_author;
		error_log( 'dispatch:send_delete_comment_activity: $user_id :' . print_r( $user_id , true ) );
		
		$activitypub_comment = new \Activitypub\Model\Comment( $activitypub_comment );
		$activitypub_activity = new \Activitypub\Model\Activity( 'Delete', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_comment( $activitypub_comment->to_array() );

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json(); // phpcs:ignore

			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}
}
