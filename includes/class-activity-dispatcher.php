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
		\add_action( 'activitypub_send_update_activity', array( '\Activitypub\Activity_Dispatcher', 'send_update_activity' ) );
		\add_action( 'activitypub_send_delete_activity', array( '\Activitypub\Activity_Dispatcher', 'send_delete_activity' ) );
	}

	/**
	 * Send "create" activities.
	 *
	 * @param \Activitypub\Model\Post $activitypub_post
	 */
	public static function send_post_activity( Model\Post $activitypub_post ) {
		// get latest version of post
		$user_id = $activitypub_post->get_post_author();

		$activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_post( $activitypub_post );

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
			$to = array_unique( $to );
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json();

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
		$user_id = $activitypub_post->get_post_author();

		$activitypub_activity = new \Activitypub\Model\Activity( 'Delete', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_post( $activitypub_post->to_array() );

		foreach ( \Activitypub\get_follower_inboxes( $user_id ) as $inbox => $to ) {
			$activitypub_activity->set_to( $to );
			$activity = $activitypub_activity->to_json(); // phpcs:ignore

			\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
		}
	}
}
