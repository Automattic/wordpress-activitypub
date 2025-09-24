<?php
/**
 * Post scheduler class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Scheduler;

use function Activitypub\add_to_outbox;
use function Activitypub\get_wp_object_state;
use function Activitypub\is_post_disabled;

/**
 * Post scheduler class.
 */
class Post {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// Post transitions.
		\add_action( 'wp_after_insert_post', array( self::class, 'schedule_post_activity' ), 33, 4 );

		// Attachment transitions.
		\add_action( 'add_attachment', array( self::class, 'transition_attachment_status' ) );
		\add_action( 'edit_attachment', array( self::class, 'transition_attachment_status' ) );
		\add_action( 'delete_attachment', array( self::class, 'transition_attachment_status' ) );
	}

	/**
	 * Handle post updates and determine the appropriate Activity type.
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post        Post object.
	 * @param bool     $update      Whether this is an existing post being updated.
	 * @param \WP_Post $post_before Post object before the update.
	 */
	public static function schedule_post_activity( $post_id, $post, $update, $post_before ) {
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}

		if ( is_post_disabled( $post ) ) {
			return;
		}

		// Bail on bulk edits, unless post author or post status changed.
		if ( isset( $_REQUEST['bulk_edit'] ) && ( ! isset( $_REQUEST['post_author'] ) || -1 === (int) $_REQUEST['post_author'] ) && -1 === (int) $_REQUEST['_status'] ) { // phpcs:ignore WordPress
			return;
		}

		$new_status = get_post_status( $post );
		$old_status = $post_before ? get_post_status( $post_before ) : null;

		switch ( $new_status ) {
			case 'publish':
				if ( $update ) {
					$type = ( 'publish' === $old_status ) ? 'Update' : 'Create';
				} else {
					$type = 'Create';
				}
				break;

			case 'draft':
				$type = ( 'publish' === $old_status ) ? 'Update' : false;
				break;

			case 'trash':
				$type = 'federated' === get_wp_object_state( $post ) ? 'Delete' : false;
				break;

			default:
				$type = false;
		}

		// Do not send Activities if `$type` is not set or unknown.
		if ( empty( $type ) ) {
			return;
		}

		// If the post was not federated before but is an Update activity, it should be a Create activity.
		if ( get_wp_object_state( $post ) !== 'federated' && 'Update' === $type ) {
			$type = 'Create';
		}

		// Add the post to the outbox.
		add_to_outbox( $post, $type, $post->post_author );
	}

	/**
	 * Schedules Activities for attachment transitions.
	 *
	 * @param int $post_id Attachment ID.
	 */
	public static function transition_attachment_status( $post_id ) {
		if ( \defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}

		if ( ! \post_type_supports( 'attachment', 'activitypub' ) ) {
			return;
		}

		$post = \get_post( $post_id );

		switch ( \current_action() ) {
			case 'add_attachment':
				// Add the post to the outbox.
				add_to_outbox( $post, 'Create', $post->post_author );
				break;
			case 'edit_attachment':
				// Update the post to the outbox.
				add_to_outbox( $post, 'Update', $post->post_author );
				break;
			case 'delete_attachment':
				// Delete the post from the outbox.
				add_to_outbox( $post, 'Delete', $post->post_author );
				break;
		}
	}
}
