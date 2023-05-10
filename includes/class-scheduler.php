<?php

namespace Activitypub;

use Activitypub\Model\Post;
use Activitypub\Collection\Followers;

/**
 * ActivityPub Scheduler Class
 *
 * @author Matthias Pfefferle
 */
class Scheduler {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'transition_post_status', array( self::class, 'schedule_post_activity' ), 33, 3 );

		\add_action( 'activitypub_update_followers', array( self::class, 'update_followers' ) );
		\add_action( 'activitypub_cleanup_followers', array( self::class, 'cleanup_followers' ) );
	}

	public static function register_schedules() {
		if ( ! \wp_next_scheduled( 'activitypub_update_followers' ) ) {
			\wp_schedule_event( time(), 'hourly', 'activitypub_update_followers' );
		}

		if ( ! \wp_next_scheduled( 'activitypub_cleanup_followers' ) ) {
			\wp_schedule_event( time(), 'daily', 'activitypub_cleanup_followers' );
		}
	}

	public static function deregister_schedules() {
		wp_unschedule_hook( 'activitypub_update_followers' );
		wp_unschedule_hook( 'activitypub_cleanup_followers' );
	}


	/**
	 * Schedule Activities.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function schedule_post_activity( $new_status, $old_status, $post ) {
		// Do not send activities if post is password protected.
		if ( \post_password_required( $post ) ) {
			return;
		}

		// Check if post-type supports ActivityPub.
		$post_types = \get_post_types_by_support( 'activitypub' );
		if ( ! \in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$activitypub_post = new Post( $post );

		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			\wp_schedule_single_event(
				\time(),
				'activitypub_send_create_activity',
				array( $activitypub_post )
			);
		} elseif ( 'publish' === $new_status ) {
			\wp_schedule_single_event(
				\time(),
				'activitypub_send_update_activity',
				array( $activitypub_post )
			);
		} elseif ( 'trash' === $new_status ) {
			\wp_schedule_single_event(
				\time(),
				'activitypub_send_delete_activity',
				array( $activitypub_post )
			);
		}
	}

	/**
	 * Update followers
	 *
	 * @return void
	 */
	public static function update_followers() {
		$followers = Followers::get_outdated_followers( ACTIVITYPUB_OBJECT );

		foreach ( $followers as $follower ) {
			$meta = get_remote_metadata_by_actor( $follower->get_actor() );

			if ( empty( $meta ) || ! is_array( $meta ) || is_wp_error( $meta ) ) {
				$follower->set_error( $meta );
			} else {
				$follower->from_meta( $meta );
			}

			$follower->update();
		}
	}

	/**
	 * Cleanup followers
	 *
	 * @return void
	 */
	public static function cleanup_followers() {
		$followers = Followers::get_faulty_followers( ACTIVITYPUB_OBJECT );

		foreach ( $followers as $follower ) {
			$meta = get_remote_metadata_by_actor( $follower->get_actor() );

			if ( empty( $meta ) || ! is_array( $meta ) || is_wp_error( $meta ) ) {
				if ( 5 >= $follower->count_errors() ) {
					$follower->delete();
				} else {
					$follower->set_error( $meta );
					$follower->update();
				}
			} else {
				$follower->reset_errors();
			}
		}
	}
}
