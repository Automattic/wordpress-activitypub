<?php
/**
 * ActivityPub Dispatcher Class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Outbox;

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
	 * @deprecated unreleased {@see Activitypub\Dispatcher::get_batch_size()}
	 *
	 * @var int
	 */
	public static $batch_size = ACTIVITYPUB_OUTBOX_PROCESSING_BATCH_SIZE;

	/**
	 * Get the batch size for processing outbox items.
	 *
	 * @return int The batch size.
	 */
	public static function get_batch_size() {
		/**
		 * Filters the batch size for processing outbox items.
		 *
		 * @param int $batch_size The batch size. Default ACTIVITYPUB_OUTBOX_PROCESSING_BATCH_SIZE.
		 */
		return apply_filters( 'activitypub_dispatcher_batch_size', ACTIVITYPUB_OUTBOX_PROCESSING_BATCH_SIZE );
	}

	/**
	 * Get the maximum number of retry attempts.
	 *
	 * @return int The maximum number of retry attempts.
	 */
	public static function get_retry_max_attempts() {
		/**
		 * Filters the maximum number of retry attempts.
		 *
		 * @param int $retry_max_attempts The maximum number of retry attempts. Default ACTIVITYPUB_OUTBOX_RETRY_MAX_ATTEMPTS.
		 */
		return apply_filters( 'activitypub_dispatcher_retry_max_attempts', 3 );
	}

	/**
	 * Get the retry delay unit (in seconds).
	 *
	 * Used to calculate exponential backoff: time() + (attempt * attempt * retry_delay_unit).
	 *
	 * @return int The retry delay unit in seconds.
	 */
	public static function get_retry_delay() {
		/**
		 * Filters the retry delay unit (in seconds).
		 *
		 * Used to calculate exponential backoff: time() + (attempt * attempt * retry_delay_unit).
		 *
		 * @param int $retry_delay_unit The retry delay unit in seconds. Default ACTIVITYPUB_OUTBOX_RETRY_DELAY_UNIT.
		 */
		return apply_filters( 'activitypub_dispatcher_retry_delay', HOUR_IN_SECONDS );
	}

	/**
	 * Get the error codes that qualify for a retry.
	 *
	 * @see https://github.com/tfredrich/RestApiTutorial.com/blob/fd08b0f67f07450521d143b123cd6e1846cb2e3b/content/advanced/responses/retries.md
	 *
	 * @return int[] The error codes.
	 */
	public static function get_retry_error_codes() {
		/**
		 * Filters the error codes that qualify for a retry.
		 *
		 * @param int[] $retry_error_codes The error codes. Default array( 408, 429, 500, 502, 503, 504 ).
		 */
		return apply_filters( 'activitypub_dispatcher_retry_error_codes', array( 408, 429, 500, 502, 503, 504 ) );
	}

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_process_outbox', array( self::class, 'process_outbox' ) );

		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'send_immediate_accept' ), 10, 2 );

		// Default filters to add Inboxes to sent to.
		\add_filter( 'activitypub_additional_inboxes', array( self::class, 'add_inboxes_by_mentioned_actors' ), 10, 3 );
		\add_filter( 'activitypub_additional_inboxes', array( self::class, 'add_inboxes_of_replied_urls' ), 10, 3 );
		\add_filter( 'activitypub_additional_inboxes', array( self::class, 'add_inboxes_of_relays' ), 10, 3 );

		Scheduler::register_async_batch_callback( 'activitypub_send_activity', array( self::class, 'send_to_followers' ) );
		Scheduler::register_async_batch_callback( 'activitypub_retry_activity', array( self::class, 'retry_send_to_followers' ) );
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

		$type  = \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true );
		$actor = Outbox::get_actor( $outbox_item );
		if ( \is_wp_error( $actor ) && 'Delete' !== $type ) {
			// If the actor is not found, publish the post and don't try again.
			\wp_publish_post( $outbox_item );
			return;
		}

		$activity = Outbox::get_activity( $outbox_item );

		// Send to mentioned and replied-to users. Everyone other than followers.
		self::send_to_additional_inboxes( $activity, $outbox_item->post_author, $outbox_item );

		if ( self::should_send_to_followers( $activity, $actor, $outbox_item ) ) {
			\do_action(
				'activitypub_send_activity',
				$outbox_item->ID,
				self::get_batch_size(),
				\get_post_meta( $outbox_item->ID, '_activitypub_outbox_offset', true ) ?: 0 // phpcs:ignore
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
	 * @param int      $outbox_item_id The Outbox item ID.
	 * @param int|null $batch_size     Optional. The batch size. Default null (uses filtered batch size).
	 * @param int      $offset         Optional. The offset. Default 0.
	 *
	 * @return array|void The next batch of followers to process, or void if done.
	 */
	public static function send_to_followers( $outbox_item_id, $batch_size = ACTIVITYPUB_OUTBOX_PROCESSING_BATCH_SIZE, $offset = 0 ) {
		if ( null === $batch_size ) {
			$batch_size = self::get_batch_size();
		}

		$outbox_item = \get_post( $outbox_item_id );
		$json        = Outbox::get_activity( $outbox_item_id )->to_json();
		$inboxes     = Followers::get_inboxes_for_activity( $json, $outbox_item->post_author, $batch_size, $offset );
		$retries     = self::send_to_inboxes( $inboxes, $outbox_item_id );

		// Retry failed inboxes.
		if ( ! empty( $retries ) ) {
			self::schedule_retry( $retries, $outbox_item_id );
		}

		if ( is_countable( $inboxes ) && count( $inboxes ) < $batch_size ) {
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
			\do_action( 'activitypub_outbox_processing_complete', $inboxes, $json, $outbox_item->post_author, $outbox_item_id, $batch_size, $offset );

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
			\do_action( 'activitypub_outbox_processing_batch_complete', $inboxes, $json, $outbox_item->post_author, $outbox_item_id, $batch_size, $offset );

			return array( $outbox_item_id, $batch_size, $offset + $batch_size );
		}
	}

	/**
	 * Retry sending to followers.
	 *
	 * @param string $transient_key  The key to retrieve retry inboxes.
	 * @param int    $outbox_item_id The Outbox item ID.
	 * @param int    $attempt        The attempt number.
	 */
	public static function retry_send_to_followers( $transient_key, $outbox_item_id, $attempt = 1 ) {
		$inboxes = \get_transient( $transient_key );
		if ( false === $inboxes ) {
			return;
		}

		// Delete the transient as we no longer need it.
		\delete_transient( $transient_key );

		$retries = self::send_to_inboxes( $inboxes, $outbox_item_id );

		// Retry failed inboxes.
		if ( ++$attempt < self::get_retry_max_attempts() && ! empty( $retries ) ) {
			self::schedule_retry( $retries, $outbox_item_id, $attempt );
		}
	}

	/**
	 * Send to inboxes.
	 *
	 * @param array $inboxes        The inboxes to notify.
	 * @param int   $outbox_item_id The Outbox item ID.
	 * @return array The failed inboxes.
	 */
	private static function send_to_inboxes( $inboxes, $outbox_item_id ) {
		$outbox_item = \get_post( $outbox_item_id );
		$json        = Outbox::get_activity( $outbox_item_id )->to_json();
		$retries     = array();

		/**
		 * Fires before sending an Activity to inboxes.
		 *
		 * @param string $json           The ActivityPub Activity JSON.
		 * @param array  $inboxes        The inboxes to send to.
		 * @param int    $outbox_item_id The Outbox item ID.
		 */
		\do_action( 'activitypub_pre_send_to_inboxes', $json, $inboxes, $outbox_item_id );

		foreach ( $inboxes as $inbox ) {
			$result = safe_remote_post( $inbox, $json, $outbox_item->post_author );

			if ( is_wp_error( $result ) && in_array( $result->get_error_code(), self::get_retry_error_codes(), true ) ) {
				$retries[] = $inbox;
			}

			/**
			 * Fires after an Activity has been sent to an inbox.
			 *
			 * @param array  $result         The result of the remote post request.
			 * @param string $inbox          The inbox URL.
			 * @param string $json           The ActivityPub Activity JSON.
			 * @param int    $actor_id       The actor ID.
			 * @param int    $outbox_item_id The Outbox item ID.
			 */
			\do_action( 'activitypub_sent_to_inbox', $result, $inbox, $json, $outbox_item->post_author, $outbox_item_id );
		}

		return $retries;
	}

	/**
	 * Schedule a retry.
	 *
	 * @param array $retries        The inboxes to retry.
	 * @param int   $outbox_item_id The Outbox item ID.
	 * @param int   $attempt        Optional. The attempt number. Default 1.
	 */
	private static function schedule_retry( $retries, $outbox_item_id, $attempt = 1 ) {
		$transient_key = 'activitypub_retry_' . \wp_generate_password( 12, false );
		\set_transient( $transient_key, $retries, WEEK_IN_SECONDS );

		\wp_schedule_single_event(
			\time() + ( $attempt * $attempt * self::get_retry_delay() ),
			'activitypub_retry_activity',
			array( $transient_key, $outbox_item_id, $attempt )
		);
	}

	/**
	 * Send an Activity to a custom list of inboxes, like mentioned users or replied-to posts.
	 *
	 * For all custom implementations, please use the `activitypub_additional_inboxes` filter.
	 *
	 * @param Activity $activity    The ActivityPub Activity.
	 * @param int      $actor_id    The actor ID.
	 * @param \WP_Post $outbox_item The WordPress object.
	 */
	private static function send_to_additional_inboxes( $activity, $actor_id, $outbox_item = null ) {
		/**
		 * Filters the list of inboxes to send the Activity to.
		 *
		 * @param array    $inboxes  The list of inboxes to send to.
		 * @param int      $actor_id The actor ID.
		 * @param Activity $activity The ActivityPub Activity.
		 */
		$inboxes = apply_filters( 'activitypub_additional_inboxes', array(), $actor_id, $activity );
		$inboxes = array_unique( $inboxes );

		$retries = self::send_to_inboxes( $inboxes, $outbox_item->ID );

		// Retry failed inboxes.
		if ( ! empty( $retries ) ) {
			self::schedule_retry( $retries, $outbox_item->ID );
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
			// No need to self-notify.
			if ( is_same_domain( $url ) ) {
				continue;
			}

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

		if ( $send ) {
			$followers = Followers::get_inboxes_for_activity( $activity->to_json(), $outbox_item->post_author );

			// Only send if there are followers to send to.
			$send = ! is_countable( $followers ) || 0 < count( $followers );
		}

		/**
		 * Filters whether to send an Activity to followers.
		 *
		 * @param bool     $send_activity_to_followers Whether to send the Activity to followers.
		 * @param Activity $activity                   The ActivityPub Activity.
		 * @param int      $actor_id                   The actor ID.
		 * @param \WP_Post $outbox_item                The WordPress object.
		 */
		return apply_filters( 'activitypub_send_activity_to_followers', $send, $activity, $outbox_item->post_author, $outbox_item );
	}

	/**
	 * Add Inboxes of Relays.
	 *
	 * @param array    $inboxes  The list of Inboxes.
	 * @param int      $actor_id The Actor-ID.
	 * @param Activity $activity The ActivityPub Activity.
	 *
	 * @return array The filtered Inboxes.
	 */
	public static function add_inboxes_of_relays( $inboxes, $actor_id, $activity ) {
		// Check if follower endpoint is set.
		$cc = $activity->get_cc() ?? array();
		$to = $activity->get_to() ?? array();

		$audience = array_merge( $cc, $to );

		// Check if activity is public.
		if ( ! in_array( 'https://www.w3.org/ns/activitystreams#Public', $audience, true ) ) {
			return $inboxes;
		}

		$relays = \get_option( 'activitypub_relays', array() );

		if ( empty( $relays ) ) {
			return $inboxes;
		}

		return array_merge( $inboxes, $relays );
	}

	/**
	 * Send an immediate Accept activity for the given Outbox item.
	 *
	 * @param int      $outbox_id The Outbox item ID.
	 * @param Activity $activity  The Activity that was just added to the Outbox.
	 */
	public static function send_immediate_accept( $outbox_id, $activity ) {
		$outbox_item = \get_post( $outbox_id );

		if ( ! $outbox_item || 'Accept' !== $activity->get_type() ) {
			return;
		}

		// Send to mentioned and replied-to users. Everyone other than followers.
		self::send_to_additional_inboxes( $activity, $outbox_item->post_author, $outbox_item );
	}
}
