<?php
/**
 * Handler for FeatureRequest activities (FEP-7aa9).
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;

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
	 * Behavior is filled in in the next task. Skeleton method exists so the
	 * action callback resolves during init.
	 *
	 * @param array     $activity The activity object.
	 * @param int|int[] $user_ids The user ID(s).
	 */
	public static function handle_feature_request( $activity, $user_ids ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Implemented in Task 6.
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
