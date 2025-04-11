<?php
/**
 * Comment scheduler class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Scheduler;

use Activitypub\Activity;
use Activitypub\Actors;
use Activitypub\Comments;

use function Activitypub\add_to_outbox;
use function Activitypub\should_comment_be_federated;

/**
 * Post scheduler class.
 */
class Comment {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		if ( ACTIVITYPUB_DISABLE_OUTGOING_INTERACTIONS ) {
			return;
		}

		// Comment transitions.
		\add_action( 'transition_comment_status', array( self::class, 'schedule_comment_activity' ), 20, 3 );
		\add_action( 'wp_insert_comment', array( self::class, 'schedule_comment_activity_on_insert' ), 10, 2 );
	}

	/**
	 * Schedule Comment Activities.
	 *
	 * @see transition_comment_status()
	 *
	 * @param string      $new_status New comment status.
	 * @param string      $old_status Old comment status.
	 * @param \WP_Comment $comment    Comment object.
	 */
	public static function schedule_comment_activity( $new_status, $old_status, $comment ) {
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}

		$comment = get_comment( $comment );
		if ( ! $comment ) {
			return;
		}

		if ( ! $comment->user_id ) {
			self::maybe_announce_interaction( $new_status, $old_status, $comment );
			return;
		}

		$type = false;

		if (
			'approved' === $new_status &&
			'approved' !== $old_status
		) {
			$type = 'Create';
		} elseif ( 'approved' === $new_status ) {
			$type = 'Update';
			\update_comment_meta( $comment->comment_ID, 'activitypub_comment_modified', time(), true );
		} elseif (
			'trash' === $new_status ||
			'spam' === $new_status
		) {
			$type = 'Delete';
		}

		if ( empty( $type ) ) {
			return;
		}

		// Check if comment should be federated or not.
		if ( ! should_comment_be_federated( $comment ) ) {
			return;
		}

		add_to_outbox( $comment, $type, $comment->user_id );
	}

	/**
	 * Announce an interaction.
	 *
	 * @param string      $new_status The new comment status.
	 * @param string      $old_status The old comment status.
	 * @param \WP_Comment $comment    The comment object.
	 */
	public static function maybe_announce_interaction( $new_status, $old_status, $comment ) {
		// Only if we're in both Blog and User modes.
		if ( ACTIVITYPUB_ACTOR_AND_BLOG_MODE !== \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) ) {
			return;
		}

		if ( 'approved' !== $new_status || 'approved' === $old_status ) {
			return;
		}

		if ( ! self::was_received( $comment ) ) {
			return;
		}

		// Get activity from comment meta.
		$activity = \get_comment_meta( $comment->comment_ID, '_activitypub_activity', true );

		if ( ! $activity ) {
			return;
		}

		$activity['cc'][]           = Actors::get_by_id( Actors::BLOG_USER_ID )->get_id();
		$activity['object']['cc'][] = Actors::get_by_id( Actors::BLOG_USER_ID )->get_id();

		$announce = new Activity();
		$announce->set_type( 'Announce' );
		$announce->set_object( $activity );

		add_to_outbox( $announce, null, Actors::BLOG_USER_ID, ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );
	}

	/**
	 * Schedule Comment Activities on insert.
	 *
	 * @param int         $comment_id Comment ID.
	 * @param \WP_Comment $comment    Comment object.
	 */
	public static function schedule_comment_activity_on_insert( $comment_id, $comment ) {
		if ( 1 === (int) $comment->comment_approved ) {
			self::schedule_comment_activity( 'approved', '', $comment );
		}
	}
}
