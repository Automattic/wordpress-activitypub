<?php
/**
 * Inbox collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Webfinger;
use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;

use function Activitypub\object_to_uri;
use function Activitypub\is_activity_public;

/**
 * ActivityPub Inbox Collection
 *
 * @link https://www.w3.org/TR/activitypub/#inbox
 */
class Inbox {
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

		$title      = self::get_object_title( $activity->get_object() );
		$visibility = is_activity_public( $activity ) ? ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC : ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE;

		$inbox_item = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => sprintf(
				/* translators: 1. Activity type, 2. Object Title or Excerpt */
				__( '[%1$s] %2$s', 'activitypub' ),
				$activity->get_type(),
				\wp_trim_words( $title, 5 )
			),
			'post_content' => wp_slash( $activity->to_json() ),
			// ensure that user ID is not below 0.
			'post_author'  => \max( $user_id, 0 ),
			'post_status'  => 'pending',
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

		// Update the activity ID if the post was inserted successfully.
		if ( $id && ! \is_wp_error( $id ) ) {
			$activity->set_id( \get_the_guid( $id ) );

			\wp_update_post(
				array(
					'ID'           => $id,
					'post_content' => \wp_slash( $activity->to_json() ),
				)
			);
		}

		if ( $has_kses ) {
			\kses_init_filters();
		}

		if ( \is_wp_error( $id ) ) {
			return $id;
		}

		if ( ! $id ) {
			return false;
		}

		return $id;
	}

	/**
	 * Get the title of an activity recursively.
	 *
	 * @param Base_Object $activity_object The activity object.
	 *
	 * @return string The title.
	 */
	private static function get_object_title( $activity_object ) {
		if ( ! $activity_object ) {
			return '';
		}

		if ( is_string( $activity_object ) ) {
			$post_id = url_to_postid( $activity_object );

			return $post_id ? get_the_title( $post_id ) : '';
		}

		$title = $activity_object->get_name() ?? $activity_object->get_content();

		if ( ! $title && $activity_object->get_object() instanceof Base_Object ) {
			$title = $activity_object->get_object()->get_name() ?? $activity_object->get_object()->get_content();
		}

		return $title;
	}

}
