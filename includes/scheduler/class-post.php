<?php
/**
 * Post scheduler class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Scheduler;

use Activitypub\Scheduler;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Actors;
use Activitypub\Transformer\Factory;

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

		// Attachment transitions.
		\add_action( 'add_attachment', array( self::class, 'transition_attachment_status' ) );
		\add_action( 'edit_attachment', array( self::class, 'transition_attachment_status' ) );
		\add_action( 'delete_attachment', array( self::class, 'transition_attachment_status' ) );

		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'send_announces' ), 10, 4 );
	}

	/**
	 * Schedules Activities for attachment transitions.
	 *
	 * @param int $post_id Attachment ID.
	 */
	public static function transition_attachment_status( $post_id ) {
		switch ( current_action() ) {
			case 'add_attachment':
				self::schedule_post_activity( 'publish', '', $post_id );
				break;
			case 'edit_attachment':
				self::schedule_post_activity( 'publish', 'publish', $post_id );
				break;
			case 'delete_attachment':
				self::schedule_post_activity( 'trash', '', $post_id );
				break;
		}
	}

	/**
	 * Schedule Activities.
	 *
	 * @param string       $new_status New post status.
	 * @param string       $old_status Old post status.
	 * @param int|\WP_Post $post       Post ID or post object.
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

	/**
	 * Send announces.
	 *
	 * @param int   $outbox_activity_id The outbox activity ID.
	 * @param array $activity_object    The activity object.
	 * @param int   $actor_id           The actor ID.
	 * @param int   $content_visibility The content visibility.
	 */
	public static function send_announces( $outbox_activity_id, $activity_object, $actor_id, $content_visibility ) {
		// Only if we're in both Blog and User modes.
		if ( ACTIVITYPUB_ACTOR_AND_BLOG_MODE !== \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) ) {
			return;
		}

		// Only if this isn't the Blog Actor.
		if ( Actors::BLOG_USER_ID === $actor_id ) {
			return;
		}

		// Only if the content is public or quiet public.
		if ( ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC !== $content_visibility ) {
			return;
		}

		$activity_type = \get_post_meta( $outbox_activity_id, '_activitypub_activity_type', true );

		// Only if the activity is a Create, Update or Delete.
		if ( ! in_array( $activity_type, array( 'Create', 'Update', 'Delete' ), true ) ) {
			return;
		}

		// Check if the object is an article, image, audio, video, event or document and ignore profile updates and other activities.
		if ( ! in_array( $activity_object->get_type(), array( 'Note', 'Article', 'Image', 'Audio', 'Video', 'Event', 'Document' ), true ) ) {
			return;
		}

		$transformer = Factory::get_transformer( $activity_object );
		$activity    = $transformer->to_activity( $activity_type );

		$outbox_activity_id = Outbox::add( $activity, 'Announce', Actors::BLOG_USER_ID, ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );

		if ( ! $outbox_activity_id ) {
			return false;
		}

		// Schedule the outbox item for federation.
		Scheduler::schedule_outbox_activity_for_federation( $outbox_activity_id );
	}
}
