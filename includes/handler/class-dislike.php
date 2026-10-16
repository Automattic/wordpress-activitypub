<?php
/**
 * Dislike handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;
use Activitypub\Comment;

use function Activitypub\object_to_uri;

/**
 * Handle Dislike requests.
 *
 * @since unreleased
 */
class Dislike {
	/**
	 * Initialize the class, registering WordPress hooks.
	 *
	 * @return void
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_dislike', array( self::class, 'handle_dislike' ), 10, 2 );
	}

	/**
	 * Handles "Dislike" requests.
	 *
	 * @param array     $dislike  The Activity array.
	 * @param int|int[] $user_ids The user ID(s).
	 *
	 * @return void
	 */
	public static function handle_dislike( $dislike, $user_ids ) {
		// Dislikes are opt-in.
		if ( '1' !== \get_option( 'activitypub_allow_dislikes', '0' ) ) {
			return;
		}

		if ( empty( object_to_uri( $dislike['object'] ?? '' ) ) ) {
			return;
		}

		// Dedupe on the activity ID, like Likes, so repeat deliveries stay idempotent.
		$exists = Comment::object_id_to_comment( \esc_url_raw( (string) object_to_uri( $dislike ) ), array( 'status' => 'any' ) );
		if ( $exists ) {
			return;
		}

		$success = false;
		$result  = Interactions::add_reaction( $dislike );

		if ( $result && ! \is_wp_error( $result ) ) {
			$success = true;
			$result  = \get_comment( $result );
		}

		/**
		 * Fires after an ActivityPub Dislike activity has been handled.
		 *
		 * @since unreleased
		 *
		 * @param array                                         $dislike  The ActivityPub activity data.
		 * @param int[]                                         $user_ids The local user IDs.
		 * @param bool                                          $success  True on success, false otherwise.
		 * @param array|false|int|string|\WP_Comment|\WP_Error $result   The created comment, or the failure.
		 */
		\do_action( 'activitypub_handled_dislike', $dislike, (array) $user_ids, $success, $result );
	}
}
