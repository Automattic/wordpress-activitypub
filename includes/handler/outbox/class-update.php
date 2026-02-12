<?php
/**
 * Outbox Update handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Posts;

use function Activitypub\get_activity_visibility;
use function Activitypub\get_content_visibility;

/**
 * Handle outgoing Update activities (C2S).
 */
class Update {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_update', array( self::class, 'handle_update' ), 10, 3 );
	}

	/**
	 * Handle outgoing "Update" activities from local actors.
	 *
	 * Updates a WordPress post from the ActivityPub object. The post scheduler
	 * will add it to the outbox and federate it.
	 *
	 * @param array       $activity   The activity data.
	 * @param int         $user_id    The local user ID.
	 * @param string|null $visibility Content visibility.
	 *
	 * @return \WP_Post|null The updated post on success, null if not handled.
	 */
	public static function handle_update( $activity, $user_id = null, $visibility = null ) {
		// Check for private and/or direct messages.
		if ( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE === get_activity_visibility( $activity ) ) {
			return false;
		}

		$object = $activity['object'] ?? array();

		if ( ! \is_array( $object ) ) {
			return null;
		}

		$type = $object['type'] ?? '';

		// Only handle Note and Article types.
		if ( ! \in_array( $type, array( 'Note', 'Article' ), true ) ) {
			return null;
		}

		$object_id = $object['id'] ?? '';

		if ( empty( $object_id ) ) {
			return null;
		}

		/*
		 * Find the post by its ActivityPub ID.
		 * First try to find a local post by permalink.
		 */
		$post_id = \url_to_postid( $object_id );
		$post    = $post_id ? \get_post( $post_id ) : null;

		// Fall back to Posts collection for remote posts (ap_post type).
		if ( ! $post instanceof \WP_Post ) {
			$post = Posts::get_by_guid( $object_id );
		}

		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		/*
		 * Verify the user owns this post.
		 * The blog actor ($user_id === 0) can update any post since it
		 * represents the site itself.
		 */
		if ( (int) $post->post_author !== $user_id && $user_id > 0 ) {
			return null;
		}

		$content = $object['content'] ?? '';
		$name    = $object['name'] ?? '';
		$summary = $object['summary'] ?? '';

		// Use name as title for Articles, or generate from content for Notes.
		$title = $name;
		if ( empty( $title ) && ! empty( $content ) ) {
			$title = \wp_trim_words( \wp_strip_all_tags( $content ), 10, '...' );
		}

		// Determine visibility if not provided.
		if ( null === $visibility ) {
			$visibility = get_content_visibility( $activity );
		}

		$post_data = array(
			'ID'           => $post->ID,
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $summary,
			'meta_input'   => array(
				'activitypub_content_visibility' => $visibility,
			),
		);

		$post_id = \wp_update_post( $post_data, true );

		if ( \is_wp_error( $post_id ) ) {
			return null;
		}

		$post = \get_post( $post_id );

		/**
		 * Fires after a post has been updated from an outgoing Update activity.
		 *
		 * @param int    $post_id    The updated post ID.
		 * @param array  $activity   The activity data.
		 * @param int    $user_id    The user ID.
		 */
		\do_action( 'activitypub_outbox_updated_post', $post_id, $activity, $user_id );

		return $post;
	}
}
