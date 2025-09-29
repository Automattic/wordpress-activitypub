<?php
/**
 * Move handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

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
		\add_action( 'activitypub_inbox_quoterequest', array( self::class, 'handle_quote_request' ), 10, 2 );
		\add_action( 'activitypub_rest_inbox_disallowed', array( self::class, 'handle_blocked_request' ), 10, 4 );

		\add_filter( 'activitypub_validate_object', array( self::class, 'validate_object' ), 10, 3 );
	}

	/**
	 * Handle QuoteRequest activities.
	 *
	 * @param array $activity The activity object.
	 * @param int   $user_id  The user ID.
	 */
	public static function handle_quote_request( $activity, $user_id ) {
		$post           = \get_post( object_to_uri( $activity['object'] ) );
		$content_policy = \get_post_meta( $post->ID, 'activitypub_interaction_policy_quote', true );

		switch ( $content_policy ) {
			case ACTIVITYPUB_INTERACTION_POLICY_ANYONE:
				self::send_accept( $activity, $user_id );
				break;
			case ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS:
				$follower = Remote_Actors::get_by_uri( object_to_uri( $activity['actor'] ) );
				if ( ! \is_wp_error( $follower ) && Followers::follows( $follower->ID, $user_id ) ) {
					self::send_accept( $activity, $user_id );
				} else {
					self::send_reject( $activity, $user_id );
				}
				break;
			case ACTIVITYPUB_INTERACTION_POLICY_ME:
			default:
				self::send_reject( $activity, $user_id );
				break;
		}
	}

	/**
	 * ActivityPub inbox disallowed activity.
	 *
	 * @param array  $activity The activity array.
	 * @param null   $user_id  The user ID.
	 * @param string $type     The type of the activity.
	 */
	public static function handle_blocked_request( $activity, $user_id, $type ) {
		if ( 'quoterequest' !== \strtolower( $type ) ) {
			return;
		}

		self::send_reject( $activity, $user_id );
	}

	/**
	 * Send an Accept activity in response to the QuoteRequest.
	 *
	 * @param array $activity The activity object.
	 * @param int   $user_id  The user ID.
	 */
	public static function send_accept( $activity, $user_id ) {
		$activity = self::normalize_activity( $activity );

		add_to_outbox( $activity, 'Accept', $user_id );
	}

	/**
	 * Send a Reject activity in response to the QuoteRequest.
	 *
	 * @param array $activity The activity object.
	 * @param int   $user_id  The user ID.
	 */
	public static function send_reject( $activity, $user_id ) {
		$activity = self::normalize_activity( $activity );

		add_to_outbox( $activity, 'Reject', $user_id );
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
		$json_params = $request->get_json_params();

		if ( empty( $json_params['type'] ) ) {
			return false;
		}

		if (
			'QuoteRequest' !== $json_params['type'] ||
			\is_wp_error( $request )
		) {
			return $valid;
		}

		$required_attributes = array(
			'actor',
			'object',
		);

		if ( ! empty( \array_diff( $required_attributes, \array_keys( $json_params ) ) ) ) {
			return false;
		}

		$required_object_attributes = array(
			'id',
			'type',
			'actor',
			'object',
			'instrument',
		);

		if ( ! empty( \array_diff( $required_object_attributes, \array_keys( $json_params['object'] ) ) ) ) {
			return false;
		}

		return $valid;
	}

	/**
	 * Normalize the activity.
	 *
	 * @param array $activity The activity object.
	 *
	 * @return array The normalized activity object.
	 */
	private static function normalize_activity( $activity ) {
		if ( empty( $activity['type'] ) ) {
			$activity['type'] = 'QuoteRequest';
		}

		if ( empty( $activity['id'] ) ) {
			$activity['id'] = \wp_generate_uuid4();
		}

		return $activity;
	}
}
