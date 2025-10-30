<?php
/**
 * Posts collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Attachments;
use Activitypub\Sanitize;

use function Activitypub\object_to_uri;

/**
 * Posts collection.
 *
 * Provides methods to retrieve, create, update, and manage ActivityPub posts (articles, notes, media, etc.).
 */
class Posts {
	/**
	 * The post type for the posts.
	 *
	 * @var string
	 */
	const POST_TYPE = 'ap_post';

	/**
	 * Add an object to the collection.
	 *
	 * @param array     $activity   The activity object data.
	 * @param int|int[] $recipients The id(s) of the local blog-user(s).
	 *
	 * @return \WP_Post|\WP_Error The object post or WP_Error on failure.
	 */
	public static function add( $activity, $recipients ) {
		$recipients      = (array) $recipients;
		$activity_object = $activity['object'];

		$existing = self::get_by_guid( $activity_object['id'] );
		// If post exists, call update instead.
		if ( ! \is_wp_error( $existing ) ) {
			return self::update( $activity, $recipients );
		}

		// Post doesn't exist, create new post.
		$actor = Remote_Actors::fetch_by_uri( object_to_uri( $activity_object['attributedTo'] ) );

		if ( \is_wp_error( $actor ) ) {
			return $actor;
		}

		$post_array = self::activity_to_post( $activity_object );
		$post_id    = \wp_insert_post( $post_array, true );

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		\add_post_meta( $post_id, '_activitypub_remote_actor_id', $actor->ID );

		// Add recipients as separate meta entries after post is created.
		foreach ( $recipients as $user_id ) {
			self::add_recipient( $post_id, $user_id );
		}

		self::add_taxonomies( $post_id, $activity_object );

		// Process attachments if present.
		if ( ! empty( $activity_object['attachment'] ) ) {
			Attachments::import_post_files( $activity_object['attachment'], $post_id );
		}

		return \get_post( $post_id );
	}

	/**
	 * Get an object from the collection.
	 *
	 * @param int $id The object ID.
	 *
	 * @return \WP_Post|null The post object or null on failure.
	 */
	public static function get( $id ) {
		return \get_post( $id );
	}

	/**
	 * Get an object by its GUID.
	 *
	 * @param string $guid The object GUID.
	 *
	 * @return \WP_Post|\WP_Error The object post or WP_Error on failure.
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
				'activitypub_post_not_found',
				\__( 'Post not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		return \get_post( $post_id );
	}

	/**
	 * Update an object in the collection.
	 *
	 * @param array     $activity   The activity object data.
	 * @param int|int[] $recipients The id(s) of the local blog-user(s).
	 *
	 * @return \WP_Post|\WP_Error The updated object post or WP_Error on failure.
	 */
	public static function update( $activity, $recipients ) {
		$recipients = (array) $recipients;

		$post = self::get_by_guid( $activity['object']['id'] );
		if ( \is_wp_error( $post ) ) {
			return $post;
		}

		$post_array       = self::activity_to_post( $activity['object'] );
		$post_array['ID'] = $post->ID;
		$post_id          = \wp_update_post( $post_array, true );

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Add new recipients using add_recipient (handles deduplication).
		foreach ( $recipients as $user_id ) {
			self::add_recipient( $post_id, $user_id );
		}

		self::add_taxonomies( $post_id, $activity['object'] );

		// Process attachments if present.
		if ( ! empty( $activity['object']['attachment'] ) ) {
			Attachments::delete_ap_posts_directory( $post_id );
			Attachments::import_post_files( $activity['object']['attachment'], $post_id );
		}

		return \get_post( $post_id );
	}

	/**
	 * Delete an object from the collection.
	 *
	 * @param int $id The object ID.
	 *
	 * @return \WP_Post|false|null Post data on success, false or null on failure.
	 */
	public static function delete( $id ) {
		return \wp_delete_post( $id, true );
	}

	/**
	 * Delete an object from the collection by its GUID.
	 *
	 * @param string $guid The object GUID.
	 *
	 * @return \WP_Post|\WP_Error|false|null Post data on success, false or null on failure, or WP_Error if no post to delete.
	 */
	public static function delete_by_guid( $guid ) {
		$post = self::get_by_guid( $guid );
		if ( \is_wp_error( $post ) ) {
			return $post;
		}

		return self::delete( $post->ID );
	}

	/**
	 * Convert an activity to a post array.
	 *
	 * @param array $activity The activity array.
	 *
	 * @return array|\WP_Error The post array or WP_Error on failure.
	 */
	private static function activity_to_post( $activity ) {
		if ( ! is_array( $activity ) ) {
			return new \WP_Error( 'invalid_activity', __( 'Invalid activity format', 'activitypub' ) );
		}

		return array(
			'post_title'   => isset( $activity['name'] ) ? \wp_strip_all_tags( $activity['name'] ) : '',
			'post_content' => isset( $activity['content'] ) ? Sanitize::content( $activity['content'] ) : '',
			'post_excerpt' => isset( $activity['summary'] ) ? \wp_strip_all_tags( $activity['summary'] ) : '',
			'post_status'  => 'publish',
			'post_type'    => self::POST_TYPE,
			'guid'         => isset( $activity['id'] ) ? \esc_url_raw( $activity['id'] ) : '',
		);
	}

	/**
	 * Add taxonomies to the object post.
	 *
	 * @param int   $post_id         The post ID.
	 * @param array $activity_object The activity object data.
	 */
	private static function add_taxonomies( $post_id, $activity_object ) {
		// Save Object Type as Taxonomy item.
		\wp_set_post_terms( $post_id, array( $activity_object['type'] ), 'ap_object_type' );

		$tags = array();

		// Save the Hashtags as Taxonomy items.
		if ( ! empty( $activity_object['tag'] ) && \is_array( $activity_object['tag'] ) ) {
			foreach ( $activity_object['tag'] as $tag ) {
				if ( isset( $tag['type'] ) && 'Hashtag' === $tag['type'] && isset( $tag['name'] ) ) {
					$tags[] = \wp_strip_all_tags( ltrim( $tag['name'], '#' ) );
				}
			}
		}

		\wp_set_post_terms( $post_id, $tags, 'ap_tag' );
	}

	/**
	 * Get posts by remote actor.
	 *
	 * @param string $actor The remote actor URI.
	 *
	 * @return array Array of WP_Post objects.
	 */
	public static function get_by_remote_actor( $actor ) {
		$remote_actor = Remote_Actors::fetch_by_uri( $actor );

		if ( \is_wp_error( $remote_actor ) ) {
			return array();
		}

		return self::get_by_remote_actor_id( $remote_actor->ID );
	}

	/**
	 * Get posts by remote actor ID.
	 *
	 * @param int $actor_id The remote actor post ID.
	 *
	 * @return array Array of WP_Post objects.
	 */
	public static function get_by_remote_actor_id( $actor_id ) {
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => '_activitypub_remote_actor_id',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'     => $actor_id,
			)
		);

		return $query->posts;
	}

	/**
	 * Get all recipients for a post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return int[] Array of user IDs who are recipients.
	 */
	public static function get_recipients( $post_id ) {
		// Get all meta values with key '_activitypub_user_id' (single => false).
		$recipients = \get_post_meta( $post_id, '_activitypub_user_id', false );
		$recipients = \array_map( 'intval', $recipients );

		return $recipients;
	}

	/**
	 * Check if a user is a recipient of a post.
	 *
	 * @param int $post_id The post ID.
	 * @param int $user_id The user ID to check.
	 *
	 * @return bool True if user is a recipient, false otherwise.
	 */
	public static function has_recipient( $post_id, $user_id ) {
		$recipients = self::get_recipients( $post_id );

		return \in_array( (int) $user_id, $recipients, true );
	}

	/**
	 * Add a recipient to an existing post.
	 *
	 * @param int $post_id The post ID.
	 * @param int $user_id The user ID to add.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function add_recipient( $post_id, $user_id ) {
		$user_id = (int) $user_id;
		// Allow 0 for blog user, but reject negative values.
		if ( $user_id < 0 ) {
			return false;
		}

		// Check if already a recipient.
		if ( self::has_recipient( $post_id, $user_id ) ) {
			return true;
		}

		// Add new recipient as separate meta entry.
		return (bool) \add_post_meta( $post_id, '_activitypub_user_id', $user_id, false );
	}

	/**
	 * Add multiple recipients to an existing post.
	 *
	 * @param int   $post_id  The post ID.
	 * @param int[] $user_ids The user ID or array of user IDs to add.
	 */
	public static function add_recipients( $post_id, $user_ids ) {
		foreach ( $user_ids as $user_id ) {
			self::add_recipient( $post_id, $user_id );
		}
	}

	/**
	 * Remove a recipient from a post.
	 *
	 * @param int $post_id The post ID.
	 * @param int $user_id The user ID to remove.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function remove_recipient( $post_id, $user_id ) {
		$user_id = (int) $user_id;

		// Allow 0 for blog user, but reject negative values.
		if ( $user_id < 0 ) {
			return false;
		}

		// Delete the specific meta entry with this value.
		return \delete_post_meta( $post_id, '_activitypub_user_id', $user_id );
	}
}
