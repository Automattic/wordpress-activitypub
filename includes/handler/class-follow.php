<?php
/**
 * Follow handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\add_to_outbox;

/**
 * Handle Follow requests.
 */
class Follow {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_follow', array( self::class, 'handle_follow' ), 10, 2 );
		\add_action( 'activitypub_handled_follow', array( self::class, 'queue_accept' ), 10, 4 );
		\add_action( 'activitypub_handled_outbox_follow', array( self::class, 'handle_outbox_follow' ), 10, 4 );
	}

	/**
	 * Handle "Follow" requests.
	 *
	 * @param array     $activity The activity object.
	 * @param int|int[] $user_ids The user ID(s).
	 */
	public static function handle_follow( $activity, $user_ids ) {
		// Extract the user ID (follow requests are always for a single user).
		$user_id = \is_array( $user_ids ) ? \reset( $user_ids ) : $user_ids;

		if ( Actors::APPLICATION_USER_ID === $user_id ) {
			self::queue_reject( $activity, $user_id );
			return;
		}

		// Check if the actor already follows the user.
		$already_following = false;
		$remote_actor      = Remote_Actors::get_by_uri( $activity['actor'] );
		if ( ! \is_wp_error( $remote_actor ) ) {
			$already_following = Followers::follows( $remote_actor->ID, $user_id );
		}

		// Save follower if not already following.
		if ( $already_following ) {
			$success = false;
		} else {
			$remote_actor = Followers::add( $user_id, $activity['actor'] );
			$success      = ! \is_wp_error( $remote_actor );

			if ( $success ) {
				$remote_actor = \get_post( $remote_actor );
			}
		}

		/**
		 * Fires after a new follower has been added.
		 *
		 * @deprecated 7.5.0 Use "activitypub_handled_follow" instead.
		 *
		 * @param string             $actor        The URL of the actor (follower) who initiated the follow.
		 * @param array              $activity     The complete activity data of the follow request.
		 * @param int                $user_id      The ID of the WordPress user being followed.
		 * @param \WP_Post|\WP_Error $remote_actor The Actor object containing the new follower's data.
		 */
		\do_action_deprecated( 'activitypub_followers_post_follow', array( $activity['actor'], $activity, $user_id, $remote_actor ), '7.5.0', 'activitypub_handled_follow' );

		/**
		 * Fires after a Follow activity has been handled.
		 *
		 * @param array              $activity     The ActivityPub activity data.
		 * @param int[]              $user_ids     The local user IDs.
		 * @param bool               $success      True on success, false otherwise.
		 * @param \WP_Post|\WP_Error $remote_actor The remote actor/follower, or WP_Error if failed.
		 */
		\do_action( 'activitypub_handled_follow', $activity, (array) $user_ids, $success, $remote_actor );
	}

	/**
	 * Send Accept response.
	 *
	 * @param array              $activity_object The ActivityPub activity data.
	 * @param int|int[]          $user_ids        The local user IDs.
	 * @param bool               $success         True on success, false otherwise.
	 * @param \WP_Post|\WP_Error $remote_actor    The remote actor/follower, or WP_Error if failed.
	 */
	public static function queue_accept( $activity_object, $user_ids, $success, $remote_actor ) {
		if ( \is_wp_error( $remote_actor ) ) {
			// Impossible to send a "Reject" because we can not get the Remote-Inbox.
			return;
		}

		// Extract the user ID from the array (follow requests are always for a single user).
		$user_id = \is_array( $user_ids ) ? \reset( $user_ids ) : $user_ids;

		$actor = $activity_object['actor'];

		// Only send minimal data.
		$activity_object = array_intersect_key(
			$activity_object,
			array(
				'id'     => 1,
				'type'   => 1,
				'actor'  => 1,
				'object' => 1,
			)
		);

		$activity = new Activity();
		$activity->set_type( 'Accept' );
		$activity->set_actor( Actors::get_by_id( $user_id )->get_id() );
		$activity->set_object( $activity_object );
		$activity->set_to( array( $actor ) );

		add_to_outbox( $activity, null, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
	}

	/**
	 * Send Reject response.
	 *
	 * @param array $activity The Activity array.
	 * @param int   $user_id  The ID of the WordPress User.
	 */
	public static function queue_reject( $activity, $user_id ) {
		// Only send minimal data.
		$origin_activity = array_intersect_key(
			$activity,
			array(
				'id'     => 1,
				'type'   => 1,
				'actor'  => 1,
				'object' => 1,
			)
		);

		$activity = new Activity();
		$activity->set_type( 'Reject' );
		$activity->set_actor( Actors::get_by_id( $user_id )->get_id() );
		$activity->set_object( $origin_activity );
		$activity->set_to( array( $origin_activity['actor'] ) );

		add_to_outbox( $activity, null, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
	}

	/**
	 * Handle outbox "Follow" activities (C2S).
	 *
	 * Adds the target actor to the user's following list (pending until accepted).
	 *
	 * @param array                          $data       The activity data array.
	 * @param int                            $user_id    The user ID.
	 * @param \Activitypub\Activity\Activity $activity   The Activity object.
	 * @param int                            $outbox_id  The outbox post ID.
	 */
	public static function handle_outbox_follow( $data, $user_id, $activity, $outbox_id ) {
		$object = $data['object'] ?? '';

		// The object should be the actor URL to follow.
		if ( empty( $object ) || ! \is_string( $object ) ) {
			return;
		}

		// Fetch or create the remote actor.
		$remote_actor = Remote_Actors::fetch_by_uri( $object );

		if ( \is_wp_error( $remote_actor ) ) {
			return;
		}

		// Check if already following.
		$all_meta  = \get_post_meta( $remote_actor->ID );
		$following = $all_meta[ Following::FOLLOWING_META_KEY ] ?? array();
		$pending   = $all_meta[ Following::PENDING_META_KEY ] ?? array();

		if ( \in_array( (string) $user_id, $following, true ) || \in_array( (string) $user_id, $pending, true ) ) {
			return;
		}

		// Add to pending following.
		\add_post_meta( $remote_actor->ID, Following::PENDING_META_KEY, (string) $user_id );

		/**
		 * Fires after a Follow activity has been sent via C2S.
		 *
		 * @param int      $remote_actor_id The remote actor post ID.
		 * @param array    $data            The activity data.
		 * @param int      $user_id         The user ID.
		 * @param int      $outbox_id       The outbox post ID.
		 */
		\do_action( 'activitypub_outbox_follow_sent', $remote_actor->ID, $data, $user_id, $outbox_id );
	}
}
