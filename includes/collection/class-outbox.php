<?php
/**
 * Outbox collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;
use Activitypub\Scheduler;
use Activitypub\Webfinger;

use function Activitypub\add_to_outbox;

/**
 * ActivityPub Outbox Collection
 *
 * @link https://www.w3.org/TR/activitypub/#outbox
 */
class Outbox {
	/**
	 * The post type for the objects.
	 *
	 * @var string
	 */
	const POST_TYPE = 'ap_outbox';

	/**
	 * Maximum number of outbox items to keep.
	 *
	 * When the total count exceeds this, the oldest items are purged
	 * regardless of their age. Acts as a safety net for runaway growth.
	 *
	 * @var int
	 */
	const MAX_ITEMS = 5000;

	/**
	 * Number of items to process per batch during purge.
	 *
	 * @var int
	 */
	const PURGE_BATCH_SIZE = 100;

	/**
	 * Maximum seconds a purge run may take before yielding.
	 *
	 * @var int
	 */
	const PURGE_TIMEOUT = 30;

	/**
	 * Add an Item to the outbox.
	 *
	 * @param Activity $activity   Full Activity object that will be added to the outbox.
	 * @param int      $user_id    The real or imaginary user ID of the actor that published the activity that will be added to the outbox.
	 * @param string   $visibility Optional. The visibility of the content. Default: `ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC`. See `constants.php` for possible values: `ACTIVITYPUB_CONTENT_VISIBILITY_*`.
	 *
	 * @return false|int|\WP_Error The added item or an error.
	 */
	public static function add( Activity $activity, $user_id, $visibility = ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC ) {
		$actor_type = Actors::get_type_by_id( $user_id );
		$object_id  = self::get_object_id( $activity );
		$title      = self::get_object_title( $activity->get_object() );

		if ( ! $activity->get_actor() ) {
			$activity->set_actor( Actors::get_by_id( $user_id )->get_id() );
		}

		if ( ! \filter_var( $object_id, FILTER_VALIDATE_URL ) ) {
			$object_id = Webfinger::resolve( $object_id );
		}

		if ( \is_wp_error( $object_id ) ) {
			return $object_id;
		}

		// Save activity in the context of an activitypub request.
		\add_filter( 'activitypub_is_activitypub_request', '__return_true' );

		$outbox_item = array(
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
				'_activitypub_object_id'         => $object_id,
				'_activitypub_activity_type'     => $activity->get_type(),
				'_activitypub_activity_actor'    => $actor_type,
				'activitypub_content_visibility' => $visibility,
			),
		);

		\remove_filter( 'activitypub_is_activitypub_request', '__return_true' );

		$has_kses = false !== \has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $has_kses ) {
			// Prevent KSES from corrupting JSON in post_content.
			\kses_remove_filters();
		}

		$id = \wp_insert_post( $outbox_item, true );

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

		self::delete_superseded_items( $object_id, $activity->get_type(), $id );

		return $id;
	}

	/**
	 * Delete pending outbox items that have been superseded by a newer item.
	 *
	 * For most activity types, only items with the same type and object ID are
	 * deleted. Delete activities are a special case: they supersede all pending
	 * items for the same object regardless of type.
	 *
	 * Unschedules all federation events before deleting each item.
	 * Skips Follow, Announce, Accept, and Reject activities, as those are
	 * independent per-request responses that must not cancel each other.
	 *
	 * @param string $object_id     The ActivityPub object ID (URL).
	 * @param string $activity_type The activity type (e.g. 'Create', 'Update', 'Delete').
	 * @param int    $exclude_id    The ID of the newly added outbox item to keep.
	 *
	 * @return void
	 */
	private static function delete_superseded_items( $object_id, $activity_type, $exclude_id ) {
		/*
		 * Do not delete items for Follow, Announce, Accept, or Reject activities.
		 * Follow activities from different users share the same object ID but are
		 * independent and must survive until their Accept is received.
		 * Accept/Reject are per-request responses (e.g. to individual incoming
		 * QuoteRequests) and must not cancel each other even when they share
		 * the same object ID.
		 */
		if ( in_array( $activity_type, array( 'Follow', 'Announce', 'Accept', 'Reject' ), true ) ) {
			return;
		}

		$meta_query = array(
			array(
				'key'   => '_activitypub_object_id',
				'value' => $object_id,
			),
		);

		// For non-Delete activities, only delete items of the same type.
		// Delete activities supersede all pending items for the same object.
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
				'exclude'     => array( $exclude_id ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => $meta_query,
				'fields'      => 'ids',
			)
		);

		foreach ( $existing_items as $existing_item_id ) {
			Scheduler::unschedule_events_for_item( $existing_item_id );
			\wp_delete_post( $existing_item_id, true );
		}
	}

	/**
	 * Creates an Undo activity.
	 *
	 * @param int|\WP_Post $outbox_item The Outbox post or post ID.
	 *
	 * @return int|bool|\WP_Error The ID of the outbox item or false on failure.
	 */
	public static function undo( $outbox_item ) {
		$outbox_item = \get_post( $outbox_item );
		$activity    = self::get_activity( $outbox_item );

		if ( \is_wp_error( $activity ) ) {
			return $activity;
		}

		$type = 'Undo';
		if ( 'Create' === $activity->get_type() ) {
			$type = 'Delete';
		} elseif ( 'Add' === $activity->get_type() ) {
			$type = 'Remove';
		}

		$visibility = \get_post_meta( $outbox_item->ID, 'activitypub_content_visibility', true );

		return add_to_outbox( $activity, $type, $outbox_item->post_author, $visibility );
	}

	/**
	 * Get an outbox item by its GUID.
	 *
	 * @param string $guid The GUID of the outbox item.
	 *
	 * @return \WP_Post|\WP_Error The outbox item or WP_Error.
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
				'activitypub_outbox_item_not_found',
				\__( 'Outbox item not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		return \get_post( $post_id );
	}

	/**
	 * Reschedule an activity.
	 *
	 * @param int|\WP_Post $outbox_item The Outbox post or post ID.
	 *
	 * @return bool True if the activity was rescheduled, false otherwise.
	 */
	public static function reschedule( $outbox_item ) {
		$outbox_item = get_post( $outbox_item );

		$outbox_item->post_status = 'pending';
		$outbox_item->post_date   = current_time( 'mysql' );

		wp_update_post( $outbox_item );

		Scheduler::schedule_outbox_activity_for_federation( $outbox_item->ID );

		return true;
	}

	/**
	 * Get the Activity object from the Outbox item.
	 *
	 * @param int|\WP_Post $outbox_item The Outbox post or post ID.
	 * @return Activity|\WP_Error The Activity object or WP_Error.
	 */
	public static function get_activity( $outbox_item ) {
		$outbox_item = \get_post( $outbox_item );

		$activity_object = \json_decode( $outbox_item->post_content, true );
		$type            = \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true );

		if ( $activity_object['type'] === $type ) {
			$activity = Activity::init_from_array( $activity_object );
			if ( ! $activity->get_actor() ) {
				$actor = self::get_actor( $outbox_item );
				if ( \is_wp_error( $actor ) ) {
					return $actor;
				}
				$activity->set_actor( $actor->get_id() );
			}
		} else {
			$actor = self::get_actor( $outbox_item );
			if ( \is_wp_error( $actor ) ) {
				return $actor;
			}

			$activity = new Activity();
			$activity->set_type( $type );
			$activity->set_id( $outbox_item->guid );
			$activity->set_actor( $actor->get_id() );
			// Pre-fill the Activity with data (for example cc and to).
			$activity->set_object( $activity_object );
		}

		if ( 'Update' === $type ) {
			$activity->set_updated( gmdate( ACTIVITYPUB_DATE_TIME_RFC3339, strtotime( $outbox_item->post_modified ) ) );
		}

		/**
		 * Filters the Activity object before it is returned.
		 *
		 * @param Activity $activity    The Activity object.
		 * @param \WP_Post $outbox_item The outbox item post object.
		 */
		return apply_filters( 'activitypub_get_outbox_activity', $activity, $outbox_item );
	}

	/**
	 * Get the Actor object from the Outbox item.
	 *
	 * @param \WP_Post $outbox_item The Outbox post.
	 *
	 * @return \Activitypub\Model\User|\Activitypub\Model\Blog|\WP_Error The Actor object or WP_Error.
	 */
	public static function get_actor( $outbox_item ) {
		$actor_type = \get_post_meta( $outbox_item->ID, '_activitypub_activity_actor', true );

		switch ( $actor_type ) {
			case 'blog':
				$actor_id = Actors::BLOG_USER_ID;
				break;
			case 'application':
				$actor_id = Actors::APPLICATION_USER_ID;
				break;
			case 'user':
			default:
				$actor_id = $outbox_item->post_author;
				break;
		}

		return Actors::get_by_id( $actor_id );
	}

	/**
	 * Get the Activity object from the Outbox item.
	 *
	 * @param \WP_Post $outbox_item The Outbox post.
	 *
	 * @return Activity|\WP_Error The Activity object or WP_Error.
	 */
	public static function maybe_get_activity( $outbox_item ) {
		if ( ! $outbox_item instanceof \WP_Post ) {
			return new \WP_Error( 'invalid_outbox_item', 'Invalid Outbox item.' );
		}

		if ( 'ap_outbox' !== $outbox_item->post_type ) {
			return new \WP_Error( 'invalid_outbox_item', 'Invalid Outbox item.' );
		}

		// Check if Outbox Activity is public.
		$visibility = \get_post_meta( $outbox_item->ID, 'activitypub_content_visibility', true );

		if ( ! in_array( $visibility, array( ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC, ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC ), true ) ) {
			return new \WP_Error( 'private_outbox_item', 'Not a public Outbox item.' );
		}

		$activity_types = \apply_filters( 'rest_activitypub_outbox_activity_types', array( 'Announce', 'Create', 'Like', 'Update' ) );
		$activity_type  = \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true );

		if ( ! in_array( $activity_type, $activity_types, true ) ) {
			return new \WP_Error( 'private_outbox_item', 'Not public Outbox item type.' );
		}

		return self::get_activity( $outbox_item );
	}

	/**
	 * Get the object ID of an activity.
	 *
	 * @param Activity|Base_Object|string $data The activity object.
	 *
	 * @return string The object ID.
	 */
	private static function get_object_id( $data ) {
		$object = $data->get_object();

		if ( is_object( $object ) ) {
			return self::get_object_id( $object );
		}

		if ( is_string( $object ) ) {
			return $object;
		}

		return $data->get_id() ?? $data->get_actor();
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

		if ( is_string( $activity_object ) ) {
			$post_id = url_to_postid( $activity_object );

			return $post_id ? get_the_title( $post_id ) : '';
		}

		$title = $activity_object->get_name() ?: $activity_object->get_content();

		if ( ! $title && $activity_object->get_object() instanceof Base_Object ) {
			$title = $activity_object->get_object()->get_name() ?: $activity_object->get_object()->get_content();
		}

		return $title;
	}

	/**
	 * Purge old outbox items.
	 *
	 * Deletes outbox items older than the specified number of days,
	 * except for Follow activities which are always preserved.
	 * Also enforces a hard cap on total items via MAX_ITEMS.
	 *
	 * @param int $days Number of days to keep items. Items older than this will be deleted.
	 *
	 * @return int The number of items deleted.
	 */
	public static function purge( $days ) {
		if ( $days <= 0 ) {
			return 0;
		}

		$counts = \wp_count_posts( self::POST_TYPE );
		$total  = 0;
		foreach ( $counts as $count ) {
			$total += (int) $count;
		}

		if ( $total <= 20 ) {
			return 0;
		}

		$deleted    = 0;
		$cutoff     = \gmdate( 'Y-m-d', \time() - ( $days * DAY_IN_SECONDS ) );
		$start_time = \time();

		// If total exceeds the hard cap, drop the date filter to purge oldest items first.
		$overflow   = $total > self::MAX_ITEMS;
		$date_query = array(
			array(
				'before' => $cutoff,
			),
		);

		$query_args = array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'any',
			'fields'      => 'ids',
			'numberposts' => self::PURGE_BATCH_SIZE,
			'orderby'     => 'date',
			'order'       => 'ASC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'  => array(
				array(
					'key'     => '_activitypub_activity_type',
					'value'   => 'Follow',
					'compare' => '!=',
				),
			),
		);

		if ( ! $overflow ) {
			$query_args['date_query'] = $date_query;
		}

		do {
			$post_ids = \get_posts( $query_args );

			foreach ( $post_ids as $post_id ) {
				\wp_delete_post( $post_id, true );
				++$deleted;
			}

			// Once we're back under the cap, re-apply the date filter.
			if ( $overflow && ( $total - $deleted ) <= self::MAX_ITEMS ) {
				$overflow                 = false;
				$query_args['date_query'] = $date_query;
			}
		} while ( ! empty( $post_ids ) && ( \time() - $start_time ) < self::PURGE_TIMEOUT );

		return $deleted;
	}
}
