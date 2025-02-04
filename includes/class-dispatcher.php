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
	public static $batch_size = 50;

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action( 'activitypub_process_outbox', array( self::class, 'process_outbox' ) );

		// Default filters to add Inboxes to sent to.
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

		self::send_activity_to_followers( $activity, $actor_id, $outbox_item );
		self::async_batch_processor( $activity, $actor_id, $outbox_item );
	}

	/**
	 * Asynchronously runs batch processing routines.
	 *
	 * @params mixed ...$args  Optional. Parameters that get passed to the callback.
	 */
	public static function async_batch_processor( $activity, $actor_id, $outbox_item ) {
		// Bail if the existing lock is still valid.
		if ( self::is_locked( $outbox_item->ID ) ) {
			// phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
			\wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'activitypub_outbox_process_batch', \func_get_args() );
			return;
		}

		self::lock( $outbox_item->ID );

		// Query for the next batch of followers.
		$followers = get_posts(
			array(
				'post_type'      => Followers::POST_TYPE,
				'posts_per_page' => self::$batch_size,
				'fields'         => 'ids',

				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => '_activitypub_user_id',
						'value' => $actor_id,
					),
					array(
						'key'     => '_activitypub_inbox',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_activitypub_inbox',
						'value'   => '',
						'compare' => '!=',
					),
					array(
						'key'     => '_activitypub_processed_outbox_' . $outbox_item->ID,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$json = $activity->to_json();

		foreach ( $followers as $follower_id ) {
			$inbox  = get_post_meta( $follower_id, '_activitypub_inbox', true );
			$result = safe_remote_post( $inbox, $json, $actor_id );

			if ( wp_remote_retrieve_response_code( $result ) >= 400 ) {
				$attempt = get_post_meta( $follower_id, '_activitypub_outbox_attempt_' . $outbox_item->ID, true );
				$attempt = $attempt ? $attempt + 1 : 1;

				if ( $attempt <= 3 ) {
					// Log attempt and move on.
					update_post_meta( $follower_id, '_activitypub_outbox_attempt_' . $outbox_item->ID, $attempt );
					continue;
				} else {
					Followers::add_error( $follower_id, wp_remote_retrieve_response_message( $result ) );
				}
			}

			// Mark as processed.
			\update_post_meta( $follower_id, '_activitypub_processed_outbox_' . $outbox_item->ID, true );
		}

		self::unlock( $outbox_item->ID );

		if ( is_countable( $followers ) && count( $followers ) < self::$batch_size ) {
			// No more followers to process for this update.
			\delete_metadata( 'post', 0, '_activitypub_processed_outbox_' . $outbox_item->ID, '', true );
			\wp_publish_post( $outbox_item );
		} else {
			// phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
			\wp_schedule_single_event( \time() + 30, 'activitypub_outbox_process_batch', \func_get_args() );
		}
	}

	/**
	 * Locks the database migration process to prevent simultaneous migrations.
	 *
	 * @param int $outbox_item_id The Outbox item ID.
	 * @return bool|int True if the lock was successful, timestamp of existing lock otherwise.
	 */
	public static function lock( $outbox_item_id ) {
		global $wpdb;

		// Try to lock.
		$lock_result = (bool) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no') /* LOCK */", 'activitypub_outbox_processing_lock_' . $outbox_item_id, \time() ) ); // phpcs:ignore WordPress.DB

		if ( ! $lock_result ) {
			$lock_result = \get_option( 'activitypub_outbox_processing_lock_' . $outbox_item_id );
		}

		return $lock_result;
	}

	/**
	 * Unlocks processing for this Outbox item.
	 *
	 * @param int $outbox_item_id The Outbox item ID.
	 */
	public static function unlock( $outbox_item_id ) {
		\delete_option( 'activitypub_outbox_processing_lock_' . $outbox_item_id );
	}

	/**
	 * Whether the outbox processing for this Outbox item is locked.
	 *
	 * @param int $outbox_item_id The Outbox item ID.
	 * @return boolean
	 */
	public static function is_locked( $outbox_item_id ) {
		$lock = \get_option( 'activitypub_outbox_processing_lock_' . $outbox_item_id );

		if ( ! $lock ) {
			return false;
		}

		$lock = (int) $lock;

		if ( $lock < \time() - 1800 ) {
			self::unlock( $outbox_item_id );
			return false;
		}

		return true;
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
