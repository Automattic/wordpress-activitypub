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
use Activitypub\Model\Blog;
use Activitypub\Model\User;

/**
 * ActivityPub (Account) Move Class
 *
 * @author Matthias Pfefferle
 */
class Move {

	/**
	 * Initialize the Move class.
	 */
	public static function init() {
		/**
		 * Filter to enable automatically moving Fediverse accounts when the domain changes.
		 *
		 * @param bool $domain_moves_enabled Whether domain moves are enabled.
		 */
		$domain_moves_enabled = apply_filters( 'activitypub_enable_primary_domain_moves', false );

		if ( $domain_moves_enabled ) {
			// Add the filter to change the domain.
			\add_filter( 'pre_update_option_home', array( self::class, 'pre_update_option_home' ), 10, 2 );

			if ( get_option( 'activitypub_old_domain' ) ) {
				\add_action( 'activitypub_construct_model_actor', array( self::class, 'maybe_initiate_old_user' ) );
				\add_action( 'activitypub_pre_send_to_inboxes', array( self::class, 'pre_send_to_inboxes' ) );

				if ( ! is_user_type_disabled( 'blog' ) ) {
					add_filter( 'activitypub_pre_get_by_username', array( self::class, 'old_blog_username' ), 10, 2 );
				}
			}
		}
	}

	/**
	 * Pre-update option home.
	 *
	 * This method is called before the home URL is updated
	 * so we can preserve actor json objects with the old domain.
	 *
	 * @param string $new_domain The new domain.
	 * @param string $old_domain The old domain.
	 *
	 * @return string The new domain.
	 */
	public static function pre_update_option_home( $new_domain, $old_domain ) {
		// Check if the new domain is different from the old one.
		if ( $new_domain !== $old_domain ) {
			// Call the change_domain method to handle the migration.
			self::change_domain( $old_domain, $new_domain );
		}

		return $new_domain;
	}

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

	/**
	 * Change domain for all ActivityPub Actors.
	 *
	 * This method handles domain migration according to the ActivityPub Data Portability spec.
	 * It stores the old domain and calls Move::internally for each available profile.
	 * It also caches the JSON representation of the old Actor for future lookups.
	 *
	 * @param string $from The old domain.
	 * @param string $to   The new domain.
	 *
	 * @return array Array of results from Move::internally calls.
	 */
	public static function change_domain( $from, $to ) {
		// Get all actors that need to be migrated.
		$actors = Actors::get_collection();

		// Also include the blog actor if active.
		if ( ! is_user_type_disabled( 'blog' ) ) {
			$blog_actor = Actors::get_by_id( Actors::BLOG_USER_ID );
			if ( ! \is_wp_error( $blog_actor ) ) {
				$actors[] = $blog_actor;
			}
		}

		$results = array();

		// Process each actor.
		foreach ( $actors as $actor ) {
			$actor_id = $actor->get_id();

			// Replace the old domain with the new domain in the actor ID.
			$new_actor_id = str_replace( $from, $to, $actor_id );

			// Call Move::internally for this actor.
			$result = self::internally( $actor_id, $new_actor_id );

			// Save the current actor data after migration.
			if ( $actor instanceof Blog ) {
				\update_option( 'activitypub_blog_user_old_domain_data', $actor->to_json(), false );
			} else {
				\update_user_option( $actor->get__id(), 'activitypub_old_domain_data', $actor->to_json(), false );
			}

			$results[] = array(
				'actor'  => $actor_id,
				'result' => $result,
			);
		}

		// Store the old domain for future reference.
		\update_option( 'activitypub_old_domain', \wp_parse_url( $from, PHP_URL_HOST ) );

		return $results;
	}

	/**
	 * Maybe initiate old user.
	 *
	 * This method checks if the current request domain matches the old domain.
	 * If it does, it retrieves the cached data for the user and populates the instance.
	 *
	 * @param Blog|User $instance The Blog or User instance to populate.
	 */
	public static function maybe_initiate_old_user( $instance ) {
		if ( ! Query::get_instance()->is_old_host_request() ) {
			return;
		}

		if ( $instance instanceof Blog ) {
			$cached_data = \get_option( 'activitypub_blog_user_old_domain_data' );
		} elseif ( $instance instanceof User ) {
			$cached_data = \get_user_option( 'activitypub_old_domain_data', $instance->get__id() );
		}

		if ( ! empty( $cached_data ) ) {
			$instance->from_json( $cached_data );
		}
	}

	/**
	 * Pre-send to inboxes.
	 *
	 * @param string $json The ActivityPub Activity JSON.
	 */
	public static function pre_send_to_inboxes( $json ) {
		$json = json_decode( $json, true );

		if ( 'Move' !== $json['type'] ) {
			return;
		}

		if ( is_same_domain( $json['object'] ) ) {
			return;
		}

		Query::get_instance()->set_old_domain_request();
	}

	/**
	 * Filter to return the old blog username.
	 *
	 * @param null   $pre      The pre-existing value.
	 * @param string $username The username to check.
	 *
	 * @return Blog|null The old blog instance or null.
	 */
	public static function old_blog_username( $pre, $username ) {
		$old_domain = \get_option( 'activitypub_old_domain' );

		// Special case for Blog Actor on old domain.
		if ( $old_domain === $username && Query::get_instance()->is_old_host_request() ) {
			// Return a new Blog instance which will load the cached data in its constructor.
			$pre = new Blog();
		}

		return $pre;
	}
}
