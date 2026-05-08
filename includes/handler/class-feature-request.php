<?php
/**
 * Handler for FeatureRequest activities (FEP-7aa9).
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\add_to_outbox;
use function Activitypub\object_to_uri;
use function Activitypub\user_can_activitypub;

/**
 * Handler for FeatureRequest activities.
 *
 * @see https://w3id.org/fep/7aa9
 */
class Feature_Request {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_feature_request', array( self::class, 'handle_feature_request' ), 10, 2 );
		\add_action( 'activitypub_rest_inbox_disallowed', array( self::class, 'handle_blocked_request' ), 10, 3 );

		\add_filter( 'activitypub_validate_object', array( self::class, 'validate_object' ), 10, 3 );
	}

	/**
	 * Handle FeatureRequest activities.
	 *
	 * @param array     $activity The activity object.
	 * @param int|int[] $user_ids The user ID(s) targeted by the inbox dispatch.
	 */
	public static function handle_feature_request( $activity, $user_ids ) {
		$state      = true;
		$object_uri = object_to_uri( $activity['object'] );
		$target     = Actors::get_by_resource( $object_uri );

		if ( \is_wp_error( $target ) ) {
			$user_id = \is_array( $user_ids ) ? \reset( $user_ids ) : $user_ids;
			self::queue_reject( $activity, $user_id );
			return;
		}

		$user_id = $target->get__id();

		$policy = \get_option( 'activitypub_default_feature_policy', ACTIVITYPUB_INTERACTION_POLICY_ME );

		switch ( $policy ) {
			case ACTIVITYPUB_INTERACTION_POLICY_ANYONE:
				self::queue_accept( $activity, $user_id );
				break;

			case ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS:
				$follower = Remote_Actors::get_by_uri( object_to_uri( $activity['actor'] ) );
				if ( ! \is_wp_error( $follower ) && Followers::follows( $follower->ID, $user_id ) ) {
					self::queue_accept( $activity, $user_id );
				} else {
					self::queue_reject( $activity, $user_id );
					$state = false;
				}
				break;

			case ACTIVITYPUB_INTERACTION_POLICY_ME:
			default:
				self::queue_reject( $activity, $user_id );
				$state = false;
				break;
		}

		/**
		 * Fires after an ActivityPub FeatureRequest activity has been handled.
		 *
		 * @param array  $activity The ActivityPub activity data.
		 * @param int[]  $user_ids The local user IDs.
		 * @param bool   $state    True on accept, false otherwise.
		 * @param string $policy   The active site policy.
		 */
		\do_action( 'activitypub_handled_feature_request', $activity, (array) $user_ids, $state, $policy );
	}

	/**
	 * ActivityPub inbox disallowed activity.
	 *
	 * @param array          $activity The activity array.
	 * @param int|int[]|null $user_ids The user ID(s).
	 * @param string         $type     The activity type.
	 */
	public static function handle_blocked_request( $activity, $user_ids, $type ) {
		if ( ! \in_array( \strtolower( $type ), array( 'featurerequest', 'feature_request' ), true ) ) {
			return;
		}

		$user_id = \is_array( $user_ids ) ? \reset( $user_ids ) : $user_ids;
		self::queue_reject( $activity, $user_id );
	}

	/**
	 * Send an Accept activity in response to the FeatureRequest, issuing a stamp.
	 *
	 * Idempotent: a second call with the same instrument for the same user reuses
	 * the existing umeta row instead of minting a duplicate stamp.
	 *
	 * @param array $activity_object The activity object.
	 * @param int   $user_id         The local user ID being featured.
	 */
	public static function queue_accept( $activity_object, $user_id ) {
		if ( ! user_can_activitypub( $user_id ) ) {
			$user_id = Actors::BLOG_USER_ID;
		}

		$actor = Actors::get_by_id( $user_id );
		if ( \is_wp_error( $actor ) ) {
			return;
		}

		$activity_object['instrument'] = object_to_uri( $activity_object['instrument'] );

		// Idempotent stamp creation.
		$existing = \get_user_meta( $user_id, '_activitypub_featured_by', false );
		if ( \in_array( $activity_object['instrument'], (array) $existing, true ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$umeta_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT umeta_id FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s AND meta_value = %s LIMIT 1",
					$user_id,
					'_activitypub_featured_by',
					$activity_object['instrument']
				)
			);
		} else {
			$umeta_id = \add_user_meta( $user_id, '_activitypub_featured_by', $activity_object['instrument'] );
		}

		// Send minimal activity object back.
		$activity_object = \array_intersect_key(
			$activity_object,
			array(
				'id'         => 1,
				'type'       => 1,
				'actor'      => 1,
				'object'     => 1,
				'instrument' => 1,
			)
		);

		$stamp_url = \add_query_arg(
			array(
				'actor' => $user_id,
				'stamp' => $umeta_id,
			),
			\home_url( '/' )
		);

		$activity = new Activity();
		$activity->set_type( 'Accept' );
		$activity->set_actor( $actor->get_id() );
		$activity->set_object( $activity_object );
		$activity->set_result( $stamp_url );
		$activity->add_to( object_to_uri( $activity_object['actor'] ) );

		add_to_outbox( $activity, null, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
	}

	/**
	 * Send a Reject activity in response to the FeatureRequest.
	 *
	 * @param array $activity_object The activity object.
	 * @param int   $user_id         The user ID.
	 */
	public static function queue_reject( $activity_object, $user_id ) {
		if ( ! user_can_activitypub( $user_id ) ) {
			$user_id = Actors::BLOG_USER_ID;
		}

		$actor = Actors::get_by_id( $user_id );
		if ( \is_wp_error( $actor ) ) {
			return;
		}

		if ( isset( $activity_object['instrument'] ) ) {
			$activity_object['instrument'] = object_to_uri( $activity_object['instrument'] );
		}

		// Only send minimal data.
		$activity_object = \array_intersect_key(
			$activity_object,
			array(
				'id'         => 1,
				'type'       => 1,
				'actor'      => 1,
				'object'     => 1,
				'instrument' => 1,
			)
		);

		$activity = new Activity();
		$activity->set_type( 'Reject' );
		$activity->set_actor( $actor->get_id() );
		$activity->set_object( $activity_object );
		$activity->add_to( object_to_uri( $activity_object['actor'] ) );

		add_to_outbox( $activity, null, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
	}

	/**
	 * Validate the object on incoming FeatureRequest activities.
	 *
	 * @param bool             $valid   The current validation state.
	 * @param string           $param   The object parameter name.
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return bool
	 */
	public static function validate_object( $valid, $param, $request ) {
		$activity = $request->get_json_params();

		if ( empty( $activity['type'] ) ) {
			return false;
		}

		if ( 'FeatureRequest' !== $activity['type'] ) {
			return $valid;
		}

		if ( ! isset( $activity['actor'], $activity['object'], $activity['instrument'] ) ) {
			return false;
		}

		return $valid;
	}
}
