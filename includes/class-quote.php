<?php
/**
 * Quote class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;
use Activitypub\Transformer\Post;

/**
 * Outgoing side of FEP-044f quote posts.
 *
 * Sends the QuoteRequest for local quote posts and records the quoted author's
 * Accept (stamp), Reject, or later revocation on the quoting post.
 *
 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/044f/fep-044f.md
 * @since unreleased
 */
class Quote {
	/**
	 * Register hooks.
	 *
	 * @since unreleased
	 */
	public static function init() {
		\add_action( 'activitypub_handled_outbox_create', array( self::class, 'maybe_send_request' ), 10, 4 );
		\add_action( 'activitypub_handled_outbox_update', array( self::class, 'maybe_send_request' ), 10, 4 );
	}

	/**
	 * Send a QuoteRequest for a quote post that has none in flight.
	 *
	 * @since unreleased
	 *
	 * @param array    $data      The Create/Update activity as array.
	 * @param int      $user_id   The local user ID.
	 * @param Activity $activity  The Activity object.
	 * @param int      $outbox_id The outbox item ID.
	 */
	public static function maybe_send_request( $data, $user_id, $activity, $outbox_id ) {
		if ( empty( $data['object']['quote'] ) ) {
			return;
		}

		$post = self::get_post_from_outbox_item( $outbox_id );

		if ( ! $post ) {
			return;
		}

		$quoted_uri = $data['object']['quote'];

		$pending = \get_post_meta( $post->ID, '_activitypub_quote_request', true );
		if ( $pending ) {
			$request_item = Outbox::get_by_guid( $pending );
			// A request for this very URL is already out; a changed URL starts a new handshake.
			if ( ! \is_wp_error( $request_item ) && self::get_request_object( $request_item ) === $quoted_uri ) {
				return;
			}
			self::reset( $post->ID );
		} elseif ( \get_post_meta( $post->ID, '_activitypub_quote_rejected', true ) || \get_post_meta( $post->ID, '_activitypub_quote_authorization', true ) ) {
			// No request in flight to supersede: a settled stamp or rejection stands until the meta is cleared elsewhere.
			return;
		}

		$quoted = Http::get_remote_object( $quoted_uri );
		if ( \is_wp_error( $quoted ) || empty( $quoted['attributedTo'] ) ) {
			return;
		}

		$quoted_author = object_to_uri( $quoted['attributedTo'] );
		$actor         = Actors::get_by_id( $user_id );

		if ( \is_wp_error( $actor ) ) {
			return;
		}

		// Self-quotes need no consent (FEP-044f section "Verification").
		if ( is_same_actor( $actor->get_id(), $quoted_author ) ) {
			return;
		}

		$request = new Activity();
		$request->set_type( 'QuoteRequest' );
		$request->set_actor( $actor->get_id() );
		$request->set_object( $quoted_uri );
		$request->set_instrument( $data['object']['id'] );
		$request->add_to( $quoted_author );

		$request_id = add_to_outbox( $request, null, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );

		if ( ! $request_id ) {
			return;
		}

		\update_post_meta( $post->ID, '_activitypub_quote_request', \get_the_guid( $request_id ) );

		/**
		 * Fires after a QuoteRequest for a local quote post was added to the outbox.
		 *
		 * @since unreleased
		 *
		 * @param int    $post_id    The quoting post ID.
		 * @param string $quoted_uri The quoted object URI.
		 * @param int    $request_id The QuoteRequest outbox item ID.
		 */
		\do_action( 'activitypub_sent_quote_request', $post->ID, $quoted_uri, $request_id );
	}

	/**
	 * Forget request, stamp and rejection so a new handshake can start.
	 *
	 * @since unreleased
	 *
	 * @param int $post_id The quoting post ID.
	 */
	public static function reset( $post_id ) {
		\delete_post_meta( $post_id, '_activitypub_quote_request' );
		\delete_post_meta( $post_id, '_activitypub_quote_authorization' );
		\delete_post_meta( $post_id, '_activitypub_quote_rejected' );
	}

	/**
	 * Resolve the local post an outbox item was created for.
	 *
	 * @since unreleased
	 *
	 * @param int $outbox_id The outbox item ID.
	 *
	 * @return \WP_Post|null The post or null.
	 */
	private static function get_post_from_outbox_item( $outbox_id ) {
		$object_id = \get_post_meta( $outbox_id, '_activitypub_object_id', true );
		$post_id   = $object_id ? \url_to_postid( $object_id ) : 0;

		return $post_id ? \get_post( $post_id ) : null;
	}

	/**
	 * Read the quoted URI out of a stored QuoteRequest outbox item.
	 *
	 * @since unreleased
	 *
	 * @param \WP_Post $request_item The outbox item.
	 *
	 * @return string The `object` of the request or an empty string.
	 */
	private static function get_request_object( $request_item ) {
		$activity = \json_decode( $request_item->post_content, true );

		return isset( $activity['object'] ) ? object_to_uri( $activity['object'] ) : '';
	}

	/**
	 * Record the quoted author's Accept: verify and store the QuoteAuthorization stamp.
	 *
	 * @since unreleased
	 *
	 * @param array    $accept       The Accept activity.
	 * @param \WP_Post $request_item Our QuoteRequest outbox item.
	 */
	public static function handle_accept( $accept, $request_item ) {
		$post = self::get_post_from_request_item( $request_item );

		if ( ! $post || \get_post_meta( $post->ID, '_activitypub_quote_request', true ) !== $request_item->guid ) {
			return;
		}

		$quoted_uri = self::get_request_object( $request_item );

		if ( ! self::verify_sender( $accept, $quoted_uri ) ) {
			return;
		}

		$stamp_uri = isset( $accept['result'] ) ? object_to_uri( $accept['result'] ) : '';

		if ( ! $stamp_uri ) {
			return;
		}

		// Uncached: a stamp is fetched once, right when it is presented, never served stale.
		$stamp    = Http::get_remote_object( $stamp_uri, false );
		$post_uri = self::get_object_id( $post );

		/*
		 * The stamp must bind exactly this quote post to exactly this quoted object and be
		 * issued by the quoted author. Anything else is not an authorization for us.
		 */
		if (
			\is_wp_error( $stamp ) ||
			'QuoteAuthorization' !== ( $stamp['type'] ?? '' ) ||
			object_to_uri( $stamp['interactingObject'] ?? '' ) !== $post_uri ||
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
	}

	/**
	 * The ActivityPub ID of a local post.
	 *
	 * @since unreleased
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return string The ID.
	 */
	private static function get_object_id( $post ) {
		return Post::transform( $post )->to_id();
	}

	/**
	 * Record the quoted author's Reject: the quote part is dropped from the post.
	 *
	 * @since unreleased
	 *
	 * @param array    $reject       The Reject activity.
	 * @param \WP_Post $request_item Our QuoteRequest outbox item.
	 */
	public static function handle_reject( $reject, $request_item ) {
		$post = self::get_post_from_request_item( $request_item );

		if ( ! $post || \get_post_meta( $post->ID, '_activitypub_quote_request', true ) !== $request_item->guid ) {
			return;
		}

		if ( ! self::verify_sender( $reject, self::get_request_object( $request_item ) ) ) {
			return;
		}

		\update_post_meta( $post->ID, '_activitypub_quote_rejected', '1' );
		\delete_post_meta( $post->ID, '_activitypub_quote_authorization' );

		add_to_outbox( $post, 'Update', $post->post_author );
	}

	/**
	 * Resolve the local quote post a stored QuoteRequest was sent for.
	 *
	 * The outbox stores the quoted URI as the item's object id; our post is the `instrument`.
	 *
	 * @since unreleased
	 *
	 * @param \WP_Post $request_item The QuoteRequest outbox item.
	 *
	 * @return \WP_Post|null The quoting post or null.
	 */
	private static function get_post_from_request_item( $request_item ) {
		$activity = \json_decode( $request_item->post_content, true );
		$post_id  = isset( $activity['instrument'] ) ? \url_to_postid( object_to_uri( $activity['instrument'] ) ) : 0;

		return $post_id ? \get_post( $post_id ) : null;
	}

	/**
	 * Only the quoted object's author may answer our QuoteRequest.
	 *
	 * @since unreleased
	 *
	 * @param array  $activity   The Accept or Reject.
	 * @param string $quoted_uri The quoted object URI.
	 *
	 * @return bool True if the sender is the quoted author.
	 */
	private static function verify_sender( $activity, $quoted_uri ) {
		if ( ! $quoted_uri ) {
			return false;
		}

		$quoted = Http::get_remote_object( $quoted_uri );

		if ( \is_wp_error( $quoted ) || empty( $quoted['attributedTo'] ) ) {
			return false;
		}

		return is_same_actor( $activity['actor'] ?? '', $quoted['attributedTo'] );
	}
}
