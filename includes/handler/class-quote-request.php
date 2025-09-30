<?php
/**
 * Handler for QuoteRequest activities.
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

/**
 * Handler for QuoteRequest activities.
 *
 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/044f/fep-044f.md
 */
class Quote_Request {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_quote_request', array( self::class, 'handle_quote_request' ), 10, 2 );
		\add_action( 'activitypub_rest_inbox_disallowed', array( self::class, 'handle_blocked_request' ), 10, 3 );

		\add_filter( 'activitypub_validate_object', array( self::class, 'validate_object' ), 10, 3 );
	}

	/**
	 * Handle QuoteRequest activities.
	 *
	 * @param array $activity The activity object.
	 * @param int   $user_id  The user ID.
	 */
	public static function handle_quote_request( $activity, $user_id ) {
		$state   = true;
		$post_id = \url_to_postid( object_to_uri( $activity['object'] ) );

		if ( ! $post_id ) {
			self::queue_reject( $activity, $user_id );
			return;
		}

		$content_policy = \get_post_meta( $post_id, 'activitypub_interaction_policy_quote', true );

		switch ( $content_policy ) {
			case ACTIVITYPUB_INTERACTION_POLICY_ME:
				self::queue_reject( $activity, $user_id );
				$state = false;
				break;
			case ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS:
				$follower = Remote_Actors::get_by_uri( object_to_uri( $activity['actor'] ) );
				if ( ! \is_wp_error( $follower ) && Followers::follows( $follower->ID, $user_id ) ) {
					self::queue_accept( $activity, $user_id, $post_id );
				} else {
					self::queue_reject( $activity, $user_id );
					$state = false;
				}
				break;
			case ACTIVITYPUB_INTERACTION_POLICY_ANYONE:
			default:
				self::queue_accept( $activity, $user_id, $post_id );
				break;
		}

		/**
		 * Fires after an ActivityPub QuoteRequest activity has been handled.
		 *
		 * @param array  $activity       The ActivityPub activity data.
		 * @param int    $user_id        The local user ID.
		 * @param bool   $success        True on success, false otherwise.
		 * @param string $content_policy The content policy for the quoted post.
		 */
		\do_action( 'activitypub_handled_quote_request', $activity, $user_id, $state, $content_policy );
	}

	/**
	 * ActivityPub inbox disallowed activity.
	 *
	 * @param array    $activity The activity array.
	 * @param int|null $user_id  The user ID.
	 * @param string   $type     The type of the activity.
	 */
	public static function handle_blocked_request( $activity, $user_id, $type ) {
		if ( 'quoterequest' !== \strtolower( $type ) ) {
			return;
		}

		self::queue_reject( $activity, $user_id );
	}

	/**
	 * Send an Accept activity in response to the QuoteRequest.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/044f/fep-044f.md#example-accept
	 *
	 * @param array $activity_object The activity object.
	 * @param int   $user_id         The user ID.
	 * @param int   $post_id         The post ID.
	 */
	public static function queue_accept( $activity_object, $user_id, $post_id ) {
		$actor = Actors::get_by_id( $user_id );

		if ( \is_wp_error( $actor ) ) {
			return;
		}

		$activity_object['instrument'] = object_to_uri( $activity_object['instrument'] );

		$post_meta = \get_post_meta( $post_id, '_activitypub_quoted_by', false );
		if ( in_array( $activity_object['instrument'], $post_meta, true ) ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$meta_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_value = %s LIMIT 1",
					$post_id,
					'_activitypub_quoted_by',
					$activity_object['instrument']
				)
			);
		} else {
			$meta_id = \add_post_meta( $post_id, '_activitypub_quoted_by', $activity_object['instrument'] );
		}

		// Only send minimal data.
		$activity_object = array_intersect_key(
			$activity_object,
			array(
				'id'         => 1,
				'type'       => 1,
				'actor'      => 1,
				'object'     => 1,
				'instrument' => 1,
			)
		);

		$url = \add_query_arg(
			array(
				'p'     => $post_id,
				'stamp' => $meta_id,
			),
			\home_url( '/' )
		);

		$activity = new Activity();
		$activity->set_type( 'Accept' );
		$activity->set_actor( $actor->get_id() );
		$activity->set_object( $activity_object );
		$activity->set_result( $url );
		$activity->add_to( object_to_uri( $activity_object['actor'] ) );

		add_to_outbox( $activity, null, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
	}

	/**
	 * Send a Reject activity in response to the QuoteRequest.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/044f/fep-044f.md#example-reject
	 *
	 * @param array $activity_object The activity object.
	 * @param int   $user_id  The user ID.
	 */
	public static function queue_reject( $activity_object, $user_id ) {
		$actor = Actors::get_by_id( $user_id );

		if ( \is_wp_error( $actor ) ) {
			return;
		}

		$activity_object['instrument'] = object_to_uri( $activity_object['instrument'] );

		// Only send minimal data.
		$activity_object = array_intersect_key(
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
	 * Validate the object.
	 *
	 * @param bool             $valid   The validation state.
	 * @param string           $param   The object parameter.
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return bool The validation state: true if valid, false if not.
	 */
	public static function validate_object( $valid, $param, $request ) {
		if ( \is_wp_error( $request ) ) {
			return $valid;
		}

		$json_params = $request->get_json_params();

		if ( empty( $json_params['type'] ) ) {
			return false;
		}

		if ( 'QuoteRequest' !== $json_params['type'] ) {
			return $valid;
		}

		$required_attributes = array(
			'actor',
			'object',
			'instrument',
		);

		if ( ! empty( \array_diff( $required_attributes, \array_keys( $json_params ) ) ) ) {
			return false;
		}

		return $valid;
	}
}
