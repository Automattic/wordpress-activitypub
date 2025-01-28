<?php
/**
 * Scheduler class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Scheduler\Post;
use Activitypub\Scheduler\Actor;
use Activitypub\Scheduler\Comment;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Followers;

/**
 * Scheduler class.
 *
 * @author Matthias Pfefferle
 */
class Scheduler {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		self::register_schedulers();

		// Follower Cleanups.
		\add_action( 'activitypub_update_followers', array( self::class, 'update_followers' ) );
		\add_action( 'activitypub_cleanup_followers', array( self::class, 'cleanup_followers' ) );

		\add_action( 'activitypub_reprocess_outbox', array( self::class, 'reprocess_outbox' ) );

		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'schedule_outbox_activity_for_federation' ) );
	}

	/**
	 * Register handlers.
	 */
	public static function register_schedulers() {
		Post::init();
		Actor::init();
		Comment::init();

		/**
		 * Register additional schedulers.
		 *
		 * @since 5.0.0
		 */
		do_action( 'activitypub_register_schedulers' );
	}

	/**
	 * Schedule all ActivityPub schedules.
	 */
	public static function register_schedules() {
		if ( ! \wp_next_scheduled( 'activitypub_update_followers' ) ) {
			\wp_schedule_event( time(), 'hourly', 'activitypub_update_followers' );
		}

		if ( ! \wp_next_scheduled( 'activitypub_cleanup_followers' ) ) {
			\wp_schedule_event( time(), 'daily', 'activitypub_cleanup_followers' );
		}

		if ( ! \wp_next_scheduled( 'activitypub_reprocess_outbox' ) ) {
			\wp_schedule_event( time(), 'hourly', 'activitypub_reprocess_outbox' );
		}
	}

	/**
	 * Un-schedule all ActivityPub schedules.
	 *
	 * @return void
	 */
	public static function deregister_schedules() {
		wp_unschedule_hook( 'activitypub_update_followers' );
		wp_unschedule_hook( 'activitypub_cleanup_followers' );
		wp_unschedule_hook( 'activitypub_reprocess_outbox' );
	}

	/**
	 * Update followers.
	 */
	public static function update_followers() {
		$number = 5;

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$number = 50;
		}

		/**
		 * Filter the number of followers to update.
		 *
		 * @param int $number The number of followers to update.
		 */
		$number    = apply_filters( 'activitypub_update_followers_number', $number );
		$followers = Followers::get_outdated_followers( $number );

		foreach ( $followers as $follower ) {
			$meta = get_remote_metadata_by_actor( $follower->get_id(), false );

			if ( empty( $meta ) || ! is_array( $meta ) || is_wp_error( $meta ) ) {
				Followers::add_error( $follower->get__id(), $meta );
			} else {
				$follower->from_array( $meta );
				$follower->update();
			}
		}
	}

	/**
	 * Cleanup followers.
	 */
	public static function cleanup_followers() {
		$number = 5;

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$number = 50;
		}

		/**
		 * Filter the number of followers to clean up.
		 *
		 * @param int $number The number of followers to clean up.
		 */
		$number    = apply_filters( 'activitypub_update_followers_number', $number );
		$followers = Followers::get_faulty_followers( $number );

		foreach ( $followers as $follower ) {
			$meta = get_remote_metadata_by_actor( $follower->get_url(), false );

			if ( is_tombstone( $meta ) ) {
				$follower->delete();
			} elseif ( empty( $meta ) || ! is_array( $meta ) || is_wp_error( $meta ) ) {
				if ( $follower->count_errors() >= 5 ) {
					$follower->delete();
					\wp_schedule_single_event(
						\time(),
						'activitypub_delete_actor_interactions',
						array( $follower->get_id() )
					);
				} else {
					Followers::add_error( $follower->get__id(), $meta );
				}
			} else {
				$follower->reset_errors();
			}
		}
	}

	/**
	 * Schedule the outbox item for federation.
	 *
	 * @param int $id The ID of the outbox item.
	 */
	public static function schedule_outbox_activity_for_federation( $id ) {
		$hook = 'activitypub_process_outbox';
		$args = array( $id );

		if ( false === wp_next_scheduled( $hook, $args ) ) {
			\wp_schedule_single_event(
				\time() + 10,
				$hook,
				$args
			);
		}
	}

	/**
	 * Reprocess the outbox.
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
			self::schedule_outbox_activity_for_federation( $id );
		}
	}
}
