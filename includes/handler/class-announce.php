<?php
/**
 * Announce handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Http;
use Activitypub\Comment;
use Activitypub\Collection\Interactions;

use function Activitypub\object_to_uri;
use function Activitypub\is_activity_public;

/**
 * Handle Create requests.
 */
class Announce {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_announce',
			array( self::class, 'handle_announce' ),
			10,
			3
		);
	}

	/**
	 * Handles "Announce" requests.
	 *
	 * @param array                          $announcement The activity-object.
	 * @param int                            $user_id      The id of the local blog-user.
	 * @param \Activitypub\Activity\Activity $activity     The activity object.
	 */
	public static function handle_announce( $announcement, $user_id, $activity = null ) {
		// Check if Activity is public or not.
		if ( ! is_activity_public( $announcement ) ) {
			// @todo maybe send email
			return;
		}

		self::maybe_save_announce( $announcement, $user_id );

		if ( is_string( $announcement['object'] ) ) {
			$object = Http::get_remote_object( $announcement['object'] );
		} else {
			$object = $announcement['object'];
		}

		if ( ! $object || is_wp_error( $object ) ) {
			return;
		}

		if ( ! isset( $object['type'] ) ) {
			return;
		}

		$type = \strtolower( $object['type'] );

		/**
		 * Fires after an Announce has been received.
		 *
		 * @param array $object   The object.
		 * @param int   $user_id  The id of the local blog-user.
		 * @param array $activity The activity object.
		 */
		\do_action( 'activitypub_inbox', $object, $user_id, $type, $activity );

		/**
		 * Fires after an Announce of a specific type has been received.
		 *
		 * @param array $object   The object.
		 * @param int   $user_id  The id of the local blog-user.
		 * @param array $activity The activity object.
		 */
		\do_action( "activitypub_inbox_{$type}", $object, $user_id, $activity );
	}

	/**
	 * Try to save the Announce.
	 *
	 * @param array $activity The activity-object.
	 * @param int   $user_id  The id of the local blog-user.
	 */
	public static function maybe_save_announce( $activity, $user_id ) {
		$url = object_to_uri( $activity['object'] );

		if ( empty( $url ) ) {
			return;
		}

		$exists = Comment::object_id_to_comment( esc_url_raw( $url ) );
		if ( $exists ) {
			return;
		}

		$state    = Interactions::add_reaction( $activity );
		$reaction = null;

		if ( $state && ! is_wp_error( $state ) ) {
			$reaction = get_comment( $state );
		}

		/**
		 * Fires after an Announce has been saved.
		 *
		 * @param array $activity The activity-object.
		 * @param int   $user_id  The id of the local blog-user.
		 * @param mixed $state    The state of the reaction.
		 * @param mixed $reaction The reaction.
		 */
		do_action( 'activitypub_handled_announce', $activity, $user_id, $state, $reaction );
	}
}
