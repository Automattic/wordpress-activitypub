<?php
namespace Activitypub\Handler;

use WP_Error;
use WP_REST_Request;
use Activitypub\Http;
use Activitypub\Collection\Followers;

/**
 * Handles Delete requests.
 */
class Delete {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_delete', array( self::class, 'handle_delete' ), 10, 2 );
		// defer signature verification for `Delete` requests.
		\add_filter( 'activitypub_defer_signature_verification', array( self::class, 'defer_signature_verification' ), 10, 2 );
		// side effect
		\add_action( 'activitypub_delete_actor_comments', array( self::class, 'scheduled_delete_comments' ), 10, 1 );
	}

	/**
	 * Handles "Delete" requests.
	 *
	 * @param array $activity The delete activity.
	 * @param int   $user_id  The ID of the user performing the delete activity.
	 */
	public static function handle_delete( $activity, $user_id ) {
		$object_type = isset( $activity['object']['type'] ) ? $activity['object']['type'] : '';

		switch ( $object_type ) {
			// Actor Types
			// @see https://www.w3.org/TR/activitystreams-vocabulary/#actor-types
			case 'Person':
			case 'Group':
			case 'Organization':
			case 'Service':
			case 'Application':
				self::maybe_delete_follower( $user_id, $activity );
				break;
			// Tombstone Type
			// @see: https://www.w3.org/TR/activitystreams-vocabulary/#dfn-tombstone
			case 'Tombstone':
				// Handle tombstone.
				break;
			// Object and Link Types
			// @see https://www.w3.org/TR/activitystreams-vocabulary/#object-types
			case 'Note':
			case 'Article':
			case 'Image':
			case 'Audio':
			case 'Video':
			case 'Event':
			case 'Document':
				self::maybe_delete_reaction( $activity );
				break;
			// Minimal Activity
			// @see https://www.w3.org/TR/activitystreams-core/#example-1
			default:
				// ignore non Minimal Activities.
				if ( ! is_string( $activity['object'] ) ) {
					return;
				}

				// check if Object is an Actor.
				if ( $activity['actor'] === $activity['object'] ) {
					self::maybe_delete_follower( $activity );
					self::maybe_delete_commenter( $activity );
				} else { // assume a reaction otherwise.
					self::maybe_delete_reaction( $activity );
				}
				// maybe handle Delete Activity for other Object Types.
				break;
		}
	}

	/**
	 * Delete a Follower if Actor-URL is a Tombstone.
	 *
	 * @param array $activity The delete activity.
	 */
	public static function maybe_delete_follower( $activity ) {
		$follower = Followers::get_follower_by_actor( $activity['actor'] );

		// verify if Actor is deleted.
		if ( $follower && Http::is_tombstone( $activity['actor'] ) ) {
			$follower->delete();
		}
	}

	/**
	 * Delete Comments if Actor-URL is a Tombstone.
	 *
	 * @param array $activity The delete activity.
	 */
	public static function maybe_delete_commenter( $activity ) {
		$comments = self::get_comments_by_actor( $activity['actor'] );

		// verify if Actor is deleted.
		if ( $comments && Http::is_tombstone( $activity['actor'] ) ) {
			\wp_schedule_single_event(
				\time(),
				'activitypub_delete_actor_comments',
				array( $comments )
			);
		}
	}

	public static function get_comments_by_actor( $activity ) {
		$args = array(
			'user_id' => 0,
			'meta_query' => array(
				array(
					'key'   => 'protocol',
					'value' => 'activitypub',
					'compare' => '=',
				),
			),
			'author_url' => $activity['actor'],
		);
		$comment_query = new WP_Comment_Query( $args );
		return $comment_query->comments;
	}

	/**
	 * Delete comments.
	 * @param array comments  The comments to delete.
	 */
	public static function scheduled_delete_comments( $comments ) {
		if ( is_array( $comments ) ) {
			foreach ( $comments as $comment ) {
				wp_delete_comment( $comment->comment_ID );
			}
		}
	}

	/**
	 * Delete a Reaction if URL is a Tombstone.
	 *
	 * @param array $activity The delete activity.
	 *
	 * @return void
	 */
	public static function maybe_delete_reaction( $activity ) {
		if ( is_array( $activity['object'] ) ) {
			$id = $activity['object']['id'];
		} else {
			$id = $activity['object'];
		}

		$comments = Interactions::get_interaction_by_id( $id );

		if ( $comments && Http::is_tombstone( $id ) ) {
			foreach ( $comments as $comment ) {
				wp_delete_comment( $comment->comment_ID, true );
			}
		}
	}

	/**
	 * Defer signature verification for `Delete` requests.
	 *
	 * @param bool            $defer   Whether to defer signature verification.
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return bool Whether to defer signature verification.
	 */
	public static function defer_signature_verification( $defer, $request ) {
		$json = $request->get_json_params();

		if ( 'Delete' === $json['type'] ) {
			return true;
		}

		return false;
	}
}
