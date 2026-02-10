<?php
/**
 * Post scheduler class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Scheduler;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;

use function Activitypub\add_to_outbox;
use function Activitypub\get_content_visibility;
use function Activitypub\get_post_id;
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
		\add_action( 'wp_after_insert_post', array( self::class, 'triage' ), 33, 4 );

		// Attachment transitions.
		\add_action( 'add_attachment', array( self::class, 'transition_attachment_status' ) );
		\add_action( 'edit_attachment', array( self::class, 'transition_attachment_status' ) );
		\add_action( 'delete_attachment', array( self::class, 'transition_attachment_status' ) );

		/*
		 * Sticky post transitions (featured collection).
		 *
		 * Note: These hooks run in addition to the legacy sticky hooks in
		 * Actor scheduler, which send an Update activity when a post becomes
		 * sticky or is unstuck. This means a sticky/unsticky event will cause both:
		 * - an Add/Remove activity for the Actor's featured collection (below), and
		 * - an Update activity (from Actor scheduler).
		 * The Update activity is kept for backwards compatibility.
		 */
		\add_action( 'post_stuck', array( self::class, 'schedule_featured_add' ) );
		\add_action( 'post_unstuck', array( self::class, 'schedule_featured_remove' ) );
	}

	/**
	 * Triage post transitions and determine the appropriate Activity type.
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post        Post object.
	 * @param bool     $update      Whether this is an existing post being updated.
	 * @param \WP_Post $post_before Post object before the update.
	 */
	public static function triage( $post_id, $post, $update, $post_before ) {
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

		$new_status    = get_post_status( $post );
		$old_status    = $post_before ? get_post_status( $post_before ) : null;
		$object_status = get_wp_object_state( $post );

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
				$type = ACTIVITYPUB_OBJECT_STATE_FEDERATED === $object_status ? 'Delete' : false;
				break;

			default:
				$type = false;
		}

		// Do not send Activities if `$type` is not set or unknown.
		if ( empty( $type ) ) {
			return;
		}

		// If the post was never federated before, it should be a Create activity.
		if ( empty( $object_status ) && 'Update' === $type ) {
			$type = 'Create';
		}

		// If the post was federated before but is now local or private, it should be a Delete activity.
		if ( ACTIVITYPUB_OBJECT_STATE_FEDERATED === $object_status && in_array( get_content_visibility( $post ), array( ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE ), true ) ) {
			$type = 'Delete';
		}

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

		if ( is_post_disabled( $post_id ) ) {
			return;
		}

		$post = \get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		switch ( \current_action() ) {
			case 'add_attachment':
				$type = 'Create';
				break;
			case 'edit_attachment':
				$type = 'Update';
				break;
			case 'delete_attachment':
				$type = 'Delete';
				break;
			default:
				return;
		}

		add_to_outbox( $post, $type, $post->post_author );
	}

	/**
	 * Schedule an Add activity when a post is added to the featured collection.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function schedule_featured_add( $post_id ) {
		self::schedule_featured_update( $post_id, 'Add' );
	}

	/**
	 * Schedule a Remove activity when a post is removed from the featured collection.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function schedule_featured_remove( $post_id ) {
		self::schedule_featured_update( $post_id, 'Remove' );
	}

	/**
	 * Schedule an Add or Remove activity for the featured collection.
	 *
	 * When a post's sticky status changes, this sends an Add or Remove activity
	 * to notify followers about the change to the actor's featured collection.
	 *
	 * @see https://github.com/Automattic/wordpress-activitypub/issues/2795
	 *
	 * @param int    $post_id       The post ID.
	 * @param string $activity_type The activity type ('Add' or 'Remove').
	 */
	private static function schedule_featured_update( $post_id, $activity_type ) {
		if ( \defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}

		$post = \get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		if ( is_post_disabled( $post ) ) {
			return;
		}

		$actor = Actors::get_by_id( $post->post_author );

		if ( ! $actor || \is_wp_error( $actor ) ) {
			return;
		}

		$activity = new Activity();
		$activity->set_type( $activity_type );
		$activity->set_actor( $actor->get_id() );
		$activity->set_object( get_post_id( $post->ID ) );
		$activity->set_target( $actor->get_featured() );

		add_to_outbox( $activity, null, $post->post_author );
	}
}
