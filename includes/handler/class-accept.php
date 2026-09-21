<?php
/**
 * Accept handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Following;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Http;

use function Activitypub\add_to_outbox;
use function Activitypub\get_object_id;
use function Activitypub\is_same_actor;
use function Activitypub\is_same_host;
use function Activitypub\object_to_uri;

/**
 * Handle Accept requests.
 */
class Accept {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_accept', array( self::class, 'handle_accept' ), 10, 2 );
		\add_filter( 'activitypub_validate_object', array( self::class, 'validate_object' ), 10, 3 );
	}

	/**
	 * Handles "Accept" requests.
	 *
	 * @param array     $accept   The activity-object.
	 * @param int|int[] $user_ids The id of the local blog-user.
	 */
	public static function handle_accept( $accept, $user_ids ) {
		// Validate that there is a preceding Activity of ours.
		$outbox_post = Outbox::get_by_guid( $accept['object']['id'] ?? '' );

		if ( \is_wp_error( $outbox_post ) ) {
			return;
		}

		switch ( \get_post_meta( $outbox_post->ID, '_activitypub_activity_type', true ) ) {
			case 'QuoteRequest':
				self::accept_quote_request( $accept, $outbox_post );
				return;
			case 'Follow':
				break;
			default:
				return;
		}

		/*
		 * For a Follow Accept, the sender must be the actor that was followed.
		 * Without this, a signed Accept from one actor could confirm a Follow that
		 * targeted another actor by referencing that pending Follow's outbox GUID.
		 */
		if ( ! is_same_actor( $accept['actor'] ?? '', $accept['object']['object'] ?? '' ) ) {
			return;
		}

		$actor_post = Remote_Actors::get_by_uri( object_to_uri( $accept['object']['object'] ?? '' ) );

		if ( \is_wp_error( $actor_post ) ) {
			return;
		}

		$user_id = \is_array( $user_ids ) ? \reset( $user_ids ) : $user_ids;
		$result  = Following::accept( $actor_post, $user_id );
		$success = ! \is_wp_error( $result );

		/**
		 * Fires after an ActivityPub Accept activity has been handled.
		 *
		 * @param array              $accept   The ActivityPub activity data.
		 * @param int[]              $user_ids The local user IDs.
		 * @param bool               $success  True on success, false otherwise.
		 * @param \WP_Post|\WP_Error $result   The remote actor post or error.
		 */
		\do_action( 'activitypub_handled_accept', $accept, (array) $user_ids, $success, $result );
	}

	/**
	 * Accept a "QuoteRequest" of ours: verify and store the QuoteAuthorization stamp.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/044f/fep-044f.md#quoteauthorization
	 * @since unreleased
	 *
	 * @param array    $accept      The activity-object.
	 * @param \WP_Post $outbox_post Our QuoteRequest outbox item.
	 */
	private static function accept_quote_request( $accept, $outbox_post ) {
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

		// An Accept for a request the post has since superseded with another quoted URL is ignored.
		if ( \get_post_meta( $post->ID, '_activitypub_quote_request', true ) !== $quoted_uri ) {
			return;
		}

		if ( ! self::quoted_author_matches( $accept, $quoted_uri ) ) {
			return;
		}

		$stamp_uri = object_to_uri( $accept['result'] ?? '' );

		if ( ! $stamp_uri ) {
			return;
		}

		// The stamp is issued by the quoted author, so it must live on the sender's host.
		if ( ! is_same_host( $stamp_uri, $accept['actor'] ?? '' ) ) {
			return;
		}

		// Uncached: a stamp is fetched once, right when it is presented, never served stale.
		$stamp = Http::get_remote_object( $stamp_uri, false );

		/*
		 * The stamp must bind exactly this quote post to exactly this quoted object and be
		 * issued by the quoted author. Anything else is not an authorization for us.
		 */
		if (
			\is_wp_error( $stamp ) ||
			'QuoteAuthorization' !== ( $stamp['type'] ?? '' ) ||
			object_to_uri( $stamp['interactingObject'] ?? '' ) !== get_object_id( $post ) ||
			object_to_uri( $stamp['interactionTarget'] ?? '' ) !== $quoted_uri ||
			! is_same_actor( $stamp['attributedTo'] ?? '', $accept['actor'] ?? '' )
		) {
			/**
			 * Fires when an Accept carried a stamp that does not authorize this quote post.
			 *
			 * @since unreleased
			 *
			 * @param int    $post_id   The quoting post ID.
			 * @param string $stamp_uri The stamp URI from the Accept.
			 * @param array  $accept    The Accept activity.
			 */
			\do_action( 'activitypub_quote_authorization_invalid', $post->ID, $stamp_uri, $accept );
			return;
		}

		\update_post_meta( $post->ID, '_activitypub_quote_authorization', $stamp_uri );
		\delete_post_meta( $post->ID, '_activitypub_quote_rejected' );

		add_to_outbox( $post, 'Update', $post->post_author );

		/**
		 * Fires after the quoted author's QuoteAuthorization stamp was stored on a local quote post.
		 *
		 * @since unreleased
		 *
		 * @param int    $post_id   The quoting post ID.
		 * @param string $stamp_uri The stamp URI.
		 * @param array  $accept    The Accept activity.
		 */
		\do_action( 'activitypub_quote_authorized', $post->ID, $stamp_uri, $accept );
	}

	/**
	 * Only the quoted object's author may answer our QuoteRequest.
	 *
	 * @since unreleased
	 *
	 * @param array  $accept     The activity-object.
	 * @param string $quoted_uri The quoted object URI.
	 *
	 * @return bool True if the sender is the quoted author.
	 */
	private static function quoted_author_matches( $accept, $quoted_uri ) {
		if ( ! $quoted_uri ) {
			return false;
		}

		$quoted = Http::get_remote_object( $quoted_uri );

		if ( \is_wp_error( $quoted ) || empty( $quoted['attributedTo'] ) ) {
			return false;
		}

		return is_same_actor( $accept['actor'] ?? '', $quoted['attributedTo'] );
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

		if ( 'Accept' !== $activity['type'] ) {
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
