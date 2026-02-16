<?php
/**
 * Outbox Create handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Interactions;

use function Activitypub\get_activity_visibility;
use function Activitypub\get_content_visibility;
use function Activitypub\is_activity_reply;
use function Activitypub\is_quote_activity;

/**
 * Handle outgoing Create activities (C2S).
 */
class Create {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_create', array( self::class, 'handle_create' ), 10, 3 );
	}

	/**
	 * Handle outgoing "Create" activities from local actors.
	 *
	 * Creates WordPress content and adds to outbox for federation.
	 *
	 * @param array       $activity   The activity data.
	 * @param int         $user_id    The local user ID.
	 * @param string|null $visibility Content visibility.
	 *
	 * @return int|\WP_Error|null The outbox ID on success, WP_Error on failure, null if not handled.
	 */
	public static function handle_create( $activity, $user_id = null, $visibility = null ) {
		// Check for private and/or direct messages.
		if ( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE === get_activity_visibility( $activity ) ) {
			return false;
		}

		$object = $activity['object'] ?? array();

		if ( ! \is_array( $object ) ) {
			return new \WP_Error( 'invalid_object', 'Invalid object in activity.' );
		}

		$object_type = $object['type'] ?? '';

		// Only handle Note and Article types for now.
		if ( ! \in_array( $object_type, array( 'Note', 'Article' ), true ) ) {
			return null;
		}

		if ( is_activity_reply( $activity ) ) {
			$result = self::create_comment( $activity, $user_id );

			// If the reply target is not found locally (e.g. remote post),
			// fall back to creating a post so it gets federated with inReplyTo.
			if ( false !== $result ) {
				return $result;
			}
		}

		// TODO: Handle quotes differently.
		if ( is_quote_activity( $activity ) ) {
			return null;
		}

		return self::create_post( $activity, $user_id, $visibility );
	}

	/**
	 * Handle outgoing post from local actor.
	 *
	 * Creates a WordPress post. The scheduler will add it to the outbox.
	 *
	 * @param array       $activity   The activity data.
	 * @param int         $user_id    The local user ID.
	 * @param string|null $visibility Content visibility.
	 *
	 * @return \WP_Post|\WP_Error The created post on success, WP_Error on failure.
	 */
	private static function create_post( $activity, $user_id, $visibility ) {
		$object = $activity['object'] ?? array();

		$object_type = $object['type'] ?? '';
		$content     = $object['content'] ?? '';
		$name        = $object['name'] ?? '';
		$summary     = $object['summary'] ?? '';

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
			'post_author'  => $user_id > 0 ? $user_id : 0,
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $summary,
			'post_status'  => ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE === $visibility ? 'private' : 'publish',
			'post_type'    => 'post',
			'meta_input'   => array(
				'activitypub_content_visibility' => $visibility,
			),
		);

		$post_id = \wp_insert_post( $post_data, true );

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set post format to 'status' for Notes so the transformer maps it back correctly.
		if ( 'Note' === $object_type ) {
			\set_post_format( $post_id, 'status' );
		}

		$post = \get_post( $post_id );

		/**
		 * Fires after a post has been created from an outgoing Create activity.
		 *
		 * @param int    $post_id    The created post ID.
		 * @param array  $activity   The activity data.
		 * @param int    $user_id    The user ID.
		 * @param string $visibility The content visibility.
		 */
		\do_action( 'activitypub_outbox_created_post', $post_id, $activity, $user_id, $visibility );

		return $post;
	}

	/**
	 * Handle outgoing reply from local actor.
	 *
	 * Creates a WordPress comment on the local post. The comment scheduler
	 * will add it to the outbox and federate it.
	 *
	 * @param array $activity The activity data.
	 * @param int   $user_id  The local user ID.
	 *
	 * @return \WP_Comment|false Comment on success, false if not a local reply.
	 */
	private static function create_comment( $activity, $user_id ) {
		$result = Interactions::add_comment( $activity, $user_id );

		if ( ! $result ) {
			return false;
		}

		return \get_comment( $result );
	}
}
