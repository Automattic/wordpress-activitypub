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
	 * Batch size.
	 *
	 * @var int
	 */
	public static $batch_size = ACTIVITYPUB_OUTBOX_PROCESSING_BATCH_SIZE;

	/**
	 * Callback for the async batch processing.
	 *
	 * @var array
	 */
	public static $callback = array( self::class, 'send_to_followers' );

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_process_outbox', array( self::class, 'process_outbox' ) );

		// Default filters to add Inboxes to sent to.
		\add_filter( 'activitypub_interactees_inboxes', array( self::class, 'add_inboxes_by_mentioned_actors' ), 10, 3 );
		\add_filter( 'activitypub_interactees_inboxes', array( self::class, 'add_inboxes_of_replied_urls' ), 10, 3 );

		// Fallback for `activitypub_send_to_inboxes` filter.
		\add_filter(
			'activitypub_interactees_inboxes',
			function ( $inboxes, $actor_id, $activity ) {
				/**
				 * Filters the list of interactees inboxes to send the Activity to.
				 *
				 * @param array    $inboxes  The list of inboxes to send to.
				 * @param int      $actor_id The actor ID.
				 * @param Activity $activity The ActivityPub Activity.
				 *
				 * @deprecated 5.2.0 Use `activitypub_interactees_inboxes` instead.
				 */
				return \apply_filters_deprecated( 'activitypub_send_to_inboxes', array( $inboxes, $actor_id, $activity ), '5.2.0', 'activitypub_interactees_inboxes' );
			},
			10,
			3
		);
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

		$actor = self::get_actor( $outbox_item );
		if ( \is_wp_error( $actor ) ) {
			// If the actor is not found, publish the post and don't try again.
			\wp_publish_post( $outbox_item );
			return;
		}

		$activity = self::get_activity( $outbox_item );

		// Send to mentioned and replied-to users. Everyone other than followers.
		self::send_to_interactees( $activity, $actor->get__id(), $outbox_item );

		if ( self::should_send_to_followers( $activity, $actor, $outbox_item ) ) {
			\wp_schedule_single_event(
				\time(),
				'activitypub_async_batch',
				array(
					self::$callback,
					$outbox_item->ID,
					self::$batch_size,
					\get_post_meta( $outbox_item->ID, '_activitypub_outbox_offset', true ) ?: 0, // phpcs:ignore
				)
			);
		} else {
			// No followers to process for this update. We're done.
			\wp_publish_post( $outbox_item );
			\delete_post_meta( $outbox_item->ID, '_activitypub_outbox_offset' );
		}
	}

	/**
	 * Asynchronously runs batch processing routines.
	 *
	 * @param int $outbox_item_id The Outbox item ID.
	 * @param int $batch_size     Optional. The batch size. Default 50.
	 * @param int $offset         Optional. The offset. Default 0.
	 *
	 * @return array|void The next batch of followers to process, or void if done.
	 */
	public static function send_to_followers( $outbox_item_id, $batch_size = 50, $offset = 0 ) {
		$activity = self::get_activity( $outbox_item_id );
		$actor    = self::get_actor( \get_post( $outbox_item_id ) );
		$json     = $activity->to_json();
		$inboxes  = Followers::get_inboxes_for_activity( $json, $actor->get__id(), $batch_size, $offset );

		foreach ( $inboxes as $inbox ) {
			$result = safe_remote_post( $inbox, $json, $actor->get__id() );

			/**
			 * Fires after an Activity has been sent to an inbox.
			 *
			 * @param array  $result         The result of the remote post request.
			 * @param string $inbox          The inbox URL.
			 * @param string $json           The ActivityPub Activity JSON.
			 * @param int    $actor_id       The actor ID.
			 * @param int    $outbox_item_id The Outbox item ID.
			 */
			\do_action( 'activitypub_sent_to_inbox', $result, $inbox, $json, $actor->get__id(), $outbox_item_id );
		}

		if ( is_countable( $inboxes ) && count( $inboxes ) < self::$batch_size ) {
			\delete_post_meta( $outbox_item_id, '_activitypub_outbox_offset' );

			/**
			 * Fires when the followers are complete.
			 *
			 * @param array  $inboxes        The inboxes.
			 * @param string $json           The ActivityPub Activity JSON
			 * @param int    $actor_id       The actor ID.
			 * @param int    $outbox_item_id The Outbox item ID.
			 * @param int    $batch_size     The batch size.
			 * @param int    $offset         The offset.
			 */
			\do_action( 'activitypub_outbox_processing_complete', $inboxes, $json, $actor->get__id(), $outbox_item_id, $batch_size, $offset );

			// No more followers to process for this update.
			\wp_publish_post( $outbox_item_id );
		} else {
			\update_post_meta( $outbox_item_id, '_activitypub_outbox_offset', $offset + $batch_size );

			/**
			 * Fires when the batch of followers is complete.
			 *
			 * @param array  $inboxes        The inboxes.
			 * @param string $json           The ActivityPub Activity JSON
			 * @param int    $actor_id       The actor ID.
			 * @param int    $outbox_item_id The Outbox item ID.
			 * @param int    $batch_size     The batch size.
			 * @param int    $offset         The offset.
			 */
			\do_action( 'activitypub_outbox_processing_batch_complete', $inboxes, $json, $actor->get__id(), $outbox_item_id, $batch_size, $offset );

			return array( $outbox_item_id, $batch_size, $offset + $batch_size );
		}
	}

	/**
	 * Send an Activity to all followers and mentioned users.
	 *
	 * @param Activity $activity  The ActivityPub Activity.
	 * @param int      $actor_id  The actor ID.
	 * @param \WP_Post $outbox_item The WordPress object.
	 */
	private static function send_to_interactees( $activity, $actor_id, $outbox_item = null ) {
		/**
		 * Filters the list of inboxes to send the Activity to.
		 *
		 * @param array    $inboxes  The list of inboxes to send to.
		 * @param int      $actor_id The actor ID.
		 * @param Activity $activity The ActivityPub Activity.
		 */
		$inboxes = apply_filters( 'activitypub_interactees_inboxes', array(), $actor_id, $activity );
		$inboxes = array_unique( $inboxes );

		$json = $activity->to_json();

		foreach ( $inboxes as $inbox ) {
			$result = safe_remote_post( $inbox, $json, $actor_id );

			/**
			 * Fires after an Activity has been sent to an inbox.
			 *
			 * @param array  $result         The result of the remote post request.
			 * @param string $inbox          The inbox URL.
			 * @param string $json           The ActivityPub Activity JSON.
			 * @param int    $actor_id       The actor ID.
			 * @param int    $outbox_item_id The Outbox item ID.
			 */
			\do_action( 'activitypub_sent_to_inbox', $result, $inbox, $json, $actor_id, $outbox_item->ID );
		}
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
	 * @param array    $inboxes  The list of Inboxes.
	 * @param int      $actor_id The WordPress Actor-ID.
	 * @param Activity $activity The ActivityPub Activity.
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
	 * @deprecated 5.2.0 Use {@see Followers::maybe_add_inboxes_of_blog_user} instead.
	 *
	 * @param array    $inboxes  The list of Inboxes.
	 * @param int      $actor_id The WordPress Actor-ID.
	 * @param Activity $activity The ActivityPub Activity.
	 *
	 * @return array The filtered Inboxes.
	 */
	public static function maybe_add_inboxes_of_blog_user( $inboxes, $actor_id, $activity ) { // phpcs:ignore
		_deprecated_function( __METHOD__, '5.2.0', 'Followers::maybe_add_inboxes_of_blog_user' );

		return $inboxes;
	}

	/**
	 * Check if passed Activity is public.
	 *
	 * @param Activity                                        $activity    The Activity object.
	 * @param \Activitypub\Model\User|\Activitypub\Model\Blog $actor       The Actor object.
	 * @param \WP_Post                                        $outbox_item The Outbox item.
	 *
	 * @return boolean True if public, false if not.
	 */
	protected static function should_send_to_followers( $activity, $actor, $outbox_item ) {
		// Check if follower endpoint is set.
		$cc = $activity->get_cc() ?? array();
		$to = $activity->get_to() ?? array();

		$audience = array_merge( $cc, $to );

		$send = (
			// Check if activity is public.
			in_array( 'https://www.w3.org/ns/activitystreams#Public', $audience, true ) ||
			// ...or check if follower endpoint is set.
			in_array( $actor->get_followers(), $audience, true )
		);

		/**
		 * Filters whether to send an Activity to followers.
		 *
		 * @param bool     $send_activity_to_followers Whether to send the Activity to followers.
		 * @param Activity $activity                   The ActivityPub Activity.
		 * @param int      $actor_id                   The actor ID.
		 * @param \WP_Post $outbox_item                The WordPress object.
		 */
		return apply_filters( 'activitypub_send_activity_to_followers', $send, $activity, $actor->get__id(), $outbox_item );
	}

	/**
	 * Get the Activity object from the Outbox item.
	 *
	 * @param int|\WP_Post $outbox_item The Outbox post or post ID.
	 * @return Activity|\WP_Error The Activity object or WP_Error.
	 */
	private static function get_activity( $outbox_item ) {
		$outbox_item = get_post( $outbox_item );
		$actor       = self::get_actor( $outbox_item );
		if ( is_wp_error( $actor ) ) {
			return $actor;
		}

		$type     = \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true );
		$activity = new Activity();
		$activity->set_type( $type );
		$activity->set_id( $outbox_item->guid );
		// Pre-fill the Activity with data (for example cc and to).
		$activity->set_object( \json_decode( $outbox_item->post_content, true ) );
		$activity->set_actor( $actor->get_id() );

		// Use simple Object (only ID-URI) for Like and Announce.
		if ( in_array( $type, array( 'Like', 'Delete' ), true ) ) {
			$activity->set_object( $activity->get_object()->get_id() );
		}

		return $activity;
	}

	/**
	 * Get the Actor object from the Outbox item.
	 *
	 * @param \WP_Post $outbox_item The Outbox post.
	 *
	 * @return \Activitypub\Model\User|\Activitypub\Model\Blog|\WP_Error The Actor object or WP_Error.
	 */
	private static function get_actor( $outbox_item ) {
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

		return Actors::get_by_id( $actor_id );
	}
}
