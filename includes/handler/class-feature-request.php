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
	 * Option-name prefix for the blog actor's feature stamps.
	 *
	 * The blog actor has no users-table row (its ID is 0), so its stamps cannot
	 * live in user meta like the stamps of regular users do. Each stamp is stored
	 * in its own option row, keyed `{prefix}_{id}`, so a stamp ID can be claimed
	 * atomically with `add_option()` without a lock.
	 *
	 * @var string
	 */
	const BLOG_STAMPS_OPTION = 'activitypub_blog_featured_by';

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
	 * Idempotent: a second call with the same instrument for the same actor reuses
	 * the existing stamp instead of minting a duplicate, see {@see add_stamp()}.
	 *
	 * @param array $activity_object The activity object.
	 * @param int   $user_id         The local user ID being featured (0 for the blog actor).
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

		$stamp_id = self::add_stamp( $user_id, $activity_object['instrument'] );
		if ( ! $stamp_id ) {
			// Without a stamp there is nothing to consent with — don't send a dangling Accept.
			return;
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
				'stamp' => $stamp_id,
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
	 * Create (or reuse) a feature stamp for an actor.
	 *
	 * Stamps for users live in user meta, with the umeta_id doubling as the
	 * stamp ID. The blog actor has no users-table row, so each of its stamps is
	 * stored in its own option row instead.
	 *
	 * Idempotent: an existing stamp for the same instrument is reused.
	 *
	 * @since 9.0.1
	 *
	 * @param int    $user_id    The local actor ID (0 for the blog actor).
	 * @param string $instrument The instrument URI being stamped.
	 * @return int|false The stamp ID, or false on failure.
	 */
	public static function add_stamp( $user_id, $instrument ) {
		if ( Actors::BLOG_USER_ID === $user_id ) {
			return self::add_blog_stamp( $instrument );
		}

		$existing = \get_user_meta( $user_id, '_activitypub_featured_by', false );
		if ( \in_array( $instrument, (array) $existing, true ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT umeta_id FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s AND meta_value = %s LIMIT 1",
					$user_id,
					'_activitypub_featured_by',
					$instrument
				)
			);
		}

		return \add_user_meta( $user_id, '_activitypub_featured_by', $instrument );
	}

	/**
	 * Create (or reuse) a feature stamp for the blog actor.
	 *
	 * Each blog stamp lives in its own option row (`{prefix}_{id}`), so a slot is
	 * claimed atomically with `add_option()` — which only inserts when the row is
	 * absent (see Scheduler\Statistics::send_annual_email()). Concurrent inbox
	 * deliveries therefore can't mint the same ID or clobber each other's writes,
	 * and no lock is needed. Stamps are never deleted, so the slots stay gap-free
	 * and the walk stops at the first empty slot.
	 *
	 * @param string $instrument The instrument URI being stamped.
	 * @return int|false The stamp ID, or false on failure.
	 */
	private static function add_blog_stamp( $instrument ) {
		$stamp_id = 1;

		/*
		 * Bounded far above any realistic blog stamp count, to guard against a
		 * pathological object-cache state where a just-claimed slot never becomes
		 * visible and the re-read would otherwise spin forever.
		 */
		for ( $attempt = 0; $attempt < 10000; $attempt++ ) {
			$key     = self::BLOG_STAMPS_OPTION . '_' . $stamp_id;
			$current = \get_option( $key );

			// Idempotent: an existing stamp for the same instrument is reused.
			if ( $current === $instrument ) {
				return $stamp_id;
			}

			if ( false === $current ) {
				if ( \add_option( $key, $instrument, '', false ) ) {
					return $stamp_id;
				}

				// A concurrent accept claimed this slot first; re-read it (no
				// increment) to see whether it took our instrument or another.
				continue;
			}

			// Slot holds a different instrument; try the next one.
			++$stamp_id;
		}

		return false;
	}

	/**
	 * Resolve a stamp ID for an actor to the stamped instrument URI.
	 *
	 * @since 9.0.1
	 *
	 * @param int $user_id  The local actor ID (0 for the blog actor).
	 * @param int $stamp_id The stamp ID.
	 * @return string|null The instrument URI, or null if the stamp does not exist for this actor.
	 */
	public static function get_stamp( $user_id, $stamp_id ) {
		if ( Actors::BLOG_USER_ID === $user_id ) {
			$instrument = \get_option( self::BLOG_STAMPS_OPTION . '_' . (int) $stamp_id );

			return false === $instrument ? null : $instrument;
		}

		$meta = \get_metadata_by_mid( 'user', $stamp_id );
		if ( ! $meta || '_activitypub_featured_by' !== $meta->meta_key || (int) $meta->user_id !== $user_id ) {
			return null;
		}

		return $meta->meta_value;
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
