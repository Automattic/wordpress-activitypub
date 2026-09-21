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
}
