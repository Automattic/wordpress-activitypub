<?php
/**
 * Outbox collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

/**
 * ActivityPub Outbox Collection
 */
class Outbox {
	const POST_TYPE = 'ap_outbox';

	/**
	 * Add an Item to the outbox.
	 *
	 * @param \Activitypub\Activity\Base_Object $activity_object The Activity-Object  to add as JSON.
	 * @param string                            $activity_type   The activity type.
	 * @param int                               $user_id         The user ID.
	 * @param string                            $visibility      Optional. The visibility of the content. Default 'public'.
	 *
	 * @return false|int|\WP_Error The added item or an error.
	 */
	public static function add( $activity_object, $activity_type, $user_id, $visibility = ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC ) { // phpcs:ignore
		switch ( $user_id ) {
			case -1:
				$actor = 'application';
				break;
			case 0:
				$actor = 'blog';
				break;
			default:
				$actor = 'user';
				break;
		}

		$outbox_item = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => $activity_object->get_id(),
			'post_content' => $activity_object->to_json(),
			// ensure that user ID is not below 0.
			'post_author'  => \max( $user_id, 0 ),
			'post_status'  => 'draft',
		);

		$has_kses = false !== \has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $has_kses ) {
			// Prevent KSES from corrupting JSON in post_content.
			\kses_remove_filters();
		}

		$id = \wp_insert_post( $outbox_item, true );

		if ( $has_kses ) {
			\kses_init_filters();
		}

		if ( ! $id || \is_wp_error( $id ) ) {
			return false;
		}

		// Set the actor type.
		\wp_set_object_terms( $id, array( $actor ), 'ap_actor' );

		// Set the activity type.
		\wp_set_object_terms( $id, array( strtolower( $activity_type ) ), 'ap_activity_type' );

		// Set the content visibility.
		\update_post_meta( $id, 'activitypub_content_visibility', $content_visibility, true );

		return $id;
	}
}
