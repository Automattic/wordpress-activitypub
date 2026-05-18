<?php
/**
 * Tombstone class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Base_Object;

/**
 * ActivityPub Tombstone Class.
 *
 * Handles detection and management of tombstoned (deleted) ActivityPub resources.
 * A tombstone in ActivityPub represents a deleted object that was previously available.
 * This class provides methods to detect tombstones across various data formats including
 * URLs, ActivityPub objects, arrays, and WordPress error responses.
 *
 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-tombstone
 */
class Tombstone {
	/**
	 * HTTP status codes that indicate a tombstoned resource.
	 *
	 * - 404: Not Found - Resource no longer exists
	 * - 410: Gone - Resource was intentionally removed
	 *
	 * @var int[] Array of HTTP status codes indicating tombstones.
	 */
	private static $codes = array( 404, 410 );

	/**
	 * The custom post type used to store local tombstones.
	 *
	 * @var string
	 */
	const POST_TYPE = 'ap_tombstone';

	/**
	 * Check if a tombstone exists for the given resource.
	 *
	 * This is the main entry point for tombstone detection. It accepts various
	 * data types and routes them to the appropriate checking method:
	 * - URLs (string): Checks remote or local tombstone status
	 * - WP_Error objects: Checks for tombstone-indicating HTTP status codes
	 * - Arrays: Checks for ActivityPub Tombstone type
	 * - Objects: Checks for ActivityPub Tombstone type or Base_Object instances
	 *
	 * @param string|\WP_Error|array|object $various The resource data to check for tombstone status.
	 *                                               Can be a URL, error object, ActivityPub array, or object.
	 *
	 * @return bool True if the resource is tombstoned, false otherwise.
	 */
	public static function exists( $various ) {
		if ( \is_wp_error( $various ) ) {
			return self::exists_in_error( $various );
		}

		if ( \is_string( $various ) ) {
			if ( is_same_domain( $various ) ) {
				return self::exists_local( $various );
			}
			return self::exists_remote( $various );
		}

		if ( \is_array( $various ) ) {
			return self::check_array( $various );
		}

		if ( \is_object( $various ) ) {
			return self::check_object( $various );
		}

		return false;
	}

	/**
	 * Check if a remote URL is tombstoned.
	 *
	 * Makes an HTTP request to the remote URL with ActivityPub headers
	 * and checks for tombstone indicators:
	 * - HTTP 404/410 status codes
	 * - ActivityPub Tombstone object type in response body
	 *
	 * @param string $url The remote URL to check for tombstone status.
	 *
	 * @return bool True if the remote URL is tombstoned, false otherwise.
	 */
	public static function exists_remote( $url ) {
		/**
		 * Fires before checking if the URL is a tombstone.
		 *
		 * @param string $url The URL to check.
		 */
		\do_action( 'activitypub_pre_http_is_tombstone', $url );

		$response = Http::get( $url );

		if ( ! \is_wp_error( $response ) ) {
			$data = \wp_remote_retrieve_body( $response );
			$data = \json_decode( $data, true );

			return self::check_array( $data );
		}

		if ( in_array( (int) $response->get_error_code(), self::$codes, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if a local URL is tombstoned.
	 *
	 * Matches by the MD5 hash of the normalized URL stored in `post_name`.
	 * Falls back to the legacy `activitypub_tombstone_urls` option for
	 * tombstones that have not yet been migrated.
	 *
	 * @param string $url The local URL to check for tombstone status.
	 *
	 * @return bool True if the local URL is tombstoned, false otherwise.
	 */
	public static function exists_local( $url ) {
		if ( ! \is_string( $url ) || '' === $url ) {
			return false;
		}

		$normalized = normalize_url( $url );

		if ( ! empty( self::find_post_ids_by_url( $normalized ) ) ) {
			return true;
		}

		/*
		 * Fallback to the legacy option during migration. Once the option is
		 * deleted (migration complete), get_option returns false and the
		 * is_array() guard short-circuits immediately.
		 */
		$legacy = \get_option( 'activitypub_tombstone_urls', false );
		if ( \is_array( $legacy ) && \in_array( $normalized, $legacy, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if a WP_Error object indicates a tombstoned resource.
	 *
	 * Examines the error data for HTTP status codes that indicate tombstones.
	 * This is typically used when HTTP requests return error responses.
	 *
	 * @param \WP_Error $wp_error The WordPress error object to examine.
	 *
	 * @return bool True if the error indicates a tombstoned resource, false otherwise.
	 */
	public static function exists_in_error( $wp_error ) {
		if ( ! \is_wp_error( $wp_error ) ) {
			return false;
		}

		$data = $wp_error->get_error_data();
		if ( isset( $data['status'] ) && in_array( (int) $data['status'], self::$codes, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if an array represents an ActivityPub Tombstone object.
	 *
	 * Examines the array for the ActivityPub 'type' property set to 'Tombstone'.
	 * This follows the ActivityStreams specification for tombstone objects.
	 *
	 * @param array|mixed $data The array data to check. Non-arrays return false.
	 *
	 * @return bool True if the array represents a Tombstone object, false otherwise.
	 */
	private static function check_array( $data ) {
		if ( ! \is_array( $data ) ) {
			return false;
		}

		if ( isset( $data['type'] ) && 'Tombstone' === $data['type'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if an object represents an ActivityPub Tombstone.
	 *
	 * Checks for tombstone indicators in objects:
	 * - Standard objects: 'type' property set to 'Tombstone'
	 * - Base_Object instances: Uses get_type() method to check for 'Tombstone'
	 *
	 * @param object|mixed $data The object data to check. Non-objects return false.
	 *
	 * @return bool True if the object represents a Tombstone, false otherwise.
	 */
	private static function check_object( $data ) {
		if ( ! \is_object( $data ) ) {
			return false;
		}

		if ( isset( $data->type ) && 'Tombstone' === $data->type ) {
			return true;
		}

		if ( $data instanceof Base_Object && 'Tombstone' === $data->get_type() ) {
			return true;
		}

		return false;
	}

	/**
	 * Look up tombstone post IDs by canonical URL.
	 *
	 * The MD5 of the normalized URL is unique per URL, so a successful
	 * `bury()` produces exactly one row and the canonical lookup is enough.
	 *
	 * @since 8.3.0
	 *
	 * @param string $normalized The normalized URL (scheme stripped).
	 * @return int[] Post IDs (zero or one entry under normal operation).
	 */
	private static function find_post_ids_by_url( $normalized ) {
		global $wpdb;

		/*
		 * `bury()` is idempotent on the MD5 slug, so a successful insert
		 * produces exactly one row per URL. `LIMIT 1` matches that invariant
		 * and keeps the query cheap on the hot `exists_local()` path.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s LIMIT 1",
				self::POST_TYPE,
				\md5( $normalized )
			)
		);

		return \array_map( 'intval', $ids );
	}

	/**
	 * Add one or more URLs to the local tombstone registry.
	 *
	 * "Buries" URLs by adding them to the local tombstone URL registry.
	 * URLs are normalized before storage; duplicate calls for the same URL
	 * are a no-op because the `post_name` slug is the MD5 of the
	 * normalized URL.
	 *
	 * @param string ...$urls The URLs to add to the tombstone registry.
	 */
	public static function bury( ...$urls ) {
		foreach ( $urls as $url ) {
			if ( ! \filter_var( $url, \FILTER_VALIDATE_URL ) ) {
				continue;
			}

			$normalized = normalize_url( $url );

			if ( ! empty( self::find_post_ids_by_url( $normalized ) ) ) {
				continue;
			}

			/*
			 * Store the original URL in `guid` so it is human-readable and
			 * survives `esc_url()` without scheme mangling. The hash slug
			 * in `post_name` is what we actually key lookups on.
			 */
			$post_id = \wp_insert_post(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'publish',
					'post_name'   => \md5( $normalized ),
					'guid'        => $url,
					'post_author' => 0,
				),
				true
			);

			if ( \is_wp_error( $post_id ) || ! $post_id ) {
				/**
				 * Fires when `bury()` fails to write a tombstone row.
				 *
				 * The URL is silently not tombstoned in this case — the
				 * request path will respond as it would for any other
				 * non-existent post. Useful as a monitoring hook.
				 *
				 * @since 8.3.0
				 *
				 * @param string             $normalized The normalized URL that failed to bury.
				 * @param \WP_Error|int|null $post_id    The `wp_insert_post()` return value.
				 */
				\do_action( 'activitypub_tombstone_bury_failed', $normalized, $post_id );
			}
		}
	}

	/**
	 * Remove one or more URLs from the local tombstone registry.
	 *
	 * Removes URLs from the local tombstone URL registry.
	 * URLs are normalized before comparison to ensure consistent matching.
	 * This marks the URLs as no longer tombstoned for future local checks.
	 *
	 * @param string ...$urls The URLs to remove from the tombstone registry.
	 */
	public static function remove( ...$urls ) {
		$normalized_urls = array();
		foreach ( $urls as $url ) {
			if ( \filter_var( $url, \FILTER_VALIDATE_URL ) ) {
				$normalized_urls[] = normalize_url( $url );
			}
		}

		if ( empty( $normalized_urls ) ) {
			return;
		}

		$normalized_urls = \array_values( \array_unique( $normalized_urls ) );

		foreach ( $normalized_urls as $normalized ) {
			foreach ( self::find_post_ids_by_url( $normalized ) as $post_id ) {
				\wp_delete_post( $post_id, true );
			}
		}

		$legacy = \get_option( 'activitypub_tombstone_urls', false );
		if ( ! \is_array( $legacy ) ) {
			return;
		}

		$filtered = \array_values( \array_diff( $legacy, $normalized_urls ) );
		if ( \count( $filtered ) === \count( $legacy ) ) {
			return;
		}

		if ( empty( $filtered ) ) {
			\delete_option( 'activitypub_tombstone_urls' );
		} else {
			\update_option( 'activitypub_tombstone_urls', $filtered );
		}
	}

	/**
	 * Delete every tombstone post and the legacy option.
	 *
	 * Used during plugin uninstall to clean up all local tombstones.
	 *
	 * @since 8.3.0
	 *
	 * @return int The number of tombstone posts deleted.
	 */
	public static function delete_all() {
		global $wpdb;

		$post_ids = \array_map(
			'intval',
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
					self::POST_TYPE
				)
			)
		);

		$deleted = 0;
		foreach ( $post_ids as $post_id ) {
			if ( \wp_delete_post( $post_id, true ) ) {
				++$deleted;
			}
		}

		\delete_option( 'activitypub_tombstone_urls' );

		return $deleted;
	}

	/**
	 * Delete tombstones older than the retention window.
	 *
	 * Processes up to `$batch_size` tombstones per call. Retention is
	 * non-urgent: large backlogs drain across multiple daily runs of the
	 * `activitypub_tombstone_purge` cron event.
	 *
	 * @since 8.3.0
	 *
	 * @param int $batch_size Max number of tombstones to delete per call.
	 * @return int The number of tombstones deleted.
	 */
	public static function purge( $batch_size = 200 ) {
		/**
		 * Filters the retention window for local tombstones, in days.
		 *
		 * Set to 0 or a negative value to disable automatic purge.
		 *
		 * @since 8.3.0
		 *
		 * @param int $days Retention window in days. Default 90.
		 */
		$days = (int) \apply_filters( 'activitypub_tombstone_retention_days', 90 );

		if ( $days <= 0 ) {
			return 0;
		}

		$cutoff = \gmdate( 'Y-m-d H:i:s', \time() - $days * DAY_IN_SECONDS );

		$ids = \get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $batch_size,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'date_query'     => array(
					array(
						'column' => 'post_date_gmt',
						'before' => $cutoff,
					),
				),
			)
		);

		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( \wp_delete_post( (int) $id, true ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}
}
