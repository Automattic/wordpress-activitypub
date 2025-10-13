<?php
/**
 * Inbox collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;
use Activitypub\Comment;

use function Activitypub\is_activity_public;
use function Activitypub\object_to_uri;

/**
 * ActivityPub Inbox Collection
 *
 * @link https://www.w3.org/TR/activitypub/#inbox
 */
class Inbox {
	/**
	 * The post type for the objects.
	 *
	 * @var string
	 */
	const POST_TYPE = 'ap_inbox';

	/**
	 * Add an activity to the inbox.
	 *
	 * @param Activity|\WP_Error $activity The Activity object.
	 * @param int                $user_id  The id of the local blog-user.
	 *
	 * @return false|int|\WP_Error The added item or an error.
	 */
	public static function add( $activity, $user_id ) {
		if ( \is_wp_error( $activity ) ) {
			return $activity;
		}

		$item = self::get( $activity->get_id(), $user_id );

		// Check for duplicate activity.
		if ( $item instanceof \WP_Post ) {
			return $item->ID;
		}

		$title      = self::get_object_title( $activity->get_object() );
		$visibility = is_activity_public( $activity ) ? ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC : ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE;

		$inbox_item = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => sprintf(
				/* translators: 1. Activity type, 2. Object Title or Excerpt */
				\__( '[%1$s] %2$s', 'activitypub' ),
				$activity->get_type(),
				\wp_trim_words( $title, 5 )
			),
			'post_content' => wp_slash( $activity->to_json() ),
			// ensure that user ID is not below 0.
			'post_author'  => \max( $user_id, 0 ),
			'post_status'  => 'publish',
			'guid'         => $activity->get_id(),
			'meta_input'   => array(
				'_activitypub_object_id'             => object_to_uri( $activity->get_object() ),
				'_activitypub_activity_type'         => $activity->get_type(),
				'_activitypub_activity_actor'        => Actors::get_type_by_id( $user_id ),
				'_activitypub_activity_remote_actor' => object_to_uri( $activity->get_actor() ),
				'activitypub_content_visibility'     => $visibility,
			),
		);

		$has_kses = false !== \has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $has_kses ) {
			// Prevent KSES from corrupting JSON in post_content.
			\kses_remove_filters();
		}

		$id = \wp_insert_post( $inbox_item, true );

		if ( $has_kses ) {
			\kses_init_filters();
		}

		return $id;
	}

	/**
	 * Get the title of an activity recursively.
	 *
	 * @param Activity|Base_Object $activity_object The activity object.
	 *
	 * @return string The title.
	 */
	private static function get_object_title( $activity_object ) {
		if ( ! $activity_object ) {
			return '';
		}

		if ( \is_string( $activity_object ) ) {
			$post_id = \url_to_postid( $activity_object );

			return $post_id ? \get_the_title( $post_id ) : '';
		}

		// phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$title = $activity_object->get_name() ?: $activity_object->get_content();

		if ( ! $title && $activity_object->get_object() instanceof Base_Object ) {
			// phpcs:ignore Universal.Operators.DisallowShortTernary.Found
			$title = $activity_object->get_object()->get_name() ?: $activity_object->get_object()->get_content();
		}

		return $title;
	}

	/**
	 * Get the inbox item by activity id.
	 *
	 * @param string $guid    The activity id.
	 * @param int    $user_id The id of the local blog-user.
	 *
	 * @return array|\WP_Error|\WP_Post The inbox item or an error.
	 */
	public static function get( $guid, $user_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE guid=%s AND post_author=%d AND post_type=%s",
				\esc_url_raw( $guid ),
				\absint( $user_id ),
				self::POST_TYPE
			)
		);

		if ( ! $post_id ) {
			return new \WP_Error(
				'activitypub_inbox_item_not_found',
				\__( 'Inbox item not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		return \get_post( $post_id );
	}

	/**
	 * Get an inbox item by its GUID.
	 *
	 * @param string $guid The GUID of the inbox item.
	 *
	 * @return \WP_Post|\WP_Error The inbox item or WP_Error.
	 */
	public static function get_by_guid( $guid ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE guid=%s AND post_type=%s",
				\esc_url( $guid ),
				self::POST_TYPE
			)
		);

		if ( ! $post_id ) {
			return new \WP_Error(
				'activitypub_inbox_item_not_found',
				\__( 'Inbox item not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		return \get_post( $post_id );
	}

	/**
	 * Undo a received activity.
	 *
	 * @param string $id The ID of the inbox item to be removed.
	 *
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public static function undo( $id ) {
		$inbox_item = self::get_by_guid( $id );

		if ( \is_wp_error( $inbox_item ) ) {
			// If inbox entry not found, return the error.
			return $inbox_item;
		}

		$type = \get_post_meta( $inbox_item->ID, '_activitypub_activity_type', true );

		switch ( $type ) {
			case 'Follow':
				$actor        = \get_post_meta( $inbox_item->ID, '_activitypub_activity_remote_actor', true );
				$remote_actor = Remote_Actors::get_by_uri( $actor );

				if ( \is_wp_error( $remote_actor ) ) {
					return $remote_actor;
				}

				return Followers::remove( $remote_actor, $inbox_item->post_author );

			case 'Like':
			case 'Create':
			case 'Announce':
				if ( ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS ) {
					return new \WP_Error(
						'activitypub_inbox_undo_interactions_disabled',
						\__( 'Undo is not possible because incoming interactions are disabled.', 'activitypub' ),
						array( 'status' => 403 )
					);
				}

				$result = Comment::object_id_to_comment( esc_url_raw( $inbox_item->guid ) );

				if ( empty( $result ) ) {
					return new \WP_Error(
						'activitypub_inbox_undo_comment_not_found',
						\__( 'Undo is not possible because the comment was not found.', 'activitypub' ),
						array( 'status' => 404 )
					);
				}

				return \wp_delete_comment( $result, true );

			default:
				return new \WP_Error(
					'activitypub_inbox_undo_unsupported',
					// Translators: %s is the activity type.
					\sprintf( \__( 'Undo is not supported for %s activities.', 'activitypub' ), $type ),
					array( 'status' => 400 )
				);
		}
	}
}
