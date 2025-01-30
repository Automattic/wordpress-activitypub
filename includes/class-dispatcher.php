<?php
/**
 * ActivityPub Dispatcher Class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;

/**
 * ActivityPub Dispatcher Class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/
 */
class Dispatcher {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_process_outbox', array( self::class, 'process_outbox' ) );

		// Default filters to add Inboxes to sent to.
		\add_filter( 'activitypub_send_to_inboxes', array( self::class, 'add_inboxes_of_follower' ), 10, 3 );
		\add_filter( 'activitypub_send_to_inboxes', array( self::class, 'add_inboxes_by_mentioned_actors' ), 10, 3 );
		\add_filter( 'activitypub_send_to_inboxes', array( self::class, 'add_inboxes_of_replied_urls' ), 10, 3 );
		\add_filter( 'activitypub_send_to_inboxes', array( self::class, 'maybe_add_inboxes_of_blog_user' ), 10, 3 );
	}

	/**
	 * Process the outbox.
	 *
	 * @param int $id The outbox ID.
	 */
	public static function process_outbox( $id ) {
		$outbox_item = \get_post( $id );

		// If the activity is not a post, return.
		if ( ! $outbox_item ) {
			return;
		}

		$actor_type = \get_post_meta( $outbox_item->ID, '_activitypub_activity_actor', true );

		switch ( $actor_type ) {
			case 'blog':
				$actor_id = Actors::BLOG_USER_ID;
				break;
			case 'application':
				$actor_id = Actors::APPLICATION_USER_ID;
				break;
			case 'user':
			default:
				$actor_id = $outbox_item->post_author;
				break;
		}

		$type     = \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true );
		$activity = new Activity();
		$activity->set_type( $type );
		$activity->set_id( $outbox_item->guid );
		// Pre-fill the Activity with data (for example cc and to).
		$activity->set_object( \json_decode( $outbox_item->post_content, true ) );
		$activity->set_actor( Actors::get_by_id( $outbox_item->post_author )->get_id() );

		// Use simple Object (only ID-URI) for Like and Announce.
		if ( in_array( $type, array( 'Like', 'Delete' ), true ) ) {
			$activity->set_object( $activity->get_object()->get_id() );
		}

		self::send_activity_to_followers( $activity, $actor_id, $outbox_item );
	}

	/**
	 * Send an Activity to all followers and mentioned users.
	 *
	 * @param Activity $activity  The ActivityPub Activity.
	 * @param int      $actor_id  The actor ID.
	 * @param \WP_Post $outbox_item The WordPress object.
	 */
	private static function send_activity_to_followers( $activity, $actor_id, $outbox_item = null ) {
		/**
		 * Filters whether to send an Activity to followers.
		 *
		 * @param bool     $send_activity_to_followers Whether to send the Activity to followers.
		 * @param Activity $activity                   The ActivityPub Activity.
		 * @param int      $actor_id                   The actor ID.
		 * @param \WP_Post $outbox_item                The WordPress object.
		 */
		if ( ! apply_filters( 'activitypub_send_activity_to_followers', true, $activity, $actor_id, $outbox_item ) ) {
			return;
		}

		/**
		 * Filters the list of inboxes to send the Activity to.
		 *
		 * @param array    $inboxes  The list of inboxes to send to.
		 * @param int      $actor_id The actor ID.
		 * @param Activity $activity The ActivityPub Activity.
		 */
		$inboxes = apply_filters( 'activitypub_send_to_inboxes', array(), $actor_id, $activity );
		$inboxes = array_unique( $inboxes );

		$json = $activity->to_json();

		$results = array();
		foreach ( $inboxes as $inbox ) {
			$results[ $inbox ] = safe_remote_post( $inbox, $json, $actor_id );
		}

		/**
		 * Fires after an Activity has been sent to all followers and mentioned users.
		 *
		 * @param array    $results     The results of the remote posts.
		 * @param Activity $activity    The ActivityPub Activity.
		 * @param \WP_Post $outbox_item The WordPress object.
		 */
		do_action( 'activitypub_sent_to_followers', $results, $activity, $outbox_item );

		\wp_publish_post( $outbox_item );
	}

	/**
	 * Default filter to add Inboxes of Followers.
	 *
	 * @param array    $inboxes  The list of Inboxes.
	 * @param int      $actor_id The WordPress Actor-ID.
	 * @param Activity $activity The ActivityPub Activity.
	 *
	 * @return array The filtered Inboxes
	 */
	public static function add_inboxes_of_follower( $inboxes, $actor_id, $activity ) {
		if ( ! self::should_send_to_followers( $activity, $actor_id ) ) {
			return $inboxes;
		}

		$follower_inboxes = Followers::get_inboxes( $actor_id );

		return array_merge( $inboxes, $follower_inboxes );
	}

	/**
	 * Default filter to add Inboxes of Mentioned Actors
	 *
	 * @param array    $inboxes  The list of Inboxes.
	 * @param int      $actor_id The WordPress Actor-ID.
	 * @param Activity $activity The ActivityPub Activity.
	 *
	 * @return array The filtered Inboxes.
	 */
	public static function add_inboxes_by_mentioned_actors( $inboxes, $actor_id, $activity ) {
		$cc = $activity->get_cc() ?? array();
		$to = $activity->get_to() ?? array();

		$audience = array_merge( $cc, $to );

		// Remove "public placeholder" and "same domain" from the audience.
		$audience = array_filter(
			$audience,
			function ( $actor ) {
				return 'https://www.w3.org/ns/activitystreams#Public' !== $actor && ! is_same_domain( $actor );
			}
		);

		if ( $audience ) {
			$mentioned_inboxes = Mention::get_inboxes( $audience );

			return array_merge( $inboxes, $mentioned_inboxes );
		}

		return $inboxes;
	}

	/**
	 * Default filter to add Inboxes of Posts that are set as `in-reply-to`
	 *
	 * @param array $inboxes  The list of Inboxes.
	 * @param int   $actor_id The WordPress Actor-ID.
	 * @param array $activity The ActivityPub Activity.
	 *
	 * @return array The filtered Inboxes
	 */
	public static function add_inboxes_of_replied_urls( $inboxes, $actor_id, $activity ) {
		$in_reply_to = $activity->get_in_reply_to();

		if ( ! $in_reply_to ) {
			return $inboxes;
		}

		if ( ! is_array( $in_reply_to ) ) {
			$in_reply_to = array( $in_reply_to );
		}

		foreach ( $in_reply_to as $url ) {
			$object = Http::get_remote_object( $url );

			if (
				! $object ||
				\is_wp_error( $object ) ||
				empty( $object['attributedTo'] )
			) {
				continue;
			}

			$actor = object_to_uri( $object['attributedTo'] );
			$actor = Http::get_remote_object( $actor );

			if ( ! $actor || \is_wp_error( $actor ) ) {
				continue;
			}

			if ( ! empty( $actor['endpoints']['sharedInbox'] ) ) {
				$inboxes[] = $actor['endpoints']['sharedInbox'];
			} elseif ( ! empty( $actor['inbox'] ) ) {
				$inboxes[] = $actor['inbox'];
			}
		}

		return $inboxes;
	}

	/**
	 * Adds Blog Actor inboxes to Updates so the Blog User's followers are notified of edits.
	 *
	 * @param array    $inboxes  The list of Inboxes.
	 * @param int      $actor_id The WordPress Actor-ID.
	 * @param Activity $activity The ActivityPub Activity.
	 *
	 * @return array The filtered Inboxes
	 */
	public static function maybe_add_inboxes_of_blog_user( $inboxes, $actor_id, $activity ) {
		if ( ! self::should_send_to_followers( $activity, $actor_id ) ) {
			return $inboxes;
		}

		// Only if we're in both Blog and User modes.
		if ( ACTIVITYPUB_ACTOR_AND_BLOG_MODE !== \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) ) {
			return $inboxes;
		}
		// Only if this isn't the Blog Actor.
		if ( Actors::BLOG_USER_ID === $actor_id ) {
			return $inboxes;
		}
		// Only if this is an Update or Delete. Create handles its own Announce in dual user mode.
		if ( ! in_array( $activity->get_type(), array( 'Update', 'Delete' ), true ) ) {
			return $inboxes;
		}

		$blog_inboxes = Followers::get_inboxes( Actors::BLOG_USER_ID );
		// array_unique is done in `send_activity_to_followers()`, no need here.
		return array_merge( $inboxes, $blog_inboxes );
	}

	/**
	 * Check if passed Activity is public.
	 *
	 * @param Activity $activity The Activity object.
	 * @param int      $actor_id The Actor-ID.
	 *
	 * @return boolean True if public, false if not.
	 */
	protected static function should_send_to_followers( $activity, $actor_id ) {
		// Check if follower endpoint is set.
		$actor = Actors::get_by_id( $actor_id );

		if ( ! $actor || is_wp_error( $actor ) ) {
			return false;
		}

		// Check if follower endpoint is set.
		$cc = $activity->get_cc() ?? array();
		$to = $activity->get_to() ?? array();

		$audience = array_merge( $cc, $to );

		if (
			// Check if activity is public.
			in_array( 'https://www.w3.org/ns/activitystreams#Public', $audience, true ) ||
			// ...or check if follower endpoint is set.
			in_array( $actor->get_followers(), $audience, true )
		) {
			return true;
		}

		return false;
	}
}
