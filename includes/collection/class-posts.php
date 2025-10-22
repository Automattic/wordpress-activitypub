<?php
/**
 * Posts collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

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
	 * @param array $activity The activity object data.
	 * @param int   $user_id  The local user ID.
	 *
	 * @return \WP_Post|\WP_Error The object post or WP_Error on failure.
	 */
	public static function add( $activity, $user_id ) {
		$activity_object = $activity['object'];
		$actor           = Remote_Actors::fetch_by_uri( object_to_uri( $activity_object['attributedTo'] ) );

		if ( \is_wp_error( $actor ) ) {
			return $actor;
		}

		$post_array = self::activity_to_post( $activity_object );
		$post_id    = \wp_insert_post( $post_array, true );

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		\add_post_meta( $post_id, '_activitypub_remote_actor_id', $actor->ID );
		\add_post_meta( $post_id, '_activitypub_user_id', $user_id );

		self::add_taxonomies( $post_id, $activity_object );

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
	 * @param array $activity The activity object data.
	 * @param int   $user_id  The local user ID.
	 *
	 * @return \WP_Post|\WP_Error The updated object post or WP_Error on failure.
	 */
	public static function update( $activity, $user_id ) {
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

		$post_meta = \get_post_meta( $post_id, '_activitypub_user_id', false );
		if ( \is_array( $post_meta ) && ! \in_array( (string) $user_id, $post_meta, true ) ) {
			\add_post_meta( $post_id, '_activitypub_user_id', $user_id );
		}

		self::add_taxonomies( $post_id, $activity['object'] );

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

		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'posts_per_page' => -1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => '_activitypub_remote_actor_id',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'     => $remote_actor->ID,
			)
		);

		return $query->posts;
	}
}
