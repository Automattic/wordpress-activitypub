<?php
/**
 * Announce handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Interactions;
use Activitypub\Comment;
use Activitypub\Http;

use function Activitypub\is_activity;
use function Activitypub\is_activity_public;
use function Activitypub\object_to_uri;

/**
 * Handle Create requests.
 */
class Announce {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_announce', array( self::class, 'handle_announce' ), 10, 3 );
	}

	/**
	 * Handles "Announce" requests.
	 *
	 * @param array                          $announcement The activity-object.
	 * @param int|int[]                      $user_ids     The id(s) of the local blog-user(s).
	 * @param \Activitypub\Activity\Activity $activity     The activity object.
	 */
	public static function handle_announce( $announcement, $user_ids, $activity = null ) {
		// Check if Activity is public or not.
		if ( ! is_activity_public( $announcement ) ) {
			// @todo maybe send email
			return;
		}

		// Ignore announces from the blog actor.
		if ( Actors::BLOG_USER_ID === Actors::get_id_by_resource( $announcement['actor'] ) ) {
			return;
		}

		// Check if reposts are allowed.
		if ( ! Comment::is_comment_type_enabled( 'repost' ) ) {
			return;
		}

		self::maybe_save_announce( $announcement, $user_ids );

		$object_url = object_to_uri( $announcement['object'] );

		// Force no redirects for this object's request only, so the requested host stays the authoritative origin.
		$no_redirects = static function ( $args, $url ) use ( $object_url ) {
			if ( $url === $object_url ) {
				$args['redirection'] = 0;
			}
			return $args;
		};

		/*
		 * Fetch the activity from its own id rather than the inline copy the Announce
		 * carries: that copy is the announcer's, who is not necessarily the activity's
		 * author. Redirects are forbidden (above) and the cache is bypassed so the
		 * requested host is the authoritative origin — otherwise a redirect, or a
		 * response cached from an earlier redirect-following fetch, could resolve to
		 * attacker content while the host check below still saw the trusted host.
		 */
		\add_filter( 'http_request_args', $no_redirects, 10, 2 );
		$object = Http::get_remote_object( $object_url, false );
		\remove_filter( 'http_request_args', $no_redirects, 10 );

		if ( ! $object || is_wp_error( $object ) || ! is_array( $object ) ) {
			return;
		}

		if ( ! is_activity( $object ) ) {
			return;
		}

		$origin_host = \strtolower( (string) \wp_parse_url( (string) $object_url, \PHP_URL_HOST ) );
		$actor_host  = \strtolower( (string) \wp_parse_url( (string) object_to_uri( $object['actor'] ?? '' ), \PHP_URL_HOST ) );

		/*
		 * Only an actor's own server may vouch for an activity attributed to it, so the
		 * host it was fetched from must equal its actor's host — the same key-host ==
		 * actor-host binding verify_key_id() enforces for signed requests, generalised
		 * to every relayed activity type.
		 */
		if ( '' === $origin_host || '' === $actor_host || $origin_host !== $actor_host ) {
			return;
		}

		$type = \strtolower( $object['type'] );

		/**
		 * Fires after an Announce has been received.
		 *
		 * @param array                               $object   The object.
		 * @param int[]                               $user_ids The ids of the local blog-users.
		 * @param string                              $type     The type of the activity.
		 * @param \Activitypub\Activity\Activity|null $activity The activity object.
		 */
		\do_action( 'activitypub_inbox', $object, (array) $user_ids, $type, $activity );

		/**
		 * Fires after an Announce of a specific type has been received.
		 *
		 * @param array                               $object   The object.
		 * @param int[]                               $user_ids The ids of the local blog-users.
		 * @param \Activitypub\Activity\Activity|null $activity The activity object.
		 */
		\do_action( "activitypub_inbox_{$type}", $object, (array) $user_ids, $activity );
	}

	/**
	 * Try to save the Announce.
	 *
	 * @param array     $activity The activity-object.
	 * @param int|int[] $user_ids The id of the local blog-user.
	 */
	public static function maybe_save_announce( $activity, $user_ids ) {
		$url = object_to_uri( $activity );

		if ( empty( $url ) ) {
			return;
		}

		$exists = Comment::object_id_to_comment( esc_url_raw( $url ) );
		if ( $exists ) {
			return;
		}

		// If the object is a Create activity, extract the actual object from it.
		if ( isset( $activity['object']['type'] ) && 'Create' === $activity['object']['type'] ) {
			$activity['object'] = object_to_uri( $activity['object']['object'] );
		}

		$success = false;
		$result  = Interactions::add_reaction( $activity );

		if ( $result && ! is_wp_error( $result ) ) {
			$success = true;
			$result  = get_comment( $result );
		}

		/**
		 * Fires after an ActivityPub Announce activity has been handled.
		 *
		 * @param array                            $activity The ActivityPub activity data.
		 * @param int[]                            $user_ids The local user IDs.
		 * @param bool                             $success  True on success, false otherwise.
		 * @param array|string|int|\WP_Error|false $result   The WP_Comment object of the created announce/repost comment, or null if creation failed.
		 */
		\do_action( 'activitypub_handled_announce', $activity, (array) $user_ids, $success, $result );
	}
}
