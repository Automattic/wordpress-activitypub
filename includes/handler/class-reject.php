<?php
/**
 * Reject handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Following;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Http;

use function Activitypub\add_to_outbox;
use function Activitypub\is_same_actor;
use function Activitypub\object_to_uri;

/**
 * Handle "Reject" requests.
 */
class Reject {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_reject', array( self::class, 'handle_reject' ), 10, 2 );
		\add_filter( 'activitypub_validate_object', array( self::class, 'validate_object' ), 10, 3 );
	}

	/**
	 * Handles "Reject" requests.
	 *
	 * @param array     $reject   The activity-object.
	 * @param int|int[] $user_ids The user ID(s).
	 */
	public static function handle_reject( $reject, $user_ids ) {
		// Validate that there is a preceding Activity.
		$outbox_post = Outbox::get_by_guid( $reject['object']['id'] ?? '' );

		if ( \is_wp_error( $outbox_post ) ) {
			return;
		}

		switch ( \get_post_meta( $outbox_post->ID, '_activitypub_activity_type', true ) ) {
			case 'Follow':
				self::reject_follow( $reject, $user_ids );
				break;
			case 'QuoteRequest':
				self::reject_quote_request( $reject, $outbox_post );
				break;
			default:
				break;
		}
	}

	/**
	 * Reject a "Follow" request.
	 *
	 * @param array     $reject   The activity-object.
	 * @param int|int[] $user_ids The user ID(s).
	 */
	private static function reject_follow( $reject, $user_ids ) {
		/*
		 * For a Follow Reject, the sender must be the actor that was followed.
		 * Without this, a signed Reject from one actor could cancel a Follow that
		 * targeted another actor by referencing that pending Follow's outbox GUID.
		 */
		$reject_actor   = object_to_uri( $reject['actor'] ?? '' );
		$followed_actor = object_to_uri( $reject['object']['object'] ?? '' );
		if ( ! $reject_actor || ! $followed_actor || $reject_actor !== $followed_actor ) {
			return;
		}

		$actor_post = Remote_Actors::get_by_uri( $followed_actor );

		if ( \is_wp_error( $actor_post ) ) {
			return;
		}

		$user_id = \is_array( $user_ids ) ? \reset( $user_ids ) : $user_ids;
		$result  = Following::reject( $actor_post, $user_id );
		$success = ! \is_wp_error( $result );

		/**
		 * Fires after an ActivityPub Reject activity has been handled.
		 *
		 * @param array              $reject   The ActivityPub activity data.
		 * @param int[]              $user_ids The local user IDs.
		 * @param bool               $success  True on success, false otherwise.
		 * @param \WP_Post|\WP_Error $result   Actor post on success, WP_Error on failure.
		 */
		\do_action( 'activitypub_handled_reject', $reject, (array) $user_ids, $success, $result );
	}

	/**
	 * Reject a "QuoteRequest" of ours: the quote part is dropped from the post.
	 *
	 * @since unreleased
	 *
	 * @param array    $reject      The activity-object.
	 * @param \WP_Post $outbox_post Our QuoteRequest outbox item.
	 */
	private static function reject_quote_request( $reject, $outbox_post ) {
		$request = Outbox::get_activity( $outbox_post );

		if ( \is_wp_error( $request ) || ! $request->get_instrument() ) {
			return;
		}

		// The request's `object` is the quoted URI; our post is its `instrument`.
		$post_id = \url_to_postid( object_to_uri( $request->get_instrument() ) );
		$post    = $post_id ? \get_post( $post_id ) : null;

		if ( ! $post ) {
			return;
		}

		$quoted_uri = object_to_uri( $request->get_object() );

		// A Reject for a request the post has since superseded with another quoted URL is ignored.
		if ( \get_post_meta( $post->ID, '_activitypub_quote_request', true ) !== $quoted_uri ) {
			return;
		}

		if ( ! self::quoted_author_matches( $reject, $quoted_uri ) ) {
			return;
		}

		\update_post_meta( $post->ID, '_activitypub_quote_rejected', '1' );
		\delete_post_meta( $post->ID, '_activitypub_quote_authorization' );

		add_to_outbox( $post, 'Update', $post->post_author );
	}

	/**
	 * Only the quoted object's author may answer our QuoteRequest.
	 *
	 * @since unreleased
	 *
	 * @param array  $reject     The activity-object.
	 * @param string $quoted_uri The quoted object URI.
	 *
	 * @return bool True if the sender is the quoted author.
	 */
	private static function quoted_author_matches( $reject, $quoted_uri ) {
		if ( ! $quoted_uri ) {
			return false;
		}

		$quoted = Http::get_remote_object( $quoted_uri );

		if ( \is_wp_error( $quoted ) || empty( $quoted['attributedTo'] ) ) {
			return false;
		}

		return is_same_actor( $reject['actor'] ?? '', $quoted['attributedTo'] );
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
		$activity = $request->get_json_params();

		if ( empty( $activity['type'] ) ) {
			return false;
		}

		if ( 'Reject' !== $activity['type'] ) {
			return $valid;
		}

		if ( ! isset( $activity['actor'], $activity['object'] ) ) {
			return false;
		}

		if ( ! \is_array( $activity['object'] ) ) {
			return false;
		}

		if ( ! isset( $activity['object']['id'], $activity['object']['type'], $activity['object']['actor'], $activity['object']['object'] ) ) {
			return false;
		}

		return $valid;
	}
}
