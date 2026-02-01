<?php
/**
 * Like handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;
use Activitypub\Comment;

use function Activitypub\object_to_uri;

/**
 * Handle Like requests.
 */
class Like {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_like', array( self::class, 'incoming' ), 10, 2 );
		\add_action( 'activitypub_handled_outbox_like', array( self::class, 'outgoing' ), 10, 4 );
		\add_filter( 'activitypub_get_outbox_activity', array( self::class, 'outbox_activity' ) );
	}

	/**
	 * Handle incoming "Like" requests from remote actors.
	 *
	 * @param array     $like     The Activity array.
	 * @param int|int[] $user_ids The user ID(s).
	 */
	public static function incoming( $like, $user_ids ) {
		if ( ! Comment::is_comment_type_enabled( 'like' ) ) {
			return;
		}

		$url = object_to_uri( $like['object'] );

		if ( empty( $url ) ) {
			return;
		}

		$exists = Comment::object_id_to_comment( esc_url_raw( $url ) );
		if ( $exists ) {
			return;
		}

		$success = false;
		$result  = Interactions::add_reaction( $like );

		if ( $result && ! is_wp_error( $result ) ) {
			$success = true;
			$result  = get_comment( $result );
		}

		/**
		 * Fires after an ActivityPub Like activity has been handled.
		 *
		 * @param array                                         $like     The ActivityPub activity data.
		 * @param int[]                                         $user_ids The local user IDs.
		 * @param bool                                          $success  True on success, false otherwise.
		 * @param array|false|int|string|\WP_Comment|\WP_Error $result   The WP_Comment object of the created like comment, or null if creation failed.
		 */
		\do_action( 'activitypub_handled_like', $like, (array) $user_ids, $success, $result );
	}

	/**
	 * Handle outgoing "Like" activities from local actors.
	 *
	 * Records a like from the local user on remote content.
	 *
	 * @param array                          $data       The activity data array.
	 * @param int                            $user_id    The user ID.
	 * @param \Activitypub\Activity\Activity $activity   The Activity object.
	 * @param int                            $outbox_id  The outbox post ID.
	 */
	public static function outgoing( $data, $user_id, $activity, $outbox_id ) {
		$object_url = object_to_uri( $data['object'] ?? '' );

		if ( empty( $object_url ) ) {
			return;
		}

		/**
		 * Fires after an outgoing Like activity has been processed.
		 *
		 * @param string $object_url The URL of the liked object.
		 * @param array  $data       The activity data.
		 * @param int    $user_id    The user ID.
		 * @param int    $outbox_id  The outbox post ID.
		 */
		\do_action( 'activitypub_outbox_like_sent', $object_url, $data, $user_id, $outbox_id );
	}

	/**
	 * Set the object to the object ID.
	 *
	 * @param \Activitypub\Activity\Activity $activity The Activity object.
	 * @return \Activitypub\Activity\Activity The filtered Activity object.
	 */
	public static function outbox_activity( $activity ) {
		if ( 'Like' === $activity->get_type() ) {
			$activity->set_object( object_to_uri( $activity->get_object() ) );
		}

		return $activity;
	}

	/**
	 * Handle "Like" requests.
	 *
	 * @deprecated unreleased Use Like::incoming() instead.
	 *
	 * @param array     $like     The Activity array.
	 * @param int|int[] $user_ids The user ID(s).
	 */
	public static function handle_like( $like, $user_ids ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Like::incoming()' );

		return self::incoming( $like, $user_ids );
	}

	/**
	 * Handle outbox "Like" activities.
	 *
	 * @deprecated unreleased Use Like::outgoing() instead.
	 *
	 * @param array                          $data       The activity data array.
	 * @param int                            $user_id    The user ID.
	 * @param \Activitypub\Activity\Activity $activity   The Activity object.
	 * @param int                            $outbox_id  The outbox post ID.
	 */
	public static function handle_outbox_like( $data, $user_id, $activity, $outbox_id ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Like::outgoing()' );

		return self::outgoing( $data, $user_id, $activity, $outbox_id );
	}
}
