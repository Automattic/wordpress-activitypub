<?php
/**
 * Outbox Create handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Interactions;
use Activitypub\Collection\Posts;

use function Activitypub\is_activity_public;
use function Activitypub\is_activity_reply;
use function Activitypub\is_quote_activity;
use function Activitypub\object_to_uri;
use function Activitypub\site_supports_blocks;
use function Activitypub\url_to_commentid;

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
	 * @return int|\WP_Error|false The outbox ID on success, WP_Error on failure, false if not handled.
	 */
	public static function handle_create( $activity, $user_id = null, $visibility = null ) {
		// Skip private/direct activities.
		if ( ! is_activity_public( $activity ) ) {
			return false;
		}

		$object = $activity['object'] ?? array();

		if ( ! \is_array( $object ) ) {
			return new \WP_Error( 'invalid_object', 'Invalid object in activity.' );
		}

		$object_type = $object['type'] ?? '';

		// Only handle Note and Article types for now.
		if ( ! \in_array( $object_type, array( 'Note', 'Article' ), true ) ) {
			return false;
		}

		if ( is_activity_reply( $activity ) ) {
			$in_reply_to = object_to_uri( $activity['object']['inReplyTo'] );

			// Replies to local posts and comments become comments; everything else becomes a post.
			if ( \url_to_postid( $in_reply_to ) || url_to_commentid( $in_reply_to ) ) {
				return self::create_comment( $activity, $user_id );
			}

			// Without block support the Reply block is never read, so the reply would federate without its target.
			if ( ! site_supports_blocks() ) {
				return new \WP_Error(
					'activitypub_reply_requires_blocks',
					\__( 'Replies to posts on other servers need the block editor.', 'activitypub' ),
					array( 'status' => 400 )
				);
			}
		} elseif ( is_quote_activity( $activity ) ) {
			// TODO: Handle quotes differently.
			return false;
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
		$post = Posts::create( $activity, $user_id, $visibility );

		if ( \is_wp_error( $post ) ) {
			return $post;
		}

		/**
		 * Fires after a post has been created from an outgoing Create activity.
		 *
		 * @param int    $post_id    The created post ID.
		 * @param array  $activity   The activity data.
		 * @param int    $user_id    The user ID.
		 * @param string $visibility The content visibility.
		 */
		\do_action( 'activitypub_outbox_created_post', $post->ID, $activity, $user_id, $visibility );

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
