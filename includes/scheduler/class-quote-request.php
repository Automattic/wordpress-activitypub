<?php
/**
 * Quote Request Scheduler.
 *
 * @package Activitypub
 */

namespace Activitypub\Scheduler;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Http;

use function Activitypub\add_to_outbox;
use function Activitypub\is_same_actor;
use function Activitypub\object_to_uri;

/**
 * Sends the FEP-044f QuoteRequest for local quote posts.
 *
 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/044f/fep-044f.md
 * @since unreleased
 */
class Quote_Request {
	/**
	 * Initialize the class, registering WordPress hooks.
	 *
	 * @since unreleased
	 */
	public static function init() {
		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'maybe_send_request' ), 10, 3 );
	}

	/**
	 * Send a QuoteRequest for a quote post that has none out for its current quoted URL.
	 *
	 * @since unreleased
	 *
	 * @param int      $outbox_id The outbox item ID.
	 * @param Activity $activity  The activity object.
	 * @param int      $user_id   The local user ID.
	 */
	public static function maybe_send_request( $outbox_id, $activity, $user_id ) {
		if ( ! \in_array( $activity->get_type(), array( 'Create', 'Update' ), true ) ) {
			return;
		}

		$object = $activity->get_object();

		if ( ! \is_object( $object ) ) {
			return;
		}

		$quoted_uri = $object->get_quote();
		$post_id    = \url_to_postid( object_to_uri( $object->get_id() ) );
		$post       = $post_id ? \get_post( $post_id ) : null;

		if ( ! $post ) {
			return;
		}

		$sent_for = \get_post_meta( $post->ID, '_activitypub_quote_request', true );

		// A request for this very URL is already out; its stamp or rejection also lives under this URL.
		if ( $sent_for === $quoted_uri ) {
			return;
		}

		/*
		 * The quoted URL changed, or the quote is gone: the old answer and the old request no longer
		 * apply. A declined quote is left out of the activity too, so whether a quote is still there
		 * is a question for the post, not for the activity.
		 */
		if ( $sent_for && ( $quoted_uri || ! self::has_quote( $post ) ) ) {
			\delete_post_meta( $post->ID, '_activitypub_quote_authorization' );
			\delete_post_meta( $post->ID, '_activitypub_quote_rejected' );
			\delete_post_meta( $post->ID, '_activitypub_quote_request' );
		}

		if ( ! $quoted_uri ) {
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
		$request->set_instrument( object_to_uri( $object->get_id() ) );
		$request->add_to( $quoted_author );

		$request_id = add_to_outbox( $request, null, $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );

		if ( ! $request_id ) {
			return;
		}

		\update_post_meta( $post->ID, '_activitypub_quote_request', $quoted_uri );
	}

	/**
	 * Whether the post still quotes something.
	 *
	 * A block whose URL was cleared quotes as little as a block that was deleted.
	 *
	 * @since unreleased
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return bool Whether the post has a Quote block with a URL.
	 */
	private static function has_quote( $post ) {
		foreach ( \parse_blocks( $post->post_content ) as $block ) {
			if ( 'activitypub/quote' === $block['blockName'] && ! empty( $block['attrs']['url'] ) ) {
				return true;
			}
		}

		return false;
	}
}
