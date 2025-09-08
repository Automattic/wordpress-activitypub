<?php
/**
 * Comment scheduler class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Scheduler;

use Activitypub\Comment as Comment_Utils;

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
		\add_action( 'delete_comment', array( self::class, 'schedule_comment_delete_activity' ), 10, 2 );
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

		// Federate only comments that are written by a registered user.
		if ( ! $comment || ! $comment->user_id ) {
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
			( 'delete' === $new_status && '' === $old_status ) || // Went through schedule_comment_delete_activity().
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

	/**
	 * Schedule Delete activity when a comment is permanently deleted.
	 *
	 * @param int         $comment_id Comment ID.
	 * @param \WP_Comment $comment    Comment object.
	 */
	public static function schedule_comment_delete_activity( $comment_id, $comment ) {
		// Only send Delete activities for comments that were previously federated.
		if ( Comment_Utils::was_sent( $comment ) ) {
			self::schedule_comment_activity( 'delete', '', $comment );
		}
	}
}
