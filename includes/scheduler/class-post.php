<?php
/**
 * Post scheduler class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Scheduler;

use Activitypub\Collection\Outbox;

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

		// Get all post types that support ActivityPub.
		$post_types = \get_post_types_by_support( 'activitypub' );

		foreach ( $post_types as $post_type ) {
			\add_filter( "rest_pre_insert_{$post_type}", array( self::class, 'rest_insert' ), 10, 2 );
		}
	}

	/**
	 * Schedules Activities for attachment transitions.
	 *
	 * @param int $post_id Attachment ID.
	 */
	public static function transition_attachment_status( $post_id ) {
		if ( ! \post_type_supports( 'attachment', 'activitypub' ) ) {
			return;
		}

		switch ( current_action() ) {
			case 'add_attachment':
				self::schedule_post_activity( 'publish', '', get_post( $post_id ) );
				break;
			case 'edit_attachment':
				self::schedule_post_activity( 'publish', 'publish', get_post( $post_id ) );
				break;
			case 'delete_attachment':
				self::schedule_post_activity( 'trash', '', get_post( $post_id ) );
				break;
		}
	}

	/**
	 * Schedule Activities.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public static function schedule_post_activity( $new_status, $old_status, $post ) {
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}

		if ( is_post_disabled( $post ) ) {
			return;
		}

		// Bail on bulk edits, unless post author or post status changed.
		if ( isset( $_REQUEST['bulk_edit'] ) && -1 === (int) $_REQUEST['post_author'] && -1 === (int) $_REQUEST['_status'] ) { // phpcs:ignore WordPress
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
				$type = 'Delete';
				break;

			default:
				$type = false;
		}

		// Do not send Activities if `$type` is not set or unknown.
		if ( empty( $type ) ) {
			return;
		}

		// Add the post to the outbox.
		add_to_outbox( $post, $type, $post->post_author );
	}

	/**
	 * Filter the post data before it is inserted via the REST API.
	 *
	 * Posts being inserted via the REST API have a different order of operations than in wp_insert_post().
	 * This filter updates post meta before the post is inserted into the database, so that the
	 * information is available by the time @see Outbox::add() runs.
	 *
	 * @param \stdClass        $post    An object representing a single post prepared for inserting or updating the database.
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \stdClass The prepared post.
	 */
	public static function rest_insert( $post, $request ) {
		$metas = $request->get_param( 'meta' );

		if ( empty( $post->ID ) || ! $metas || ! is_array( $metas ) ) {
			return $post;
		}

		foreach ( $metas as $meta_key => $meta_value ) {
			if (
				\str_starts_with( $meta_key, 'activitypub_' ) ||
				\str_starts_with( $meta_key, '_activitypub_' )
			) {
				if ( $meta_value ) {
					\update_post_meta( $post->ID, $meta_key, $meta_value );
				} else {
					\delete_post_meta( $post->ID, $meta_key );
				}
			}
		}

		return $post;
	}
}
