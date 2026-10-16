<?php
/**
 * Scheduler class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Inbox;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Collection\Remote_Posts;
use Activitypub\Scheduler\Actor;
use Activitypub\Scheduler\Collection_Sync;
use Activitypub\Scheduler\Comment;
use Activitypub\Scheduler\Post;
use Activitypub\Scheduler\Quote_Request;
use Activitypub\Scheduler\Statistics;

/**
 * Scheduler class.
 *
 * @author Matthias Pfefferle
 */
class Scheduler {

	/**
	 * Scheduled events with their recurrence.
	 *
	 * @var array
	 */
	const SCHEDULES = array(
		'activitypub_update_remote_actors'         => 'hourly',
		'activitypub_cleanup_remote_actors'        => 'daily',
		'activitypub_reprocess_outbox'             => 'hourly',
		'activitypub_outbox_purge'                 => 'daily',
		'activitypub_inbox_purge'                  => 'daily',
		'activitypub_ap_post_purge'                => 'daily',
		'activitypub_tombstone_purge'              => 'daily',
		'activitypub_sync_blocklist_subscriptions' => 'weekly',
	);

	/**
	 * Allowed batch callbacks.
	 *
	 * @var array
	 */
	private static $batch_callbacks = array();

	/**
	 * Get the pause between async batches (in seconds).
	 *
	 * @param string|false|null $hook Optional. The async batch hook being scheduled. Default current action.
	 *
	 * @return int The pause in seconds.
	 */
	public static function get_retry_delay( $hook = null ) {
		if ( null === $hook ) {
			$hook = \current_action();
		}

		/**
		 * Filters the pause between async batches (in seconds).
		 *
		 * @param int               $async_batch_pause The pause in seconds. Default 30.
		 * @param string|false|null $hook The async batch hook being scheduled.
		 */
		return \apply_filters( 'activitypub_scheduler_async_batch_pause', 30, $hook );
	}

	/**
	 * Initialize the class, registering WordPress hooks.
	 *
	 * @return void
	 */
	public static function init() {
		self::register_schedulers();

		// Custom cron schedules.
		\add_filter( 'cron_schedules', array( self::class, 'add_cron_schedules' ) );

		// Follower Cleanups.
		\add_action( 'activitypub_update_remote_actors', array( self::class, 'update_remote_actors' ) );
		\add_action( 'activitypub_cleanup_remote_actors', array( self::class, 'cleanup_remote_actors' ) );
		\add_action( 'activitypub_cleanup_remote_actors', array( self::class, 'cleanup_cache_only_remote_actors' ) );
		self::register_async_batch_callback( 'activitypub_cleanup_actor_candidates', array( self::class, 'cleanup_cache_only_remote_actors_batch' ) );

		// Event callbacks.
		\add_action( 'activitypub_async_batch', array( self::class, 'async_batch' ), 10, 99 );
		\add_action( 'activitypub_reprocess_outbox', array( self::class, 'reprocess_outbox' ) );
		\add_action( 'activitypub_outbox_purge', array( self::class, 'purge_outbox' ) );
		\add_action( 'activitypub_inbox_purge', array( self::class, 'purge_inbox' ) );
		\add_action( 'activitypub_ap_post_purge', array( self::class, 'purge_ap_posts' ) );
		\add_action( 'activitypub_tombstone_purge', array( self::class, 'purge_tombstones' ) );
		\add_action( 'activitypub_inbox_create_item', array( self::class, 'process_inbox_activity' ) );
		\add_action( 'activitypub_sync_blocklist_subscriptions', array( Blocklist_Subscriptions::class, 'sync_all' ) );

		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'schedule_outbox_activity_for_federation' ) );
		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'schedule_announce_activity' ), 10, 4 );

		\add_action( 'update_option_activitypub_outbox_purge_days', array( self::class, 'update_outbox_purge_schedule' ), 10, 2 );
		\add_action( 'update_option_activitypub_inbox_purge_days', array( self::class, 'update_inbox_purge_schedule' ), 10, 2 );
		\add_action( 'update_option_activitypub_ap_post_purge_days', array( self::class, 'update_ap_post_purge_schedule' ), 10, 2 );
	}

	/**
	 * Register handlers.
	 *
	 * @return void
	 */
	public static function register_schedulers() {
		Post::init();
		Actor::init();
		Collection_Sync::init();
		Comment::init();
		Quote_Request::init();
		Statistics::init();

		/**
		 * Register additional schedulers.
		 *
		 * @since 5.0.0
		 */
		\do_action( 'activitypub_register_schedulers' );
	}

	/**
	 * Add custom cron schedules.
	 *
	 * @param array $schedules Existing cron schedules.
	 *
	 * @return array Modified cron schedules.
	 */
	public static function add_cron_schedules( $schedules ) {
		$schedules['monthly'] = array(
			'interval' => MONTH_IN_SECONDS,
			'display'  => \__( 'Once Monthly', 'activitypub' ),
		);

		$schedules['yearly'] = array(
			'interval' => YEAR_IN_SECONDS,
			'display'  => \__( 'Once Yearly', 'activitypub' ),
		);

		return $schedules;
	}

	/**
	 * Register a batch callback for async processing.
	 *
	 * @param string   $hook     The cron event hook name.
	 * @param callable $callback The callback to execute.
	 *
	 * @return void
	 */
	public static function register_async_batch_callback( $hook, $callback ) {
		if ( \did_action( 'init' ) && ! \doing_action( 'init' ) ) {
			\_doing_it_wrong( __METHOD__, 'Async batch callbacks should be registered before or during the init action.', '7.5.0' );
			return;
		}

		if ( ! \is_callable( $callback ) ) {
			return;
		}

		self::$batch_callbacks[ $hook ] = $callback;

		// Register the WordPress action hook to trigger async_batch.
		\add_action( $hook, array( self::class, 'async_batch' ), 10, 99 );
	}

	/**
	 * Schedule all ActivityPub schedules.
	 *
	 * @return void
	 */
	public static function register_schedules() {
		foreach ( self::SCHEDULES as $hook => $recurrence ) {
			if ( ! \wp_next_scheduled( $hook ) ) {
				\wp_schedule_event( \time(), $recurrence, $hook );
			}
		}

		// Schedule monthly stats collection for the 1st of each month.
		if ( ! \wp_next_scheduled( 'activitypub_collect_monthly_stats' ) ) {
			// Calculate next 1st of month at 2:00 AM.
			$next_first = self::get_next_first_of_month();
			\wp_schedule_event( $next_first, 'monthly', 'activitypub_collect_monthly_stats' );
		}

		// Schedule annual stats compilation for December 1st (wrapped notification).
		if ( ! \wp_next_scheduled( 'activitypub_compile_annual_stats' ) ) {
			$next_december = self::get_next_december_first();
			\wp_schedule_event( $next_december, 'yearly', 'activitypub_compile_annual_stats' );
		}
	}

	/**
	 * Un-schedule all ActivityPub schedules.
	 *
	 * @return void
	 */
	public static function deregister_schedules() {
		foreach ( \array_keys( self::SCHEDULES ) as $hook ) {
			\wp_unschedule_hook( $hook );
		}

		// Statistics schedules.
		\wp_unschedule_hook( 'activitypub_collect_monthly_stats' );
		\wp_unschedule_hook( 'activitypub_compile_annual_stats' );
	}

	/**
	 * Get the next 1st of month timestamp.
	 *
	 * @return int Unix timestamp of next 1st of month at 2:00 AM.
	 */
	private static function get_next_first_of_month() {
		$now        = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$next_month = \strtotime( 'first day of next month 02:00:00', $now );

		return $next_month;
	}

	/**
	 * Get the next December 1st timestamp for wrapped notification.
	 *
	 * @return int Unix timestamp of next December 1st at 3:00 AM.
	 */
	private static function get_next_december_first() {
		$now  = \current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$year = (int) \gmdate( 'Y', $now );

		// Get December 1st 3:00 AM for this year.
		$this_year_dec_first = \strtotime( \sprintf( '%d-12-01 03:00:00', $year ) );

		// If we're already past this year's December 1st, schedule for next year.
		if ( $now >= $this_year_dec_first ) {
			return \strtotime( \sprintf( '%d-12-01 03:00:00', $year + 1 ) );
		}

		return $this_year_dec_first;
	}

	/**
	 * Unschedule events for an outbox item.
	 *
	 * @param int $outbox_item_id The outbox item ID.
	 *
	 * @return void
	 */
	public static function unschedule_events_for_item( $outbox_item_id ) {
		\delete_post_meta( $outbox_item_id, '_activitypub_outbox_offset' );

		$timestamp = \wp_next_scheduled( 'activitypub_process_outbox', array( $outbox_item_id ) );
		\wp_unschedule_event( $timestamp, 'activitypub_process_outbox', array( $outbox_item_id ) );

		self::unschedule_outbox_delivery_batches( $outbox_item_id );

		// Invalidate any retries for this outbox item.
		foreach ( \_get_cron_array() as $timestamp => $cron ) {
			if ( ! isset( $cron['activitypub_retry_activity'] ) ) {
				continue;
			}

			foreach ( $cron['activitypub_retry_activity'] as $event ) {
				if ( isset( $event['args'][1] ) && $outbox_item_id === $event['args'][1] ) {
					\wp_unschedule_event( $timestamp, 'activitypub_retry_activity', $event['args'] );
				}
			}
		}
	}

	/**
	 * Update remote Actors.
	 *
	 * @return void
	 */
	public static function update_remote_actors() {
		$number = 5;

		if ( \defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$number = 50;
		}

		/**
		 * Filter the number of remote Actors to update.
		 *
		 * @param int $number The number of remote Actors to update.
		 */
		$number = \apply_filters( 'activitypub_update_remote_actors_number', $number );
		$actors = Remote_Actors::get_outdated( $number );

		foreach ( $actors as $actor ) {
			/*
			 * Use Proxy::get() directly here.
			 * get_remote_metadata_by_actor() short-circuits to the locally
			 * cached ap_actor CPT via Remote_Actors::fetch_by_uri() and never
			 * makes an HTTP request when the actor is already cached, so the
			 * upsert would just rewrite the same stale data and this refresh
			 * would be a no-op. The Update handler documents the same trap.
			 */
			$meta = Proxy::get( $actor->guid, array( 'cached' => false ) );

			if ( empty( $meta ) || ! \is_array( $meta ) || \is_wp_error( $meta ) ) {
				Remote_Actors::add_error( $actor->ID, 'Failed to fetch or parse metadata' );
			} else {
				/*
				 * Only refresh when the remote still reports the same identity. A
				 * different (or missing) id means a Move or a malformed response;
				 * applying it would rewrite the cached guid in place and could
				 * collide with another cached actor, so leave the record alone.
				 * Updating by the known post ID otherwise refreshes it without the
				 * redundant get_by_uri() lookup upsert() would do.
				 */
				$fetched_id = isset( $meta['id'] ) && \is_string( $meta['id'] ) ? \esc_url_raw( $meta['id'] ) : '';
				if ( $fetched_id !== $actor->guid ) {
					/*
					 * Bump only the modified date, directly, so the skipped actor
					 * drops out of the outdated queue and is not re-fetched every
					 * run. A direct write avoids the save_post hooks wp_update_post()
					 * fires (which would needlessly clear the cached avatar); the
					 * record is intentionally left unchanged otherwise.
					 */
					global $wpdb;
					$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->posts,
						array(
							'post_modified'     => \current_time( 'mysql' ),
							'post_modified_gmt' => \current_time( 'mysql', true ),
						),
						array( 'ID' => $actor->ID )
					);
					\clean_post_cache( $actor->ID );
					continue;
				}

				$id = Remote_Actors::update( $actor->ID, $meta );
				if ( \is_wp_error( $id ) ) {
					continue;
				}
				Remote_Actors::clear_errors( $id );
			}
		}
	}

	/**
	 * Cleanup remote Actors.
	 *
	 * @return void
	 */
	public static function cleanup_remote_actors() {
		$number = 5;

		if ( \defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$number = 50;
		}

		/**
		 * Filter the number of remote Actors to clean up.
		 *
		 * @param int $number The number of remote Actors to clean up.
		 */
		$number = \apply_filters( 'activitypub_cleanup_remote_actors_number', $number );
		$actors = Remote_Actors::get_faulty( $number );

		foreach ( $actors as $actor ) {
			$meta = get_remote_metadata_by_actor( $actor->guid, false );

			if ( Tombstone::exists( $meta ) ) {
				Remote_Actors::delete( $actor->ID );
			} elseif ( empty( $meta ) || ! \is_array( $meta ) || \is_wp_error( $meta ) ) {
				if ( Remote_Actors::count_errors( $actor->ID ) >= 5 ) {
					\wp_schedule_single_event( \time(), 'activitypub_delete_remote_actor_interactions', array( $actor->guid ) );
					\wp_schedule_single_event( \time(), 'activitypub_delete_remote_actor_posts', array( $actor->guid ) );
					Remote_Actors::delete( $actor->ID );
				} else {
					Remote_Actors::add_error( $actor->ID, $meta );
				}
			} else {
				$id = Remote_Actors::upsert( $meta );
				if ( \is_wp_error( $id ) ) {
					Remote_Actors::add_error( $actor->ID, $id );
				} else {
					Remote_Actors::clear_errors( $actor->ID );
				}
			}
		}
	}

	/**
	 * Schedule the outbox item for federation.
	 *
	 * @param int $id     The ID of the outbox item.
	 * @param int $offset The offset to add to the scheduled time. Default 3 seconds.
	 *
	 * @return void
	 */
	public static function schedule_outbox_activity_for_federation( $id, $offset = 3 ) {
		$hook = 'activitypub_process_outbox';
		$args = array( $id );

		if ( false === \wp_next_scheduled( $hook, $args ) ) {
			\wp_schedule_single_event(
				\time() + $offset,
				$hook,
				$args
			);
		}
	}

	/**
	 * Reprocess the outbox.
	 *
	 * @return void
	 */
	public static function reprocess_outbox() {
		$ids = \get_posts(
			array(
				'post_type'      => Outbox::POST_TYPE,
				'post_status'    => 'pending',
				'posts_per_page' => 10,
				'fields'         => 'ids',
			)
		);

		foreach ( $ids as $id ) {
			// Bail if there is a pending batch.
			$offset = \get_post_meta( $id, '_activitypub_outbox_offset', true ) ?: 0; // phpcs:ignore
			if ( self::has_scheduled_outbox_delivery_batch( $id, $offset ) ) {
				return;
			}

			// Bail if there is a batch in progress.
			$key = \md5( \serialize( $id ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			if ( self::is_locked( $key ) ) {
				return;
			}

			self::schedule_outbox_activity_for_federation( $id );
		}
	}

	/**
	 * Purge outbox items based on a schedule.
	 *
	 * @return void
	 */
	public static function purge_outbox() {
		Outbox::purge( \get_option( 'activitypub_outbox_purge_days', ACTIVITYPUB_OUTBOX_PURGE_DAYS ) );
	}

	/**
	 * Purge inbox items based on a schedule.
	 *
	 * @return void
	 */
	public static function purge_inbox() {
		Inbox::purge( \get_option( 'activitypub_inbox_purge_days', ACTIVITYPUB_INBOX_PURGE_DAYS ) );
	}

	/**
	 * Purge remote posts based on a schedule.
	 *
	 * @return void
	 */
	public static function purge_ap_posts() {
		Remote_Posts::purge( \get_option( 'activitypub_ap_post_purge_days', ACTIVITYPUB_AP_POST_PURGE_DAYS ) );
	}

	/**
	 * Daily cron handler that purges expired tombstones.
	 *
	 * Retention is non-urgent: large backlogs (e.g. after retention is first enforced)
	 * drain across multiple daily runs.
	 *
	 * @since 8.3.0
	 *
	 * @return void
	 */
	public static function purge_tombstones() {
		Tombstone::purge();
	}

	/**
	 * Process cached inbox activity.
	 *
	 * Retrieves all collected user IDs for an activity and processes them together.
	 *
	 * @param string $activity_id The activity ID.
	 *
	 * @return void
	 */
	public static function process_inbox_activity( $activity_id ) {
		// Deduplicate if multiple inbox items were created due to race condition.
		$inbox_item = Inbox::deduplicate( $activity_id );
		if ( ! $inbox_item ) {
			return;
		}

		$data = \json_decode( $inbox_item->post_content, true );
		// Reconstruct activity from inbox post.
		$activity = Activity::init_from_array( $data );
		$type     = camel_to_snake_case( $activity->get_type() );
		$context  = Inbox::CONTEXT_INBOX;
		$user_ids = Inbox::get_recipients( $inbox_item->ID );

		/**
		 * Fires after any ActivityPub Inbox activity has been handled, regardless of activity type.
		 *
		 * This hook is triggered for all activity types processed by the inbox handler.
		 *
		 * @param array    $data     The data array.
		 * @param array    $user_ids The user IDs.
		 * @param string   $type     The type of the activity.
		 * @param Activity $activity The Activity object.
		 * @param int      $result   The ID of the inbox item that was created, or WP_Error if failed.
		 * @param string   $context  The context of the request ('inbox' or 'shared_inbox').
		 */
		\do_action( 'activitypub_handled_inbox', $data, $user_ids, $type, $activity, $inbox_item->ID, $context );

		/**
		 * Fires after an ActivityPub Inbox activity has been handled.
		 *
		 * @param array    $data     The data array.
		 * @param array    $user_ids The user IDs.
		 * @param Activity $activity The Activity object.
		 * @param int      $result   The ID of the inbox item that was created, or WP_Error if failed.
		 * @param string   $context  The context of the request ('inbox' or 'shared_inbox').
		 */
		\do_action( 'activitypub_handled_inbox_' . $type, $data, $user_ids, $activity, $inbox_item->ID, $context );
	}

	/**
	 * Update schedules when outbox purge days settings change.
	 *
	 * @param int $old_value The old value.
	 * @param int $value     The new value.
	 *
	 * @return void
	 */
	public static function update_outbox_purge_schedule( $old_value, $value ) {
		if ( 0 === (int) $value ) {
			\wp_clear_scheduled_hook( 'activitypub_outbox_purge' );
		} elseif ( ! \wp_next_scheduled( 'activitypub_outbox_purge' ) ) {
			\wp_schedule_event( \time(), 'daily', 'activitypub_outbox_purge' );
		}
	}

	/**
	 * Update schedules when inbox purge days settings change.
	 *
	 * @param int $old_value The old value.
	 * @param int $value     The new value.
	 *
	 * @return void
	 */
	public static function update_inbox_purge_schedule( $old_value, $value ) {
		if ( 0 === (int) $value ) {
			\wp_clear_scheduled_hook( 'activitypub_inbox_purge' );
		} elseif ( ! \wp_next_scheduled( 'activitypub_inbox_purge' ) ) {
			\wp_schedule_event( \time(), 'daily', 'activitypub_inbox_purge' );
		}
	}

	/**
	 * Update schedules when remote posts purge days settings change.
	 *
	 * @param int $old_value The old value.
	 * @param int $value     The new value.
	 *
	 * @return void
	 */
	public static function update_ap_post_purge_schedule( $old_value, $value ) {
		if ( 0 === (int) $value ) {
			\wp_clear_scheduled_hook( 'activitypub_ap_post_purge' );
		} elseif ( ! \wp_next_scheduled( 'activitypub_ap_post_purge' ) ) {
			\wp_schedule_event( \time(), 'daily', 'activitypub_ap_post_purge' );
		}
	}

	/**
	 * Asynchronously runs batch processing routines.
	 *
	 * The batching part is optional and only comes into play if the callback returns anything.
	 * Beyond that it's a helper to run a callback asynchronously with locking to prevent simultaneous processing.
	 *
	 * @params mixed ...$args Optional. Parameters that get passed to the callback.
	 *
	 * @return void
	 */
	public static function async_batch() {
		$args     = \func_get_args(); // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue
		$callback = self::$batch_callbacks[ \current_action() ] ?? $args[0] ?? null;
		if ( ! \is_callable( $callback ) ) {
			\_doing_it_wrong( __METHOD__, 'There must be a valid callback associated with the current action.', '5.2.0' );
			return;
		}

		$key = \md5( \serialize( $callback ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

		// Bail if the existing lock is still valid.
		if ( self::is_locked( $key ) ) {
			\wp_schedule_single_event( \time() + MINUTE_IN_SECONDS, \current_action(), $args );
			return;
		}

		self::lock( $key );

		if ( \is_callable( $args[0] ?? null ) ) {
			$callback = \array_shift( $args ); // Remove $callback from arguments.
		}
		$next = \call_user_func_array( $callback, $args );

		self::unlock( $key );

		if ( ! empty( $next ) ) {
			// Schedule the next run, adding the result to the arguments.
			\wp_schedule_single_event( \time() + self::get_retry_delay( \current_action() ), \current_action(), \array_values( $next ) );
		}
	}

	/**
	 * Whether an outbox item already has a scheduled delivery batch at an offset.
	 *
	 * @param int $outbox_item_id The outbox item ID.
	 * @param int $offset         The delivery offset.
	 *
	 * @return bool True when a matching delivery batch is scheduled.
	 */
	private static function has_scheduled_outbox_delivery_batch( $outbox_item_id, $offset ) {
		return ! empty( self::get_scheduled_outbox_delivery_batches( $outbox_item_id, $offset ) );
	}

	/**
	 * Unschedule all pending delivery batches for an outbox item.
	 *
	 * @param int $outbox_item_id The outbox item ID.
	 *
	 * @return void
	 */
	private static function unschedule_outbox_delivery_batches( $outbox_item_id ) {
		foreach ( self::get_scheduled_outbox_delivery_batches( $outbox_item_id ) as $event ) {
			\wp_unschedule_event( $event['timestamp'], 'activitypub_send_activity', $event['args'] );
		}
	}

	/**
	 * Get scheduled delivery batches for an outbox item.
	 *
	 * The batch size is deliberately ignored because scheduled events may
	 * retain an older value after the admin changes the distribution mode.
	 *
	 * @param int      $outbox_item_id The outbox item ID.
	 * @param int|null $offset         Optional. Restrict results to this delivery offset.
	 *
	 * @return array Scheduled events with timestamp and args.
	 */
	private static function get_scheduled_outbox_delivery_batches( $outbox_item_id, $offset = null ) {
		$events = array();

		foreach ( \_get_cron_array() as $timestamp => $cron ) {
			if ( empty( $cron['activitypub_send_activity'] ) ) {
				continue;
			}

			foreach ( $cron['activitypub_send_activity'] as $event ) {
				$args = $event['args'] ?? array();

				if ( ! isset( $args[0] ) || (int) $outbox_item_id !== (int) $args[0] ) {
					continue;
				}

				if ( null !== $offset && (int) ( $args[2] ?? 0 ) !== (int) $offset ) {
					continue;
				}

				$events[] = array(
					'timestamp' => $timestamp,
					'args'      => $args,
				);
			}
		}

		return $events;
	}

	/**
	 * Locks the async batch process for individual callbacks to prevent simultaneous processing.
	 *
	 * @param string $key Serialized callback name.
	 * @return bool|int True if the lock was successful, timestamp of existing lock otherwise.
	 */
	public static function lock( $key ) {
		global $wpdb;

		// Try to lock.
		$lock_result = (bool) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no') /* LOCK */", 'activitypub_async_batch_' . $key, \time() ) ); // phpcs:ignore WordPress.DB

		if ( ! $lock_result ) {
			$lock_result = \get_option( 'activitypub_async_batch_' . $key );
		}

		return $lock_result;
	}

	/**
	 * Unlocks processing for the async batch callback.
	 *
	 * @param string $key Serialized callback name.
	 *
	 * @return void
	 */
	public static function unlock( $key ) {
		\delete_option( 'activitypub_async_batch_' . $key );
	}

	/**
	 * Whether the async batch callback is locked.
	 *
	 * @param string $key Serialized callback name.
	 * @return boolean
	 */
	public static function is_locked( $key ) {
		$lock = \get_option( 'activitypub_async_batch_' . $key );

		if ( ! $lock ) {
			return false;
		}

		$lock = (int) $lock;

		if ( $lock < \time() - 1800 ) {
			self::unlock( $key );
			return false;
		}

		return true;
	}

	/**
	 * Send announces.
	 *
	 * @param int      $outbox_activity_id The outbox activity ID.
	 * @param Activity $activity           The activity object.
	 * @param int      $actor_id           The actor ID.
	 * @param int      $content_visibility The content visibility.
	 *
	 * @return void
	 */
	public static function schedule_announce_activity( $outbox_activity_id, $activity, $actor_id, $content_visibility ) {
		// Only if we're in both Blog and User modes.
		if ( ACTIVITYPUB_ACTOR_AND_BLOG_MODE !== \get_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE ) ) {
			return;
		}

		// Only if this isn't the Blog Actor.
		if ( Actors::BLOG_USER_ID === $actor_id ) {
			return;
		}

		// Only if the content is public or quiet public.
		if ( ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC !== $content_visibility ) {
			return;
		}

		// Only if the activity is a Create.
		if ( 'Create' !== $activity->get_type() ) {
			return;
		}

		if ( ! \is_object( $activity->get_object() ) ) {
			return;
		}

		// Check if the object is an article, image, audio, video, event, or document and ignore profile updates and other activities.
		if ( ! \in_array( $activity->get_object()->get_type(), Base_Object::TYPES, true ) ) {
			return;
		}

		$announce = new Activity();
		$announce->set_type( 'Announce' );
		$announce->set_actor( Actors::get_by_id( Actors::BLOG_USER_ID )->get_id() );
		$announce->set_object( $activity );
		$announce->add_cc( object_to_uri( $activity->get_actor() ) );

		$outbox_activity_id = Outbox::add( $announce, Actors::BLOG_USER_ID );

		if ( ! $outbox_activity_id ) {
			return;
		}

		// Schedule the outbox item for federation.
		self::schedule_outbox_activity_for_federation( $outbox_activity_id, 120 );
	}
	/**
	 * Start candidate cleanup as an independent listener on the existing cron action.
	 *
	 * @return void
	 */
	public static function cleanup_cache_only_remote_actors() {
		$next = self::cleanup_cache_only_remote_actors_batch();
		if ( $next && ! \wp_next_scheduled( 'activitypub_cleanup_actor_candidates', $next ) ) {
			\wp_schedule_single_event(
				\time() + self::get_retry_delay( 'activitypub_cleanup_actor_candidates' ),
				'activitypub_cleanup_actor_candidates',
				$next
			);
		}
	}

	/**
	 * Process one bounded batch using stored posts, without changing classification on reads.
	 *
	 * @param int $batch Continuation batch number used by the async scheduler.
	 * @return int[]|null Next batch arguments when progress was made.
	 */
	public static function cleanup_cache_only_remote_actors_batch( $batch = 0 ) {
		if ( ! \get_option( 'activitypub_actor_retention_migrated' ) ) {
			return null;
		}
		$limit = \max( 1, (int) \apply_filters( 'activitypub_actor_cleanup_batch_size', 20 ) );
		$query = new \WP_Query(
			array(
				'post_type'      => Remote_Actors::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => Remote_Actors::RETAIN_META_KEY,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => Remote_Actors::RETAIN_META_KEY,
						'value'   => '1',
						'compare' => '!=',
					),
				),
			)
		);
		if ( ! $query->posts ) {
			return null;
		}

		// Parse stored outgoing activities once per batch, not once per actor.
		$outbox_uris = self::get_outbox_uris();
		$progress    = false;
		foreach ( $query->posts as $actor ) {
			$in_use = isset( $outbox_uris[ $actor->guid ] ) || self::remote_actor_is_in_use( $actor );
			/**
			 * Allow integrations to preserve references unknown to ActivityPub.
			 *
			 * Returning false cannot override a native reference.
			 *
			 * @since unreleased
			 *
			 * @param bool     $in_use Whether an integration needs the actor.
			 * @param \WP_Post $actor  Stored actor, without changing its classification.
			 */
			$in_use = \apply_filters( 'activitypub_remote_actor_is_in_use', false, $actor ) || $in_use;
			if ( $in_use ) {
				Remote_Actors::set_cache_only( $actor, false );
				$progress = true;
				continue;
			}
			// An integration may have promoted the actor while inspecting it.
			if ( ! \get_post_meta( $actor->ID, Remote_Actors::RETAIN_META_KEY, true ) ) {
				$progress = (bool) Remote_Actors::delete( $actor->ID ) || $progress;
			} else {
				$progress = true;
			}
		}
		if ( $progress && \count( $query->posts ) === $limit ) {
			return array( $batch + 1 );
		}
		return null;
	}

	/**
	 * Check durable native references, including blog ID zero and trashed content.
	 *
	 * @param \WP_Post $actor Stored actor.
	 * @return bool Whether the actor is referenced.
	 */
	public static function remote_actor_is_in_use( $actor ) {
		foreach ( array( Followers::FOLLOWER_META_KEY, Following::FOLLOWING_META_KEY, Following::PENDING_META_KEY, Moderation::BLOCKED_ACTORS_META_KEY ) as $key ) {
			if ( \metadata_exists( 'post', $actor->ID, $key ) ) {
				return true;
			}
		}
		// These references belong to specific ActivityPub collections, not arbitrary posts.
		$references = array(
			Remote_Posts::POST_TYPE => array(
				'key'   => '_activitypub_remote_actor_id',
				'value' => $actor->ID,
			),
			Inbox::POST_TYPE        => array(
				'key'   => '_activitypub_activity_remote_actor',
				'value' => $actor->guid,
			),
		);
		foreach ( $references as $post_type => $reference ) {
			$query = new \WP_Query(
				array(
					'post_type'      => $post_type,
					'post_status'    => \array_values( \get_post_stati() ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'     => array( $reference ),
				)
			);
			if ( $query->posts ) {
				return true;
			}
		}
		$comments = \get_comments(
			array(
				'status'     => 'any',
				'number'     => 1,
				'fields'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					array(
						'key'   => '_activitypub_remote_actor_id',
						'value' => $actor->ID,
					),
				),
			)
		);
		return ! empty( $comments ) || ! empty(
			\get_comments(
				array(
					'status'     => 'any',
					'number'     => 1,
					'fields'     => 'ids',
					'author_url' => $actor->guid,
				)
			)
		);
	}

	/**
	 * Collect URI references without searching JSON text or touching actor APIs.
	 *
	 * @return array<string, true> Referenced strings from stored outgoing activities.
	 */
	public static function get_outbox_uris() {
		$uris = array();
		$page = 1;
		do {
			$query = new \WP_Query(
				array(
					'post_type'      => Outbox::POST_TYPE,
					'post_status'    => \array_values( \get_post_stati() ),
					'posts_per_page' => 100,
					'paged'          => $page++,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			foreach ( $query->posts as $post ) {
				$data = \json_decode( $post->post_content, true );
				if ( \is_array( $data ) ) {
					\array_walk_recursive(
						$data,
						static function ( $value ) use ( &$uris ) {
							if ( \is_string( $value ) ) {
								$uris[ $value ] = true;
							}
						}
					);
				}
			}
			$count = \count( $query->posts );
		} while ( 100 === $count );
		return $uris;
	}
}
