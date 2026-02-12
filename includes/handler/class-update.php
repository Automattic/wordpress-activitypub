<?php
/**
 * Update handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;
use Activitypub\Collection\Posts;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\get_remote_metadata_by_actor;
use function Activitypub\is_activity_reply;

/**
 * Handle Update requests.
 */
class Update {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_handled_inbox_update', array( self::class, 'incoming' ), 10, 3 );
		\add_action( 'activitypub_handled_outbox_update', array( self::class, 'outgoing' ), 10, 4 );
	}

	/**
	 * Handle incoming "Update" requests from remote actors.
	 *
	 * @param array                          $activity        The Activity object.
	 * @param int[]                          $user_ids        The user IDs. Always null for Update activities.
	 * @param \Activitypub\Activity\Activity $activity_object The activity object. Default null.
	 */
	public static function incoming( $activity, $user_ids, $activity_object ) {
		$object_type = $activity['object']['type'] ?? '';

		switch ( $object_type ) {
			/*
			 * Actor Types.
			 *
			 * @see https://www.w3.org/TR/activitystreams-vocabulary/#actor-types
			 */
			case 'Person':
			case 'Group':
			case 'Organization':
			case 'Service':
			case 'Application':
				self::update_actor( $activity, $user_ids );
				break;

			/*
			 * Object and Link Types.
			 *
			 * @see https://www.w3.org/TR/activitystreams-vocabulary/#object-types
			 */
			case 'Note':
			case 'Article':
			case 'Image':
			case 'Audio':
			case 'Video':
			case 'Event':
			case 'Document':
				self::update_object( $activity, $user_ids, $activity_object );
				break;

			/*
			 * Minimal Activity.
			 *
			 * @see https://www.w3.org/TR/activitystreams-core/#example-1
			 */
			default:
				break;
		}
	}

	/**
	 * Update an Object.
	 *
	 * @param array                          $activity        The Activity object.
	 * @param int[]|null                     $user_ids        The user IDs. Always null for Update activities.
	 * @param \Activitypub\Activity\Activity $activity_object The activity object. Default null.
	 */
	public static function update_object( $activity, $user_ids, $activity_object ) {
		$result  = new \WP_Error( 'activitypub_update_failed', 'Update failed' );
		$updated = true;

		// Check for private and/or direct messages.
		if ( is_activity_reply( $activity ) ) {
			$comment_data = Interactions::update_comment( $activity );

			if ( false === $comment_data ) {
				$updated = false;
			} elseif ( ! empty( $comment_data['comment_ID'] ) ) {
				$result = \get_comment( $comment_data['comment_ID'] );
			}
		} elseif ( \get_option( 'activitypub_create_posts', false ) ) {
			$result = Posts::update( $activity, $user_ids );

			if ( \is_wp_error( $result ) && 'activitypub_post_not_found' === $result->get_error_code() ) {
				$updated = false;
			}
		}

		// There is no object to update, try to trigger create instead.
		if ( ! $updated ) {
			return Create::incoming( $activity, $user_ids, $activity_object );
		}

		$success = ( $result && ! \is_wp_error( $result ) );

		/**
		 * Fires after an ActivityPub Update activity has been handled.
		 *
		 * @param array                          $activity The ActivityPub activity data.
		 * @param int[]|null                     $user_ids The local user IDs.
		 * @param bool                           $success  True on success, false otherwise.
		 * @param \WP_Comment|\WP_Post|\WP_Error $result   The updated post, comment, or error.
		 */
		\do_action( 'activitypub_handled_update', $activity, (array) $user_ids, $success, $result );
	}

	/**
	 * Update an Actor.
	 *
	 * @param array      $activity The Activity object.
	 * @param int[]|null $user_ids The user IDs. Always null for Update activities.
	 */
	public static function update_actor( $activity, $user_ids ) {
		// Update cache.
		$actor = get_remote_metadata_by_actor( $activity['actor'], false );

		if ( ! $actor || \is_wp_error( $actor ) || ! isset( $actor['id'] ) ) {
			$state = new \WP_Error( 'activitypub_update_failed', 'Update failed: could not fetch actor data' );
		} else {
			$state = Remote_Actors::upsert( $actor );
		}

		/**
		 * Fires after an ActivityPub Update activity has been handled.
		 *
		 * @param array         $activity The ActivityPub activity data.
		 * @param int[]         $user_ids The local user IDs.
		 * @param int|\WP_Error $state    Actor post ID on success, WP_Error on failure.
		 * @param array         $actor    Remote actor meta data.
		 */
		\do_action( 'activitypub_handled_update', $activity, (array) $user_ids, $state, $actor );
	}

	/**
	 * Handle outgoing "Update" activities from local actors.
	 *
	 * Updates a WordPress post from the ActivityPub object.
	 *
	 * @param array                          $data       The activity data array.
	 * @param int                            $user_id    The user ID.
	 * @param \Activitypub\Activity\Activity $activity   The Activity object.
	 * @param int                            $outbox_id  The outbox post ID.
	 */
	public static function outgoing( $data, $user_id, $activity, $outbox_id ) {
		$object = $data['object'] ?? array();

		if ( ! \is_array( $object ) ) {
			return;
		}

		$type = $object['type'] ?? '';

		// Only handle Note and Article types.
		if ( ! \in_array( $type, array( 'Note', 'Article' ), true ) ) {
			return;
		}

		$object_id = $object['id'] ?? '';

		if ( empty( $object_id ) ) {
			return;
		}

		/*
		 * Find the post by its ActivityPub ID.
		 * First try to find a local post by permalink (for C2S-created posts).
		 */
		$post_id = \url_to_postid( $object_id );
		$post    = $post_id ? \get_post( $post_id ) : null;

		// Fall back to Posts collection for remote posts (ap_post type).
		if ( ! $post instanceof \WP_Post ) {
			$post = Posts::get_by_guid( $object_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		/*
		 * Verify the user owns this post.
		 * The blog actor ($user_id === 0) can update any post since it
		 * represents the site itself.
		 */
		if ( (int) $post->post_author !== $user_id && $user_id > 0 ) {
			return;
		}

		$content = $object['content'] ?? '';
		$name    = $object['name'] ?? '';
		$summary = $object['summary'] ?? '';

		// Use name as title for Articles, or generate from content for Notes.
		$title = $name;
		if ( empty( $title ) && ! empty( $content ) ) {
			$title = \wp_trim_words( \wp_strip_all_tags( $content ), 10, '...' );
		}

		$post_data = array(
			'ID'           => $post->ID,
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $summary,
		);

		/*
		 * Pass $fire_after_hooks = false to prevent wp_after_insert_post from
		 * re-triggering the outbox chain and causing infinite recursion.
		 */
		$post_id = \wp_update_post( $post_data, true, false );

		if ( \is_wp_error( $post_id ) ) {
			return;
		}

		/**
		 * Fires after a post has been updated from an outgoing Update activity.
		 *
		 * @param int   $post_id    The updated post ID.
		 * @param array $data       The activity data.
		 * @param int   $user_id    The user ID.
		 * @param int   $outbox_id  The outbox post ID.
		 */
		\do_action( 'activitypub_outbox_updated_post', $post_id, $data, $user_id, $outbox_id );
	}

	/**
	 * Handle "Update" requests.
	 *
	 * @deprecated unreleased Use Update::incoming() instead.
	 *
	 * @param array                          $activity        The Activity object.
	 * @param int[]                          $user_ids        The user IDs.
	 * @param \Activitypub\Activity\Activity $activity_object The activity object.
	 */
	public static function handle_update( $activity, $user_ids, $activity_object ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Update::incoming()' );

		return self::incoming( $activity, $user_ids, $activity_object );
	}

	/**
	 * Handle outbox "Update" activities.
	 *
	 * @deprecated unreleased Use Update::outgoing() instead.
	 *
	 * @param array                          $data       The activity data array.
	 * @param int                            $user_id    The user ID.
	 * @param \Activitypub\Activity\Activity $activity   The Activity object.
	 * @param int                            $outbox_id  The outbox post ID.
	 */
	public static function handle_outbox_update( $data, $user_id, $activity, $outbox_id ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Update::outgoing()' );

		return self::outgoing( $data, $user_id, $activity, $outbox_id );
	}
}
