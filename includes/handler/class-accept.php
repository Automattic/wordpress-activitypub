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
use Activitypub\Proxy;
use Activitypub\Schema\Registry;

use function Activitypub\add_to_outbox;
use function Activitypub\get_object_id;
use function Activitypub\is_same_actor;
use function Activitypub\is_same_host;
use function Activitypub\object_to_id;

/**
 * Handle Accept requests.
 */
class Accept {
	/**
	 * Initialize the class, registering WordPress hooks.
	 *
	 * @return void
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_accept', array( self::class, 'handle_accept' ), 10, 2 );
	}

	/**
	 * Handles "Accept" requests.
	 *
	 * @param array     $accept   The activity-object.
	 * @param int|int[] $user_ids The id of the local blog-user.
	 *
	 * @return void
	 */
	public static function handle_accept( $accept, $user_ids ) {
		// Validate that there is a preceding Activity of ours.
		$outbox_post = Outbox::get_by_guid( $accept['object']['id'] ?? '' );

		if ( \is_wp_error( $outbox_post ) ) {
			return;
		}

		switch ( \get_post_meta( $outbox_post->ID, '_activitypub_activity_type', true ) ) {
			case 'Follow':
				self::accept_follow( $accept, $user_ids );
				break;
			case 'QuoteRequest':
				self::accept_quote_request( $accept, $outbox_post, $user_ids );
				break;
			default:
				break;
		}
	}

	/**
	 * Accept a "Follow" of ours: confirm the relationship with the followed actor.
	 *
	 * @param array     $accept   The activity-object.
	 * @param int[]|int $user_ids The local user IDs.
	 *
	 * @return void
	 */
	private static function accept_follow( $accept, $user_ids ) {
		/*
		 * The sender must be the actor that was followed. Without this, a signed Accept
		 * from one actor could confirm a Follow that targeted another actor by
		 * referencing that pending Follow's outbox GUID.
		 */
		if ( ! is_same_actor( $accept['actor'] ?? '', $accept['object']['object'] ?? '' ) ) {
			return;
		}

		$actor_post = Remote_Actors::get_by_uri( object_to_id( $accept['object']['object'] ?? '' ) );

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
	 * @since 9.4.0
	 *
	 * @param array     $accept      The activity-object.
	 * @param \WP_Post  $outbox_post Our QuoteRequest outbox item.
	 * @param int[]|int $user_ids    The local user IDs.
	 *
	 * @return void
	 */
	private static function accept_quote_request( $accept, $outbox_post, $user_ids ) {
		$request = Outbox::get_activity( $outbox_post );

		if ( \is_wp_error( $request ) || ! $request->get_instrument() ) {
			return;
		}

		// The request's `object` is the quoted URI; our post is its `instrument`.
		$post_id = \url_to_postid( object_to_id( $request->get_instrument() ) );
		$post    = $post_id ? \get_post( $post_id ) : null;

		if ( ! $post ) {
			return;
		}

		$quoted_uri = object_to_id( $request->get_object() );

		// An Accept for a request the post has since superseded with another quoted URL is ignored.
		if ( \get_post_meta( $post->ID, '_activitypub_quote_request', true ) !== $quoted_uri ) {
			return;
		}

		$stamp_uri = object_to_id( $accept['result'] ?? '' );

		if ( ! $stamp_uri ) {
			return;
		}

		// The stamp is issued by the quoted author, so it must live on the sender's host.
		if ( ! is_same_host( $stamp_uri, $accept['actor'] ?? '' ) ) {
			return;
		}

		// Fetches the quoted object, so it runs after the free checks above.
		$quoted = Proxy::get( $quoted_uri );

		if ( \is_wp_error( $quoted ) || empty( $quoted['attributedTo'] ) ) {
			return;
		}

		// Only the quoted object's author may answer our QuoteRequest.
		if ( ! is_same_actor( $accept['actor'] ?? '', $quoted['attributedTo'] ) ) {
			return;
		}

		// Uncached: a stamp is fetched once, right when it is presented, never served stale.
		$stamp = Proxy::get(
			$stamp_uri,
			array(
				'cached' => false,
				'ttl'    => 0,
			)
		);

		/*
		 * The stamp must bind exactly this quote post to exactly this quoted object and be
		 * issued by the quoted author. Anything else is not an authorization for us.
		 */
		if (
			\is_wp_error( $stamp ) ||
			'QuoteAuthorization' !== ( $stamp['type'] ?? '' ) ||
			object_to_id( $stamp['interactingObject'] ?? '' ) !== get_object_id( $post ) ||
			// The stored URL is what the author pasted into the block; the stamp names the canonical object id.
			! \in_array( object_to_id( $stamp['interactionTarget'] ?? '' ), array( $quoted_uri, object_to_id( $quoted['id'] ?? '' ) ), true ) ||
			! is_same_actor( $stamp['attributedTo'] ?? '', $accept['actor'] ?? '' )
		) {
			/** This action is documented in includes/handler/class-accept.php */
			\do_action( 'activitypub_handled_accept', $accept, (array) $user_ids, false, $post );

			return;
		}

		\update_post_meta( $post->ID, '_activitypub_quote_authorization', $stamp_uri );
		\delete_post_meta( $post->ID, '_activitypub_quote_rejected' );

		add_to_outbox( $post, 'Update', (int) $post->post_author );

		/** This action is documented in includes/handler/class-accept.php */
		\do_action( 'activitypub_handled_accept', $accept, (array) $user_ids, true, $post );
	}

	/**
	 * Validate the object of an activity.
	 *
	 * @deprecated unreleased The schema of the type checks the activity in the inbox, see `Activitypub\Schema\Accept`.
	 *
	 * @param bool             $valid   The validation result.
	 * @param array            $param   The object data.
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return bool The validation result: for a Accept, what its schema says.
	 */
	public static function validate_object( $valid, $param, $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable, Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- The signature of the removed check.
		\_deprecated_function( __METHOD__, 'unreleased' );

		$activity = $request->get_json_params();

		if ( empty( $activity['type'] ) ) {
			return false;
		}

		if ( 'Accept' !== $activity['type'] ) {
			return $valid;
		}

		return $valid && true === Registry::get( $activity )->validate();
	}
}
