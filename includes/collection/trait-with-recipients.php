<?php
/**
 * Recipients Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

/**
 * Recipients Trait.
 *
 * Shared recipient bookkeeping for the CPT-backed collections that fan a single
 * stored item out to multiple local users (Inbox and Remote_Posts). Recipients are
 * stored as repeated `_activitypub_user_id` post meta, one row per user.
 */
trait With_Recipients {

	/**
	 * Get all recipients for a stored item.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array Array of user IDs who are recipients.
	 */
	public static function get_recipients( $post_id ) {
		// Get all meta values with key '_activitypub_user_id' (single => false).
		$recipients = \get_post_meta( $post_id, '_activitypub_user_id', false );
		$recipients = \array_map( 'intval', $recipients );

		return $recipients;
	}

	/**
	 * Check if a user is a recipient of a stored item.
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
	 * Add a recipient to an existing stored item.
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
	 * Add multiple recipients to an existing stored item.
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
	 * Remove a recipient from a stored item.
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
