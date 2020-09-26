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
	}

	/**
	 * Send "create" activities
	 *
	 * @param int $post_id
	 */
	public static function send_post_activity( $post_id ) {
		$post = \get_post( $post_id );
		$user_id = $post->post_author;

		$activitypub_post = new \Activitypub\Model\Post( $post );
		$activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_post( $activitypub_post->to_array() );

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json(); // phpcs:ignore
\error_log( 'send_post_activity: ' . print_r( $activitypub_activity, true ));
			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}

	/**
	 * Send "update" activities
	 *
	 * @param int $post_id
	 */
	public static function send_update_activity( $post_id ) {
		$post = \get_post( $post_id );
		$user_id = $post->post_author;

		$activitypub_post = new \Activitypub\Model\Post( $post );
		$activitypub_activity = new \Activitypub\Model\Activity( 'Update', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_post( $activitypub_post->to_array() );

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json(); // phpcs:ignore

			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}

	/**
	 * Send "delete" activities
	 *
	 * @param int $post_id
	 */
	public static function send_delete_activity( $post_id ) {
		$post = \get_post( $post_id );
		$user_id = $post->post_author;

		$activitypub_post = new \Activitypub\Model\Post( $post );
		$activitypub_activity = new \Activitypub\Model\Activity( 'Delete', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_post( $activitypub_post->to_array() );

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			$too[] = 'https://www.w3.org/ns/activitystreams#Public';
			$too[] = $to;
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json(); // phpcs:ignore

			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}

	/**
	 * Send "create" activities for comments
	 *
	 * @param int $comment_id
	 */
	public static function send_comment_activity( $comment_id ) {
		//ONLY FOR LOCAL USERS ?
		$comment = \get_comment( $comment_id );
		//$user = get_user_by( 'login', \get_comment_author( $comment_id ) );//
		$user_id =  $comment->user_id;

		//error_log( 'dispatcher:send_comment:$user_id: ' . $user_id );

		// $ap_object = json_encode( get_comment_meta( $comment_id, 'ap_object', true ) );
		// error_log( 'dispatcher:send_comment:ap_object' );
		\error_log( print_r( $comment, true ) );
		$replyto = get_comment_meta( $comment->comment_parent, 'comment_author_url', true );//
		//error_log( 'dispatcher:send_comment:replyto' );
		//\error_log( 'Activity_Dispatcher::send_comment_activity' );

		$activitypub_comment = new \Activitypub\Model\Comment( $comment );
		$activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_comment( $activitypub_comment->to_array() );

		error_log( 'Activity_Dispatcher::send_comment_activity:payload' );
		error_log(print_r($activitypub_activity, true));
		\error_log( '$user_id: '. $user_id );
		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			\error_log( '$inbox: '. $inbox );
			\error_log( '$to: '. print_r( $to[0], true ) );
			$activitypub_activity->set_to( $to[0] );
			//error_log( 'Activity_Dispatcher::send_comment_activity:set_to(): ' . print_r( $to, true ));
			$activity = $activitypub_activity->to_json(); // phpcs:ignore
			error_log(print_r($activity, true));
			// Send reply to followers, skip if replying to followers (avoid duplicate replies)
			// if( in_array( $to, $replyto ) || ( $replyto == $to ) ) {
			// 	continue;
			// }
			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
		// Reply (to followers and non-followers)
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
	 * @param int $comment_id
	 */
	public static function inbox_forward_activity( $comment_id ) {
		\error_log( 'Activity_Dispatcher::inbox_forward_activity' );
		$comment = \get_comment( $comment_id );
		//why do I need the parent here? to get inbox ID?
		$parent_comment = \get_comment( $comment->comment_parent );
		
		//$user = get_user_by( 'login', \get_comment_author( $parent_comment->comment_ID ) );
		//\error_log( print_r( \get_comment_author( $parent_comment['comment_ID'] ), true ) );
		//\error_log( '$parent_comment->user_id' . print_r( $parent_comment ), true );
		$replyto[] = $comment->comment_author_url;
		$activitypub_activity = unserialize( get_comment_meta( $comment_id, 'ap_object', true ) );
		$user_id = $activitypub_activity['user_id'];
		if ( $user_id === 0 ) {
			$parent_comment = \get_comment( $comment->comment_parent );
			$user_id = $parent_comment->user_id;
		}
		//remove user_id from $activitypub_activity
		unset($activitypub_activity['user_id']);
		\error_log( '$activitypub_activity: ' );
		\error_log( print_r( $activitypub_activity, true ) );
		
		//if is foreign user
		/*
		When Activities are received in the inbox, the server needs to forward these to recipients that the origin was unable to deliver them to.
		To do this,	the server MUST target and deliver to the values of to, cc, and/or audience if and only if all of the following are true:

This is the first time the server has seen this Activity.
The values of to, cc, and/or audience contain a Collection owned by the server. (user followers collection)
The values of inReplyTo, object, target and/or tag are objects owned by the server.
The server SHOULD recurse through these values to look for linked objects owned by the server,
and SHOULD set a maximum limit for recursion (ie. the point at which the thread is so deep the recipients
followers may not mind if they are no longer getting updates that don't directly involve the recipient).
The server MUST only target the values of to, cc, and/or audience on the original object being forwarded, and not pick up
any new addressees whilst recursing through the linked objects (in case these addressees were purposefully amended by or via the client).
*/
		//$replyto = get_comment_meta( $comment_id, 'replyto', true );
		//error_log( print_r( $replyto, true) );
	
		// $activitypub_comment = new \Activitypub\Model\Comment( $comment );
		// $activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_FULL );
		// $activitypub_activity->from_comment( $activitypub_comment->to_array() );
//error_log(print_r($activitypub_activity, true));

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			\error_log( '$user_id: ' . $user_id );
			\error_log( '$inbox: '. $inbox );
			\error_log( '$to: '. print_r($to, true ) );			
			array_push( $activitypub_activity['object']['to'], $to[0] );
			array_push( $activitypub_activity['to'], $to[0] );
			//Forward reply to followers, skip 
			if( in_array( $to, $replyto ) || ( $replyto == $to ) ) {
				continue;
			}
			error_log(print_r($activitypub_activity, true));
			//$activitypub_activity
			//$activitypub_activity->set_to( $to );
			//$activity = $activitypub_activity->to_json(); // phpcs:ignore
			$activity = \wp_json_encode( $activitypub_activity, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT );
			error_log( 'dispatch:forward:activity:' );
			error_log( print_r( $activity, true ) );
			error_log( 'dispatch:forward:inbox: '. $inbox );
			\Activitypub\forward_remote_post( $inbox, $activity, $user_id );
			array_pop( $activitypub_activity['object']['to'] );
			array_pop( $activitypub_activity['to'] );
		}
	}
}
