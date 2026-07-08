<?php
/**
 * Inbox collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Activity\Activity;
use Activitypub\Comment;

use function Activitypub\is_activity_public;
use function Activitypub\object_to_uri;

/**
 * ActivityPub Inbox Collection
 *
 * @link https://www.w3.org/TR/activitypub/#inbox
 */
class Inbox {
	use With_Guid_Lookup;
	use With_Kses_Safe_Insert;
	use With_Object_Title;
	use With_Purge;
	use With_Recipients;

	/**
	 * The post type for the objects.
	 *
	 * @var string
	 */
	const POST_TYPE = 'ap_inbox';

	/**
	 * Maximum number of inbox items to keep.
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
	 * Context for user inbox requests.
	 *
	 * @var string
	 */
	const CONTEXT_INBOX = 'inbox';

	/**
	 * Context for shared inbox requests.
	 *
	 * @var string
	 */
	const CONTEXT_SHARED_INBOX = 'shared_inbox';

	/**
	 * Add an activity to the inbox.
	 *
	 * @param Activity|\WP_Error $activity   The Activity object.
	 * @param int|array          $recipients The id(s) of the local blog-user(s).
	 *
	 * @return false|int|\WP_Error The added item or an error.
	 */
	public static function add( $activity, $recipients ) {
		if ( \is_wp_error( $activity ) ) {
			return $activity;
		}

		// Sanitize recipients.
		$recipients = \array_map( 'absint', (array) $recipients );
		$recipients = \array_unique( $recipients );
		$recipients = \array_values( $recipients );

		if ( empty( $recipients ) ) {
			return new \WP_Error(
				'activitypub_inbox_no_recipients',
				'No valid recipients provided',
				array( 'status' => 400 )
			);
		}

		// Check if activity already exists (by GUID).
		$existing = self::get_by_guid( $activity->get_id() );

		// If activity exists, add new recipients to it.
		if ( $existing instanceof \WP_Post ) {
			foreach ( $recipients as $user_id ) {
				self::add_recipient( $existing->ID, $user_id );
			}

			return $existing->ID;
		}

		// Activity doesn't exist, create new post.
		$title      = self::get_object_title( $activity->get_object() );
		$visibility = is_activity_public( $activity ) ? ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC : ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE;

		/*
		 * For QuoteRequest activities, we store the instrument URL as the object_id.
		 * This allows efficient querying by instrument (the quote post URL).
		 * For all other activities, we store the object URL as before.
		 */
		if ( 'QuoteRequest' === $activity->get_type() && $activity->get_instrument() ) {
			$object_id = object_to_uri( $activity->get_instrument() ?? '' );
		} else {
			$object_id = object_to_uri( $activity->get_object() ?? '' );
		}

		$inbox_item = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => \sprintf(
				/* translators: 1. Activity type, 2. Object Title or Excerpt */
				\__( '[%1$s] %2$s', 'activitypub' ),
				$activity->get_type(),
				\wp_trim_words( $title, 5 )
			),
			// Persist the blind audience so we keep the full addressing the sender used.
			'post_content' => \wp_slash( $activity->to_json( true, true ) ),
			'post_author'  => 0, // No specific author, recipients stored in meta.
			'post_status'  => 'publish',
			'guid'         => $activity->get_id(),
			'meta_input'   => array(
				'_activitypub_object_id'             => $object_id,
				'_activitypub_activity_type'         => $activity->get_type(),
				'_activitypub_activity_remote_actor' => object_to_uri( $activity->get_actor() ),
				'activitypub_content_visibility'     => $visibility,
			),
		);

		$id = self::without_kses(
			static function () use ( $inbox_item ) {
				return \wp_insert_post( $inbox_item, true );
			}
		);

		// Add recipients as separate meta entries after post is created.
		if ( ! \is_wp_error( $id ) ) {
			foreach ( $recipients as $user_id ) {
				self::add_recipient( $id, $user_id );
			}
		}

		return $id;
	}

	/**
	 * Get the inbox item by id.
	 *
	 * @param int $id The inbox item id.
	 *
	 * @return \WP_Post|null The inbox item or null.
	 */
	public static function get( $id ) {
		return \get_post( $id );
	}

	/**
	 * Get an inbox item by its GUID.
	 *
	 * @param string $guid The GUID of the inbox item.
	 *
	 * @return \WP_Post|\WP_Error The inbox item or WP_Error.
	 */
	public static function get_by_guid( $guid ) {
		$post_id = self::get_id_by_guid( $guid );

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
	 * @param string      $id    The ID of the inbox item to be removed.
	 * @param string|null $actor Optional. The actor URI of the Undo sender. When provided, the
	 *                           activity is only undone if this actor created the original
	 *                           activity. Default null.
	 *
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public static function undo( $id, $actor = null ) {
		$inbox_item = self::get_by_guid( $id );

		if ( \is_wp_error( $inbox_item ) ) {
			// If inbox entry not found, return the error.
			return $inbox_item;
		}

		/*
		 * Only the actor that created the original activity may undo it. Without this
		 * binding a remote server could undo (and, for interactions, force-delete the
		 * comment behind) any activity whose public id it knows but does not own.
		 *
		 * Items stored before this meta existed have no actor recorded; those are let
		 * through for backward compatibility rather than becoming permanently un-undoable.
		 */
		$stored_actor = \get_post_meta( $inbox_item->ID, '_activitypub_activity_remote_actor', true );
		if ( null !== $actor && $stored_actor && object_to_uri( $actor ) !== $stored_actor ) {
			return new \WP_Error(
				'activitypub_inbox_undo_forbidden',
				\__( 'Undo is not possible because the actor does not own the activity.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$type = \get_post_meta( $inbox_item->ID, '_activitypub_activity_type', true );

		switch ( $type ) {
			case 'Follow':
				$actor        = \get_post_meta( $inbox_item->ID, '_activitypub_activity_remote_actor', true );
				$remote_actor = Remote_Actors::get_by_uri( $actor );

				if ( \is_wp_error( $remote_actor ) ) {
					return $remote_actor;
				}

				// A follow is only possible for a specific user.
				$user_id = \get_post_meta( $inbox_item->ID, '_activitypub_user_id', true );
				return Followers::remove( $remote_actor, $user_id );

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

				$result = Comment::object_id_to_comment( \esc_url_raw( $inbox_item->guid ) );

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

	/**
	 * Get an inbox item by GUID for a specific recipient.
	 *
	 * This checks both that the activity exists and that the user is a valid recipient.
	 *
	 * @param string $guid    The activity GUID.
	 * @param int    $user_id The user ID.
	 *
	 * @return \WP_Post|\WP_Error The inbox item or WP_Error.
	 */
	public static function get_by_guid_and_recipient( $guid, $user_id ) {
		$post = self::get_by_guid( $guid );

		if ( \is_wp_error( $post ) ) {
			return $post;
		}

		// Check if user is a recipient.
		if ( ! self::has_recipient( $post->ID, $user_id ) ) {
			return new \WP_Error(
				'activitypub_inbox_not_recipient',
				'User is not a recipient of this activity',
				array( 'status' => 404 )
			);
		}

		return $post;
	}

	/**
	 * Get an inbox item by activity type and object ID.
	 *
	 * This is useful for finding specific activity types (like QuoteRequest)
	 * by their object identifier. For QuoteRequest activities, the object_id
	 * is the instrument URL (the quote post).
	 *
	 * @param string $activity_type The activity type (e.g., 'QuoteRequest').
	 * @param string $object_id     The object identifier to search for.
	 *
	 * @return \WP_Post|\WP_Error The inbox item or WP_Error if not found.
	 */
	public static function get_by_type_and_object( $activity_type, $object_id ) {
		$posts = \get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Necessary for querying by activity type and object ID.
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_activitypub_activity_type',
						'value' => $activity_type,
					),
					array(
						'key'   => '_activitypub_object_id',
						'value' => $object_id,
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			return new \WP_Error(
				'activitypub_inbox_item_not_found',
				\__( 'Inbox item not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		return $posts[0];
	}

	/**
	 * Deduplicate inbox items with the same GUID.
	 *
	 * If multiple inbox items exist with the same GUID (due to race conditions),
	 * this merges all recipients into the first post and deletes duplicates.
	 *
	 * @param string $guid The activity GUID.
	 *
	 * @return \WP_Post|false The primary inbox post, or false if no posts found.
	 */
	public static function deduplicate( $guid ) {
		// Query for all posts with this GUID directly (get_posts doesn't supports guid parameter).
		$post_ids = self::get_ids_by_guid( $guid );

		if ( empty( $post_ids ) ) {
			return false;
		}

		// Keep the first (oldest) post as primary.
		$primary_id = \array_shift( $post_ids );
		$primary    = \get_post( $primary_id );

		// Merge recipients from duplicates into primary and delete duplicates.
		foreach ( $post_ids as $duplicate_id ) {
			$recipients = \get_post_meta( $duplicate_id, '_activitypub_user_id', false );
			self::add_recipients( $primary_id, $recipients );
			\wp_delete_post( $duplicate_id, true );
		}

		return $primary;
	}

	/**
	 * Purge old inbox items.
	 *
	 * Deletes inbox items older than the specified number of days.
	 *
	 * @param int $days Number of days to keep items. Items older than this will be deleted.
	 *
	 * @return int The number of items deleted.
	 */
	public static function purge( $days ) {
		return self::run_purge( $days, array( 'min_total' => 200 ) );
	}
}
