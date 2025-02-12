<?php
/**
 * Outbox collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Dispatcher;

use function Activitypub\is_activity;

/**
 * ActivityPub Outbox Collection
 *
 * @link https://www.w3.org/TR/activitypub/#outbox
 */
class Outbox {
	const POST_TYPE = 'ap_outbox';

	/**
	 * Add an Item to the outbox.
	 *
	 * @param \Activitypub\Activity\Base_Object $activity_object    The object of the activity that will be added to the outbox.
	 * @param string                            $activity_type      The activity type.
	 * @param int                               $user_id            The real or imaginary user ID of the actor that published the activity that will be added to the outbox.
	 * @param string                            $content_visibility Optional. The visibility of the content. Default: `ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC`. See `constants.php` for possible values: `ACTIVITYPUB_CONTENT_VISIBILITY_*`.
	 *
	 * @return false|int|\WP_Error The added item or an error.
	 */
	public static function add( $activity_object, $activity_type, $user_id, $content_visibility = ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC ) { // phpcs:ignore
		switch ( $user_id ) {
			case Actors::APPLICATION_USER_ID:
				$actor_type = 'application';
				break;
			case Actors::BLOG_USER_ID:
				$actor_type = 'blog';
				break;
			default:
				$actor_type = 'user';
				break;
		}

		$title                 = $activity_object->get_name() ?? $activity_object->get_content();
		$activitypub_object_id = $activity_object->get_id();

		if ( ! $title && is_activity( $activity_object ) && $activity_object->get_object() instanceof \Activitypub\Activity\Base_Object ) {
			$title                 = $activity_object->get_object()->get_name() ?? $activity_object->get_object()->get_content();
			$activitypub_object_id = $activity_object->get_object()->get_id();
		}

		$outbox_item = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => sprintf(
				/* translators: 1. Activity type, 2. Object type, 3. Object Title or Excerpt */
				__( '[%1$s] %2$s: %3$s', 'activitypub' ),
				$activity_type,
				$activity_object->get_type(),
				\wp_trim_words( $title, 5 )
			),
			'post_content' => wp_slash( $activity_object->to_json() ),
			// ensure that user ID is not below 0.
			'post_author'  => \max( $user_id, 0 ),
			'post_status'  => 'pending',
			'meta_input'   => array(
				'_activitypub_object_id'         => $activitypub_object_id,
				'_activitypub_activity_type'     => $activity_type,
				'_activitypub_activity_actor'    => $actor_type,
				'activitypub_content_visibility' => $content_visibility,
			),
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

		if ( \is_wp_error( $id ) ) {
			return $id;
		}

		if ( ! $id ) {
			return false;
		}

		self::invalidate_existing_items( $activitypub_object_id, $activity_type, $id );

		return $id;
	}

	/**
	 * Invalidate existing outbox items with the same activity type and object ID
	 * by setting their status to 'publish'.
	 *
	 * @param string $object_id     The ID of the activity object.
	 * @param string $activity_type The type of the activity.
	 * @param int    $current_id    The ID of the current outbox item to exclude.
	 *
	 * @return void
	 */
	private static function invalidate_existing_items( $object_id, $activity_type, $current_id ) {
		$meta_query = array(
			array(
				'key'   => '_activitypub_object_id',
				'value' => $object_id,
			),
		);

		// For non-Delete activities, only invalidate items of the same type.
		if ( 'Delete' !== $activity_type ) {
			$meta_query[] = array(
				'key'   => '_activitypub_activity_type',
				'value' => $activity_type,
			);
		}

		$existing_items = get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'pending',
				'exclude'     => array( $current_id ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => $meta_query,
				'fields'      => 'ids',
			)
		);

		foreach ( $existing_items as $existing_item_id ) {
			$event_args = array(
				Dispatcher::$callback,
				$existing_item_id,
				Dispatcher::$batch_size,
				\get_post_meta( $existing_item_id, '_activitypub_outbox_offset', true ) ?: 0, // phpcs:ignore
			);

			$timestamp = \wp_next_scheduled( 'activitypub_async_batch', $event_args );
			\wp_unschedule_event( $timestamp, 'activitypub_async_batch', $event_args );

			$timestamp = \wp_next_scheduled( 'activitypub_process_outbox', array( $existing_item_id ) );
			\wp_unschedule_event( $timestamp, 'activitypub_process_outbox', array( $existing_item_id ) );

			\wp_publish_post( $existing_item_id );
			\delete_post_meta( $existing_item_id, '_activitypub_outbox_offset' );
		}
	}
}
