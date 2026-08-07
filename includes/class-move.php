<?php
/**
 * Move class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Actor;
use Activitypub\Collection\Actors;
use Activitypub\Model\Blog;
use Activitypub\Model\User;
use Activitypub\Scheduler\Actor as Actor_Scheduler;

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
		$domain_moves_enabled = \apply_filters( 'activitypub_enable_primary_domain_moves', false );

		if ( $domain_moves_enabled ) {
			// Add the filter to change the domain.
			\add_filter( 'update_option_home', array( self::class, 'change_domain' ), 10, 2 );

			if ( \get_option( 'activitypub_old_host' ) ) {
				\add_action( 'activitypub_construct_model_actor', array( self::class, 'maybe_initiate_old_user' ) );
				\add_action( 'activitypub_pre_send_to_inboxes', array( self::class, 'pre_send_to_inboxes' ) );

				if ( ! is_user_type_disabled( 'blog' ) ) {
					\add_filter( 'activitypub_pre_get_by_username', array( self::class, 'old_blog_username' ), 10, 2 );
				}
			}
		}

		// Serve the retired representation (with movedTo) when an actor is fetched via its old
		// permalink id. Always registered, since the id migration is not gated by domain moves.
		\add_action( 'activitypub_construct_model_actor', array( self::class, 'maybe_initiate_retired_actor' ) );
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
	 * This helps with migrating local profiles to a new external profile:
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

		$response = Http::get_remote_object( $to );

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$target_actor = new Actor();
		$target_actor->from_array( $response );

		// The canonical id is both federated and advertised, so a target that declares none cannot be moved to.
		$target_id = $target_actor->get_id();
		if ( ! $target_id ) {
			return new \WP_Error( 'invalid_target', \__( 'Invalid target', 'activitypub' ) );
		}

		/*
		 * The move is only valid if the target links back. Receiving servers accept it only when the
		 * id we send as the Move's `object` is listed in the target's `alsoKnownAs`, so verify that
		 * exact id, not the (possibly non-canonical) input URL.
		 */
		$also_known_as = $target_actor->get_also_known_as() ?? array();
		if ( ! \in_array( $user->get_id(), $also_known_as, true ) ) {
			return new \WP_Error( 'invalid_target', \__( 'Invalid target', 'activitypub' ) );
		}

		/*
		 * Advertise the move only after the target is verified, so a failed attempt never leaves the
		 * actor pointing at an unverified target. Store the canonical id, not the input URL: receivers
		 * match the advertised `movedTo` against the Move's `target` and skip the move when they differ.
		 */
		if ( $user->get__id() > 0 ) {
			\update_user_option( $user->get__id(), 'activitypub_moved_to', $target_id );
		} else {
			\update_option( 'activitypub_blog_user_moved_to', $target_id );
		}

		$activity = new Activity();
		$activity->set_type( 'Move' );
		$activity->set_actor( $user->get_id() );
		$activity->set_origin( $user->get_id() );
		$activity->set_object( $user->get_id() );
		$activity->set_target( $target_id );

		$outbox_id = add_to_outbox( $activity, null, $user->get__id(), ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );

		if ( ! $outbox_id || \is_wp_error( $outbox_id ) ) {
			return $outbox_id;
		}

		/*
		 * Notify followers of the new movedTo by federating a profile Update (FEP-7628). Queue it
		 * after the Move so a follower that reacts to `movedTo` still processes the migration first.
		 */
		Actor_Scheduler::schedule_profile_update( $user->get__id() );

		return $outbox_id;
	}

	/**
	 * Internal Move.
	 *
	 * Move an ActivityPub Actor from one location (internal) to another (internal).
	 *
	 * This helps with migrating abandoned profiles to `Move` to other profiles:
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

		// The old id is the input when it is a URL, otherwise the source's canonical id.
		$old_id = \filter_var( $from, FILTER_VALIDATE_URL ) ? $from : $user->get_id();

		return self::internally_by_actor( $user, Actors::get_by_various( $to ), $old_id, $to );
	}

	/**
	 * Perform an internal Move for already-resolved actors.
	 *
	 * Used when the caller already holds the source and target actors and the exact old/new ids,
	 * rather than a URL that still resolves. The id migration relies on this because the old
	 * permalink URL no longer resolves once `get_id()` stops emitting it.
	 *
	 * @since unreleased
	 *
	 * @param User|Blog           $source The actor being moved (the old identity).
	 * @param User|Blog|\WP_Error $target The actor the move points to; may equal the source for a self re-identification.
	 * @param string              $old_id The old actor id (the Move's `object`).
	 * @param string              $new_id The new actor id (the Move's `target`).
	 *
	 * @return int|bool|\WP_Error The ID of the outbox item or false or WP_Error on failure.
	 */
	public static function internally_by_actor( $source, $target, $old_id, $new_id ) {
		// Point the old actor at the new one.
		if ( $source->get__id() > 0 ) {
			\update_user_option( $source->get__id(), 'activitypub_moved_to', $new_id );
		} else {
			\update_option( 'activitypub_blog_user_moved_to', $new_id );
		}

		/*
		 * The old id belongs in the *target's* alsoKnownAs, not the source's: receiving servers
		 * accept the Move only when the new actor links back to the old one. For a self
		 * re-identification the source and target are the same actor, so it is recorded there.
		 */
		if ( ! \is_wp_error( $target ) ) {
			if ( $target->get__id() > 0 ) {
				self::update_user_also_known_as( $target->get__id(), $old_id );
			} else {
				self::update_blog_also_known_as( $old_id );
			}
		}

		$activity = new Activity();
		$activity->set_type( 'Move' );
		$activity->set_actor( $old_id );
		$activity->set_origin( $old_id );
		$activity->set_object( $old_id );
		$activity->set_target( $new_id );

		$outbox_id = add_to_outbox( $activity, null, $source->get__id(), ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC );

		if ( ! $outbox_id || \is_wp_error( $outbox_id ) ) {
			return $outbox_id;
		}

		/*
		 * Notify followers of the changed profile on both actors by federating an Update (FEP-7628).
		 * Queued after the Move so a follower that reacts to `movedTo` still processes the migration first.
		 */
		Actor_Scheduler::schedule_profile_update( $source->get__id() );
		if ( ! \is_wp_error( $target ) && $target->get__id() !== $source->get__id() ) {
			Actor_Scheduler::schedule_profile_update( $target->get__id() );
		}

		return $outbox_id;
	}

	/**
	 * Update the alsoKnownAs property of a user.
	 *
	 * @param int    $user_id The user ID.
	 * @param string $from    The current account URL.
	 */
	private static function update_user_also_known_as( $user_id, $from ) {
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
	 * It stores the old host and calls Move::internally for each available profile.
	 * It also caches the JSON representation of the old Actor for future lookups.
	 *
	 * @param string $from The old domain.
	 * @param string $to   The new domain.
	 *
	 * @return array Array of results from Move::internally calls.
	 */
	public static function change_domain( $from, $to ) {
		// Get all actors that need to be migrated.
		$actors = Actors::get_all();

		$results   = array();
		$to_host   = \wp_parse_url( $to, \PHP_URL_HOST );
		$from_host = \wp_parse_url( $from, \PHP_URL_HOST );

		// Store the old host for future reference.
		\update_option( 'activitypub_old_host', $from_host );

		// Process each actor.
		foreach ( $actors as $actor ) {
			$actor_id = $actor->get_id();

			// Replace the new host with the old host in the actor ID.
			$old_actor_id = \str_replace( $to_host, $from_host, $actor_id );

			// Call Move::internally for this actor.
			$result = self::internally( $old_actor_id, $actor_id );

			if ( \is_wp_error( $result ) ) {
				/**
				 * Fires when an actor move fails during domain change.
				 *
				 * @since 8.1.0
				 *
				 * @param \WP_Error $result   The error that occurred.
				 * @param string    $actor_id The actor ID that failed to move.
				 */
				\do_action( 'activitypub_move_failed', $result, $actor_id );
				continue;
			}

			$json = \str_replace( $to_host, $from_host, $actor->to_json() );

			// Save the current actor data after migration.
			if ( $actor instanceof Blog ) {
				\update_option( 'activitypub_blog_user_old_host_data', $json, false );
			} else {
				\update_user_option( $actor->get__id(), 'activitypub_old_host_data', $json );
			}

			$results[] = array(
				'actor'  => $actor_id,
				'result' => $result,
			);
		}

		return $results;
	}

	/**
	 * Maybe initiate old user.
	 *
	 * This method checks if the current request domain matches the old host.
	 * If it does, it retrieves the cached data for the user and populates the instance.
	 *
	 * @param Blog|User $instance The Blog or User instance to populate.
	 */
	public static function maybe_initiate_old_user( $instance ) {
		if ( ! Query::get_instance()->is_old_host_request() ) {
			return;
		}

		if ( $instance instanceof Blog ) {
			$cached_data = \get_option( 'activitypub_blog_user_old_host_data' );
		} elseif ( $instance instanceof User ) {
			$cached_data = \get_user_option( 'activitypub_old_host_data', $instance->get__id() );
		}

		if ( ! empty( $cached_data ) ) {
			$instance->from_json( $cached_data );
		}
	}

	/**
	 * Store the retired representation of an actor's old permalink id.
	 *
	 * Snapshots the actor document with its id set to the old permalink URL, so a later request to
	 * that URL can serve it. The `movedTo` is not stored here: it is derived at read time from the
	 * `activitypub_moved_to` option (set by the move) against this old id, exactly as the domain
	 * move does.
	 *
	 * @since unreleased
	 *
	 * @param User|Blog $actor  The migrated actor (already resolved to its new id).
	 * @param string    $old_id The old permalink id to keep serving.
	 */
	public static function store_retired_permalink( $actor, $old_id ) {
		$data = \json_decode( $actor->to_json(), true );

		if ( ! \is_array( $data ) ) {
			return;
		}

		$data['id'] = $old_id;
		$json       = \wp_json_encode( $data );

		if ( $actor->get__id() > 0 ) {
			\update_user_option( $actor->get__id(), 'activitypub_retired_permalink_data', $json );
		} else {
			\update_option( 'activitypub_blog_user_retired_permalink_data', $json );
		}
	}

	/**
	 * Serve the retired permalink representation when the actor is fetched via its old id.
	 *
	 * Mirrors {@see self::maybe_initiate_old_user()}: the model stays unaware of the request, and
	 * this loads the stored snapshot only when {@see Query::is_permalink_actor_request()} matches.
	 *
	 * @since unreleased
	 *
	 * @param Blog|User $instance The Blog or User instance to populate.
	 */
	public static function maybe_initiate_retired_actor( $instance ) {
		if ( ! Query::get_instance()->is_permalink_actor_request() ) {
			return;
		}

		if ( $instance instanceof Blog ) {
			$data = \get_option( 'activitypub_blog_user_retired_permalink_data' );
		} elseif ( $instance instanceof User ) {
			$data = \get_user_option( 'activitypub_retired_permalink_data', $instance->get__id() );
		} else {
			return;
		}

		if ( ! empty( $data ) ) {
			$instance->from_json( $data );
		}
	}

	/**
	 * Pre-send to inboxes.
	 *
	 * @param string $json The ActivityPub Activity JSON.
	 */
	public static function pre_send_to_inboxes( $json ) {
		$json = \json_decode( $json, true );

		if ( 'Move' !== $json['type'] ) {
			return;
		}

		if ( is_same_domain( $json['object'] ) ) {
			return;
		}

		Query::get_instance()->set_old_host_request();
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
		$old_host = \get_option( 'activitypub_old_host' );

		// Special case for Blog Actor on old host.
		if ( $old_host === $username && Query::get_instance()->is_old_host_request() ) {
			// Return a new Blog instance which will load the cached data in its constructor.
			$pre = new Blog();
		}

		return $pre;
	}
}
