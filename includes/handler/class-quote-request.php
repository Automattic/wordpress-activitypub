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
use Activitypub\Collection\Inbox;
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
		\add_action( 'delete_comment', array( self::class, 'handle_quote_delete' ), 10, 2 );

		\add_filter( 'activitypub_validate_object', array( self::class, 'validate_object' ), 10, 3 );
	}

	/**
	 * Handle QuoteRequest activities.
	 *
	 * @param array     $activity The activity object.
	 * @param int|int[] $user_ids The user ID(s).
	 */
	public static function handle_quote_request( $activity, $user_ids ) {
		// Extract the user ID (quote requests are always for a single user).
		$user_id = \is_array( $user_ids ) ? \reset( $user_ids ) : $user_ids;

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
		 * @param int[]  $user_ids       The local user IDs.
		 * @param bool   $success        True on success, false otherwise.
		 * @param string $content_policy The content policy for the quoted post.
		 */
		\do_action( 'activitypub_handled_quote_request', $activity, (array) $user_ids, $state, $content_policy );
	}

	/**
	 * ActivityPub inbox disallowed activity.
	 *
	 * @param array          $activity The activity array.
	 * @param int|int[]|null $user_ids The user ID(s).
	 * @param string         $type     The type of the activity.
	 */
	public static function handle_blocked_request( $activity, $user_ids, $type ) {
		if ( 'quoterequest' !== \strtolower( $type ) ) {
			return;
		}

		// Extract the user ID (quote requests are always for a single user).
		$user_id = \is_array( $user_ids ) ? \reset( $user_ids ) : $user_ids;

		self::queue_reject( $activity, $user_id );
	}

	/**
	 * Handle deletion of a quote comment.
	 *
	 * When a local quote comment is deleted, send a Reject activity to revoke
	 * the previously accepted QuoteRequest.
	 *
	 * @param int         $comment_id The comment ID being deleted.
	 * @param \WP_Comment $comment    The comment object.
	 */
	public static function handle_quote_delete( $comment_id, $comment ) {
		// Only handle quote comments.
		if ( 'quote' !== $comment->comment_type ) {
			return;
		}

		// Get the post being quoted.
		$post_id = $comment->comment_post_ID;
		if ( ! $post_id ) {
			return;
		}

		// Get the instrument URL (the quote post URL) from comment meta.
		$instrument_url = \get_comment_meta( $comment_id, 'source_url', true );
		if ( ! $instrument_url ) {
			$instrument_url = \get_comment_meta( $comment_id, 'source_id', true );
		}

		if ( ! $instrument_url ) {
			return;
		}

		// Get the post author (who accepted the quote).
		$post = \get_post( $post_id );
		if ( ! $post || ! $post->post_author ) {
			return;
		}

		/*
		 * Try to retrieve the original QuoteRequest from the inbox.
		 * For QuoteRequest activities, the inbox stores the instrument URL
		 * in _activitypub_object_id, so we can query by that.
		 */
		$activity_object = null;
		$inbox_item      = Inbox::get_by_type_and_object( 'QuoteRequest', $instrument_url );

		if ( $inbox_item instanceof \WP_Post ) {
			$activity_object = \json_decode( $inbox_item->post_content, true );
			if ( JSON_ERROR_NONE !== \json_last_error() ) {
				$activity_object = null;
			}
		}

		// Fallback: If inbox item not found, reconstruct from available data.
		if ( ! $activity_object ) {
			$activity_object = array(
				'type'       => 'QuoteRequest',
				'actor'      => $comment->comment_author_url,
				'object'     => \get_permalink( $post_id ),
				'instrument' => $instrument_url,
				'published'  => \gmdate( 'c' ),
			);
		}

		// Remove from _activitypub_quoted_by meta.
		\delete_post_meta( $post_id, '_activitypub_quoted_by', $instrument_url );

		// Send Reject activity to revoke the quote permission.
		self::queue_reject( $activity_object, $post->post_author );

		/**
		 * Fires after a quote comment has been deleted and Reject activity sent.
		 *
		 * @param int    $comment_id       The deleted comment ID.
		 * @param int    $post_id          The post ID that was quoted.
		 * @param string $instrument_url   The instrument URL (quote post).
		 * @param array  $activity_object  The QuoteRequest activity that was rejected.
		 */
		\do_action( 'activitypub_quote_comment_deleted', $comment_id, $post_id, $instrument_url, $activity_object );
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
		$activity = $request->get_json_params();

		if ( empty( $activity['type'] ) ) {
			return false;
		}

		if ( 'QuoteRequest' !== $activity['type'] ) {
			return $valid;
		}

		if ( ! isset( $activity['actor'], $activity['object'], $activity['instrument'] ) ) {
			return false;
		}

		return $valid;
	}
}
