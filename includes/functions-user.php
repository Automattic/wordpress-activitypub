<?php
/**
 * User functions.
 *
 * Functions for working with users and actors in ActivityPub context.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;

/**
 * Returns a users WebFinger "resource".
 *
 * @deprecated 7.1.0 Use {@see \Activitypub\Webfinger::get_user_resource} instead.
 *
 * @param int $user_id The user ID.
 *
 * @return string The User resource.
 */
function get_webfinger_resource( $user_id ) {
	\_deprecated_function( __FUNCTION__, '7.1.0', 'Activitypub\Webfinger::get_user_resource' );

	return Webfinger::get_user_resource( $user_id );
}

/**
 * Returns the followers of a given user.
 *
 * @param int $user_id The user ID.
 *
 * @return array The followers.
 */
function get_followers( $user_id ) {
	return Followers::get_many( $user_id );
}

/**
 * Count the number of followers for a given user.
 *
 * @param int $user_id The user ID.
 *
 * @return int The number of followers.
 */
function count_followers( $user_id ) {
	return Followers::count( $user_id );
}

/**
 * Examine a url and try to determine the author ID it represents.
 *
 * Checks are supposedly from the hosted site blog.
 *
 * @param string $url Permalink to check.
 *
 * @return int|null User ID, or null on failure.
 */
function url_to_authorid( $url ) {
	global $wp_rewrite;

	// Check if url has the same host.
	$request_host = \wp_parse_url( $url, \PHP_URL_HOST );
	if ( \wp_parse_url( \home_url(), \PHP_URL_HOST ) !== $request_host && get_option( 'activitypub_old_host' ) !== $request_host ) {
		return null;
	}

	// First, check to see if there is an 'author=N' to match against.
	if ( \preg_match( '/[?&]author=(\d+)/i', $url, $values ) ) {
		return \absint( $values[1] );
	}

	// Check to see if we are using rewrite rules.
	$rewrite = $wp_rewrite->wp_rewrite_rules();

	// Not using rewrite rules, and 'author=N' method failed, so we're out of options.
	if ( empty( $rewrite ) ) {
		return null;
	}

	// Generate rewrite rule for the author url.
	$author_rewrite = $wp_rewrite->get_author_permastruct();
	$author_regexp  = \str_replace( '%author%', '', $author_rewrite );

	// Match the rewrite rule with the passed url.
	if ( \preg_match( '/https?:\/\/(.+)' . \preg_quote( $author_regexp, '/' ) . '([^\/]+)/i', $url, $match ) ) {
		$user = \get_user_by( 'slug', $match[2] );
		if ( $user ) {
			return $user->ID;
		}
	}

	return null;
}

/**
 * This function checks if a user is enabled for ActivityPub.
 *
 * @param int|string $user_id The user ID.
 *
 * @return boolean True if the user is enabled, false otherwise.
 */
function user_can_activitypub( $user_id ) {
	if ( ! is_numeric( $user_id ) ) {
		return false;
	}

	switch ( $user_id ) {
		case Actors::APPLICATION_USER_ID:
			$enabled = true; // Application user is always enabled.
			break;

		case Actors::BLOG_USER_ID:
			$enabled = ! is_user_type_disabled( 'blog' );
			break;

		default:
			if ( ! \get_user_by( 'id', $user_id ) ) {
				$enabled = false;
				break;
			}

			if ( is_user_type_disabled( 'user' ) ) {
				$enabled = false;
				break;
			}

			$enabled = \user_can( $user_id, 'activitypub' );
	}

	/**
	 * Allow plugins to enable/disable users for ActivityPub.
	 *
	 * @param boolean $enabled True if the user is enabled, false otherwise.
	 * @param int     $user_id The user ID.
	 */
	return apply_filters( 'activitypub_user_can_activitypub', $enabled, $user_id );
}

/**
 * Checks if a User-Type is disabled for ActivityPub.
 *
 * This function is used to check if the 'blog' or 'user'
 * type is disabled for ActivityPub.
 *
 * @param string $type User type. 'blog' or 'user'.
 *
 * @return boolean True if the user type is disabled, false otherwise.
 */
function is_user_type_disabled( $type ) {
	switch ( $type ) {
		case 'blog':
			if ( \defined( 'ACTIVITYPUB_SINGLE_USER_MODE' ) ) {
				if ( ACTIVITYPUB_SINGLE_USER_MODE ) {
					$disabled = false;
					break;
				}
			}

			if ( \defined( 'ACTIVITYPUB_DISABLE_BLOG_USER' ) ) {
				$disabled = ACTIVITYPUB_DISABLE_BLOG_USER;
				break;
			}

			if ( ACTIVITYPUB_ACTOR_MODE === \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) ) {
				$disabled = true;
				break;
			}

			$disabled = false;
			break;
		case 'user':
			if ( \defined( 'ACTIVITYPUB_SINGLE_USER_MODE' ) ) {
				if ( ACTIVITYPUB_SINGLE_USER_MODE ) {
					$disabled = true;
					break;
				}
			}

			if ( \defined( 'ACTIVITYPUB_DISABLE_USER' ) ) {
				$disabled = ACTIVITYPUB_DISABLE_USER;
				break;
			}

			if ( ACTIVITYPUB_BLOG_MODE === \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) ) {
				$disabled = true;
				break;
			}

			$disabled = false;
			break;
		default:
			// Treat unknown user types as disabled to ensure a consistent boolean return value.
			$disabled = true;
			break;
	}

	/**
	 * Allow plugins to disable user types for ActivityPub.
	 *
	 * @param boolean $disabled True if the user type is disabled, false otherwise.
	 * @param string  $type     The User-Type.
	 */
	return apply_filters( 'activitypub_is_user_type_disabled', $disabled, $type );
}

/**
 * Check if the blog is in single-user mode.
 *
 * @return boolean True if the blog is in single-user mode, false otherwise.
 */
function is_single_user() {
	if (
		false === is_user_type_disabled( 'blog' ) &&
		true === is_user_type_disabled( 'user' )
	) {
		return true;
	}

	return false;
}

/**
 * Get active users based on a given duration.
 *
 * @param int $duration Optional. The duration to check in month(s). Default 1.
 *
 * @return int The number of active users.
 */
function get_active_users( $duration = 1 ) {
	$duration      = intval( $duration );
	$transient_key = sprintf( 'monthly_active_users_%d', $duration );
	$count         = get_transient( $transient_key );

	if ( false === $count ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( DISTINCT post_author ) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_modified >= DATE_SUB( NOW(), INTERVAL %d MONTH )",
				$duration
			)
		);

		set_transient( $transient_key, $count, DAY_IN_SECONDS );
	}

	// If 0 authors were active.
	if ( 0 === $count ) {
		return 0;
	}

	// If single user mode.
	if ( is_single_user() ) {
		return 1;
	}

	// If blog user is disabled.
	if ( ! user_can_activitypub( Actors::BLOG_USER_ID ) ) {
		$active = (int) $count;
	} else {
		// Also count blog user.
		$active = (int) $count + 1;
	}

	// Ensure active users doesn't exceed total users.
	return min( $active, get_total_users() );
}

/**
 * Get the total number of users.
 *
 * @return int The total number of users.
 */
function get_total_users() {
	// If single user mode.
	if ( is_single_user() ) {
		return 1;
	}

	$users = \get_users(
		array(
			'capability__in' => array( 'activitypub' ),
		)
	);

	if ( is_array( $users ) ) {
		$users = count( $users );
	} else {
		$users = 1;
	}

	// If blog user is disabled.
	if ( ! user_can_activitypub( Actors::BLOG_USER_ID ) ) {
		return (int) $users;
	}

	return (int) $users + 1;
}

/**
 * Get the ActivityPub ID of a User by the WordPress User ID.
 *
 * Fall back to blog user if in blog mode or if user is not found.
 *
 * @param int $id The WordPress User ID.
 *
 * @return string|false The ActivityPub ID (a URL) of the User or false if not found.
 */
function get_user_id( $id ) {
	$mode = \get_option( 'activitypub_actor_mode', 'default' );

	if ( ACTIVITYPUB_BLOG_MODE === $mode ) {
		$user = Actors::get_by_id( Actors::BLOG_USER_ID );
	} else {
		$user = Actors::get_by_id( $id );

		if ( \is_wp_error( $user ) ) {
			$user = Actors::get_by_id( Actors::BLOG_USER_ID );
		}
	}

	if ( \is_wp_error( $user ) ) {
		return false;
	}

	return $user->get_id();
}
