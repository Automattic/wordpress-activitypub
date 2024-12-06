<?php
/**
 * Comment scheduler class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Scheduler;

use function Activitypub\set_wp_object_state;
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
		\add_action(
			'edit_comment',
			function ( $comment_id ) {
				self::schedule_comment_activity( 'approved', 'approved', $comment_id );
			}
		);
		\add_action(
			'wp_insert_comment',
			function ( $comment_id ) {
				self::schedule_comment_activity( 'approved', '', $comment_id );
			}
		);
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

		$hook = 'activitypub_send_comment';
		$args = array( $comment->comment_ID, $type );

		if ( false === wp_next_scheduled( $hook, $args ) ) {
			set_wp_object_state( $comment, 'federate' );
			\wp_schedule_single_event( \time() + 10, $hook, $args );
		}
	}
}
