<?php
/**
 * Post scheduler class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Scheduler;

use function Activitypub\add_to_outbox;
use function Activitypub\is_post_disabled;
use function Activitypub\get_wp_object_state;

/**
 * Post scheduler class.
 */
class Post {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// Post transitions.
		\add_action( 'transition_post_status', array( self::class, 'schedule_post_activity' ), 33, 3 );
		\add_action(
			'edit_attachment',
			function ( $post_id ) {
				self::schedule_post_activity( 'publish', 'publish', $post_id );
			}
		);
		\add_action(
			'add_attachment',
			function ( $post_id ) {
				self::schedule_post_activity( 'publish', '', $post_id );
			}
		);
		\add_action(
			'delete_attachment',
			function ( $post_id ) {
				self::schedule_post_activity( 'trash', '', $post_id );
			}
		);
	}

	/**
	 * Schedule Activities.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public static function schedule_post_activity( $new_status, $old_status, $post ) {
		if ( is_post_disabled( $post ) ) {
			return;
		}

		// Do not send activities if post is password protected.
		if ( \post_password_required( $post ) ) {
			return;
		}

		switch ( $new_status ) {
			case 'publish':
				$type = ( 'publish' === $old_status ) ? 'Update' : 'Create';
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

		// Get the content visibility.
		$content_visibility = \get_post_meta( $post->ID, 'activitypub_content_visibility', true );

		// Add the post to the outbox.
		add_to_outbox( $post, $type, $post->post_author, $content_visibility );
	}
}
