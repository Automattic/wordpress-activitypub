<?php
/**
 * Posts collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Emoji;
use Activitypub\Sanitize;

use function Activitypub\generate_post_summary;
use function Activitypub\object_to_uri;
use function Activitypub\process_remote_media;

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
	 * Maximum number of remote post items to keep.
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
	 * Extract hashtag names from ActivityPub tag array.
	 *
	 * @param array $tags Array of ActivityPub tags.
	 *
	 * @return array Array of normalized hashtag names (without # prefix, trimmed, sanitized).
	 */
	public static function extract_hashtags( $tags ) {
		$hashtags = array();

		if ( empty( $tags ) || ! \is_array( $tags ) ) {
			return $hashtags;
		}

		foreach ( $tags as $tag ) {
			if ( isset( $tag['type'] ) && 'Hashtag' === $tag['type'] && isset( $tag['name'] ) ) {
				// Strip # prefix, trim whitespace, and sanitize.
				$normalized = \trim( \ltrim( $tag['name'], '#' ) );
				$normalized = \wp_strip_all_tags( $normalized );

				if ( ! empty( $normalized ) ) {
					$hashtags[] = $normalized;
				}
			}
		}

		return $hashtags;
	}

	/**
	 * Remove hashtags from content.
	 *
	 * Removes hashtags that appear at the end of the content.
	 * Handles both plain text and HTML content, including hashtags within anchor tags.
	 *
	 * @param string $content The content to process.
	 * @param array  $tags    Array of tag objects from activity (with 'type' and 'name' keys).
	 *
	 * @return string The content with trailing hashtags removed.
	 */
	public static function remove_hashtags( $content, $tags ) {
		if ( empty( $content ) || empty( $tags ) || ! \is_array( $tags ) ) {
			return $content;
		}

		// Extract and normalize hashtags from tag objects.
		$normalized_tags = self::extract_hashtags( $tags );

		if ( empty( $normalized_tags ) ) {
			return $content;
		}

		// Build pattern to match trailing hashtags (at end of content or before closing tags).
		$tag_patterns = array();
		foreach ( $normalized_tags as $tag ) {
			$escaped_tag    = \preg_quote( $tag, '/' );
			$tag_patterns[] = '(?:<a[^>]*>\s*)?#' . $escaped_tag . '(?=\s|<|$)(?:\s*<\/a>)?';
		}

		/*
		 * Pattern explanation:
		 * Match one or more hashtags (plain or in anchor tags) at the end of content.
		 * The pattern matches trailing hashtags before closing HTML tags or at end of string.
		 */
		$pattern = '/(?:\s+(?:' . \implode( '|', $tag_patterns ) . '))+(?=\s*(?:<\/[^>]+>)*\s*$)/i';
		$content = \preg_replace( $pattern, '', $content );

		// Clean up any extra whitespace at end of paragraphs.
		$content = \preg_replace( '/<p>\s*<\/p>/', '', $content );
		$content = \preg_replace( '/\s+<\/p>/', '</p>', $content );
		$content = \preg_replace( '/\s+<\/strong>/', '</strong>', $content );

		return \trim( $content );
	}

	/**
	 * Convert an activity to a post array.
	 *
	 * @param array $activity The activity array.
	 *
	 * @return array|\WP_Error The post array or WP_Error on failure.
	 */
	private static function activity_to_post( $activity ) {
		if ( ! \is_array( $activity ) ) {
			return new \WP_Error( 'invalid_activity', \__( 'Invalid activity format', 'activitypub' ) );
		}

		$gm_date = \gmdate( 'Y-m-d H:i:s', \strtotime( $activity['published'] ?? 'now' ) );

		// Sanitize content and remove hashtags.
		$content = isset( $activity['content'] ) ? Sanitize::content( $activity['content'] ) : '';
		$content = self::remove_hashtags( $content, $activity['tag'] ?? array() );
		$content = Emoji::wrap_in_content( $content, $activity );

		// Process remote media: wrap inline images and append attachments.
		$attachments = self::extract_attachments( $activity );
		$content     = process_remote_media( $content, $attachments );

		return array(
			'post_title'    => isset( $activity['name'] ) ? \wp_strip_all_tags( $activity['name'] ) : '',
			'post_content'  => $content,
			'post_excerpt'  => isset( $activity['summary'] ) ? \wp_strip_all_tags( $activity['summary'] ) : generate_post_summary( $activity['content'] ?? '' ),
			'post_status'   => 'publish',
			'post_type'     => self::POST_TYPE,
			'post_date_gmt' => $gm_date,
			'post_date'     => \get_date_from_gmt( $gm_date ),
			'guid'          => isset( $activity['id'] ) ? \esc_url_raw( $activity['id'] ) : '',
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

		// Save the Hashtags as Taxonomy items.
		$tags = self::extract_hashtags( $activity_object['tag'] ?? array() );

		\wp_set_post_terms( $post_id, $tags, 'ap_tag' );
	}

	/**
	 * Extract media attachments from an activity object.
	 *
	 * Extracts attachments with URL, alt text, and media type for appending to content.
	 *
	 * @param array $activity_object The activity object data.
	 *
	 * @return array Array of attachments with 'url', 'alt', and 'type' keys.
	 */
	private static function extract_attachments( $activity_object ) {
		if ( empty( $activity_object['attachment'] ) || ! \is_array( $activity_object['attachment'] ) ) {
			return array();
		}

		$attachments = array();
		foreach ( $activity_object['attachment'] as $attachment ) {
			if ( \is_object( $attachment ) ) {
				$attachment = \get_object_vars( $attachment );
			}

			if ( empty( $attachment['url'] ) ) {
				continue;
			}

			$mime_type = $attachment['mediaType'] ?? '';

			if ( \str_starts_with( $mime_type, 'video/' ) ) {
				$type = 'video';
			} elseif ( \str_starts_with( $mime_type, 'audio/' ) ) {
				$type = 'audio';
			} else {
				$type = 'image';
			}

			$attachments[] = array(
				'url'  => $attachment['url'],
				'alt'  => $attachment['name'] ?? '',
				'type' => $type,
			);
		}

		return $attachments;
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

	/**
	 * Delete all posts.
	 *
	 * Used during plugin uninstall to clean up all remote posts.
	 *
	 * @return int The number of posts deleted.
	 */
	public static function delete_all() {
		$post_ids = \get_posts(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => array( 'any', 'trash', 'auto-draft' ),
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);

		foreach ( $post_ids as $post_id ) {
			\wp_delete_post( $post_id, true );
		}

		return count( $post_ids );
	}

	/**
	 * Purge old remote posts.
	 *
	 * Deletes remote posts older than the specified number of days,
	 * but preserves posts that have comments from local users
	 * as these indicate meaningful local interactions.
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

		if ( $total <= 200 ) {
			return 0;
		}

		global $wpdb;

		$deleted    = 0;
		$cutoff     = \gmdate( 'Y-m-d', \time() - ( $days * DAY_IN_SECONDS ) );
		$start_time = \time();
		$exclude    = array();

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
		);

		if ( ! $overflow ) {
			$query_args['date_query'] = $date_query;
		}

		do {
			$query_args['exclude'] = $exclude;
			$post_ids              = \get_posts( $query_args );

			if ( empty( $post_ids ) ) {
				break;
			}

			// Batch-fetch post IDs that have local user comments (single query per batch).
			$placeholders = \implode( ',', \array_fill( 0, \count( $post_ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$commented_post_ids = $wpdb->get_col(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders
				$wpdb->prepare( "SELECT DISTINCT comment_post_ID FROM $wpdb->comments WHERE comment_post_ID IN ($placeholders) AND user_id > 0", $post_ids )
			);
			$commented_post_ids = \array_flip( $commented_post_ids );

			foreach ( $post_ids as $post_id ) {
				/**
				 * Filter whether to preserve a specific ap_post from being purged.
				 *
				 * @param bool $preserve Whether to preserve this post. Default false.
				 * @param int  $post_id  The ap_post ID being considered for deletion.
				 *
				 * @return bool Whether to preserve this post from deletion.
				 */
				if ( \apply_filters( 'activitypub_preserve_ap_post', false, $post_id ) ) {
					$exclude[] = $post_id;
					continue;
				}

				// Preserve posts with comments from local users.
				if ( isset( $commented_post_ids[ $post_id ] ) ) {
					$exclude[] = $post_id;
					continue;
				}

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
