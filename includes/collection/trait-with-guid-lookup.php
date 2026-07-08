<?php
/**
 * GUID Lookup Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

/**
 * GUID Lookup Trait.
 *
 * The CPT-backed collections key their items by the ActivityPub object ID, stored in
 * the post `guid` column. `WP_Query` cannot query `guid`, so a small prepared statement
 * is the legitimate way to resolve it. This trait keeps that single query in one place.
 *
 * The GUID is normalized once with `esc_url_raw` — matching how the collections store it
 * (`esc_url_raw`/raw id, never the entity-encoding `esc_url`) — and SQL-escaping is left
 * to `prepare()`. The post type comes from the using class via late static binding.
 */
trait With_Guid_Lookup {

	/**
	 * Resolve a single post ID by its GUID.
	 *
	 * @param string $guid The object GUID.
	 *
	 * @return int|null The post ID, or null when none matches.
	 */
	protected static function get_id_by_guid( $guid ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE guid=%s AND post_type=%s",
				\esc_url_raw( $guid ),
				static::POST_TYPE
			)
		);
	}

	/**
	 * Resolve every post ID sharing a GUID, oldest first.
	 *
	 * Used to reconcile duplicates created by concurrent writes.
	 *
	 * @param string $guid The object GUID.
	 *
	 * @return string[] The matching post IDs, ordered by ID ascending.
	 */
	protected static function get_ids_by_guid( $guid ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE guid=%s AND post_type=%s ORDER BY ID ASC",
				\esc_url_raw( $guid ),
				static::POST_TYPE
			)
		);
	}
}
