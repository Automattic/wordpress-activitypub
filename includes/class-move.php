<?php
/**
 * Move class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Actor;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;

/**
 * ActivityPub (Account) Move Class
 *
 * @author Matthias Pfefferle
 */
class Move {
	/**
	 * Move an ActivityPub account from one location to another.
	 *
	 * @param string $from The current account URL.
	 * @param string $to   The new account URL.
	 *
	 * @return int|bool|\WP_Error The ID of the outbox item or false or WP_Error on failure.
	 */
	public static function account( $from, $to ) {
		if ( is_same_domain( $from ) && is_same_domain( $to ) ) {
			return self::internally( $from, $to );
		}

		return self::externally( $from, $to );
	}

	/**
	 * Move an ActivityPub Actor from one location (internal) to another (external).
	 *
	 * This helps migrating local profiles to a new external profile:
	 *
	 * `Move::externally( 'https://example.com/?author=123', 'https://mastodon.example/users/foo' );`
	 *
	 * @param string $from The current account URL.
	 * @param string $to   The new account URL.
	 *
	 * @return int|bool|\WP_Error The ID of the outbox item or false or WP_Error on failure.
	 */
	public static function externally( $from, $to ) {
		$user = Actors::get_by_various( $from );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		// Update the movedTo property.
		if ( $user->get__id() > 0 ) {
			\update_user_option( $user->get__id(), 'activitypub_moved_to', $to );
		} else {
			\update_option( 'activitypub_blog_user_moved_to', $to );
		}

		$response = Http::get_remote_object( $to );

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$target_actor = new Actor();
		$target_actor->from_array( $response );

		// Check if the `Move` Activity is valid.
		$also_known_as = $target_actor->get_also_known_as() ?? array();
		if ( ! in_array( $from, $also_known_as, true ) ) {
			return new \WP_Error( 'invalid_target', __( 'Invalid target', 'activitypub' ) );
		}

		$activity = new Activity();
		$activity->set_type( 'Move' );
		$activity->set_actor( $user->get_id() );
		$activity->set_origin( $user->get_id() );
		$activity->set_object( $user->get_id() );
		$activity->set_target( $target_actor->get_id() );

		// Add to outbox.
		return add_to_outbox( $activity, null, $user->get__id(), ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );
	}

	/**
	 * Internal Move.
	 *
	 * Move an ActivityPub Actor from one location (internal) to another (internal).
	 *
	 * This helps migrating abandoned profiles to `Move` to other profiles:
	 *
	 * `Move::internally( 'https://example.com/?author=123', 'https://example.com/?author=321' );`
	 *
	 * ... or to change Actor-IDs like:
	 *
	 * `Move::internally( 'https://example.com/author/foo', 'https://example.com/?author=123' );`
	 *
	 * @param string $from The current account URL.
	 * @param string $to   The new account URL.
	 *
	 * @return int|bool|\WP_Error The ID of the outbox item or false or WP_Error on failure.
	 */
	public static function internally( $from, $to ) {
		$user = Actors::get_by_various( $from );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		// Add the old account URL to alsoKnownAs.
		if ( $user->get__id() > 0 ) {
			self::update_user_also_known_as( $user->get__id(), $from );
			\update_user_option( $user->get__id(), 'activitypub_moved_to', $to );
		} else {
			self::update_blog_also_known_as( $from );
			\update_option( 'activitypub_blog_user_moved_to', $to );
		}

		// check if `$from` is a URL or an ID.
		if ( \filter_var( $from, FILTER_VALIDATE_URL ) ) {
			$actor = $from;
		} else {
			$actor = $user->get_id();
		}

		$activity = new Activity();
		$activity->set_type( 'Move' );
		$activity->set_actor( $actor );
		$activity->set_origin( $actor );
		$activity->set_object( $actor );
		$activity->set_target( $to );

		return add_to_outbox( $activity, null, $user->get__id(), ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC );
	}

	/**
	 * Update the alsoKnownAs property of a user.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $from    The current account URL.
	 */
	private static function update_user_also_known_as( $user_id, $from ) {
		// phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$also_known_as   = \get_user_option( 'activitypub_also_known_as', $user_id ) ?: array();
		$also_known_as[] = $from;

		\update_user_option( $user_id, 'activitypub_also_known_as', $also_known_as );
	}

	/**
	 * Update the alsoKnownAs property of the blog.
	 *
	 * @param string $from The current account URL.
	 */
	private static function update_blog_also_known_as( $from ) {
		$also_known_as   = \get_option( 'activitypub_blog_user_also_known_as', array() );
		$also_known_as[] = $from;

		\update_option( 'activitypub_blog_user_also_known_as', $also_known_as );
	}
}
