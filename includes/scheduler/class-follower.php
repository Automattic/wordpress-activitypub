<?php
/**
 * Follower Scheduler.
 *
 * Handles async reconciliation when FEP-8fcf Collection-Synchronization
 * digest mismatches are detected.
 *
 * @package Activitypub
 */

namespace Activitypub\Scheduler;

use Activitypub\Collection\Followers;
use Activitypub\Http;

/**
 * Follower class.
 */
class Follower {
	/**
	 * Initialize the scheduler.
	 */
	public static function init() {
		\add_action( 'activitypub_followers_sync_mismatch', array( self::class, 'schedule_reconciliation' ), 10, 3 );
		\add_action( 'activitypub_followers_sync_reconcile', array( self::class, 'reconcile_followers' ), 10, 3 );
	}

	/**
	 * Schedule a reconciliation job.
	 *
	 * @param int    $user_id   The local user ID.
	 * @param string $actor_url The remote actor URL.
	 * @param array  $params    The Followers-Synchronization header parameters.
	 */
	public static function schedule_reconciliation( $user_id, $actor_url, $params ) {
		// Schedule async processing to avoid blocking the inbox.
		\wp_schedule_single_event(
			time() + 60, // Process in 1 minute.
			'activitypub_followers_sync_reconcile',
			array( $user_id, $actor_url, $params )
		);
	}

	/**
	 * Reconcile followers based on remote partial collection.
	 *
	 * @param int    $user_id   The local user ID.
	 * @param string $actor_url The remote actor URL.
	 * @param array  $params    The Collection-Synchronization header parameters.
	 */
	public static function reconcile_followers( $user_id, $actor_url, $params ) {
		if ( empty( $params['url'] ) ) {
			return;
		}

		// Fetch the authoritative partial followers collection.
		$response = Http::get( $params['url'], 300 ); // Cache for 5 minutes.

		if ( \is_wp_error( $response ) ) {
			return;
		}

		$body = \wp_remote_retrieve_body( $response );
		$data = \json_decode( $body, true );

		if ( empty( $data['orderedItems'] ) || ! \is_array( $data['orderedItems'] ) ) {
			return;
		}

		$remote_followers = $data['orderedItems'];

		// Get our authority.
		$our_authority = Http::get_authority( \home_url() );

		if ( ! $our_authority ) {
			return;
		}

		// Get local partial followers for comparison.
		$local_followers = Followers::get_partial_followers( $user_id, $our_authority );

		// Find followers to remove (in local but not in remote).
		$to_remove = array_diff( $local_followers, $remote_followers );

		// Find followers to potentially accept (in remote but not in local).
		$to_check = array_diff( $remote_followers, $local_followers );

		// Remove followers that shouldn't be there.
		foreach ( $to_remove as $follower_url ) {
			$follower = Followers::get_follower( $user_id, $follower_url );

			if ( $follower && ! \is_wp_error( $follower ) ) {
				Followers::remove( $follower->ID, $user_id );

				/**
				 * Action triggered when a follower is removed due to synchronization.
				 *
				 * @param int    $user_id      The local user ID.
				 * @param string $follower_url The follower URL that was removed.
				 * @param string $actor_url    The remote actor URL.
				 */
				\do_action( 'activitypub_followers_sync_follower_removed', $user_id, $follower_url, $actor_url );
			}
		}

		/*
		 * For followers in remote but not local, we could send Undo Follow.
		 * However, this requires careful consideration as the follow may be pending.
		 * For now, just log these for potential manual review.
		 */
		foreach ( $to_check as $follower_url ) {
			Followers::add_follower( $user_id, $follower_url );

			/**
			 * Action triggered when a follower exists remotely but not locally.
			 *
			 * This could indicate:
			 * - A pending follow request
			 * - A follow that was lost locally
			 * - An inconsistency that needs manual review
			 *
			 * @param int    $user_id      The local user ID.
			 * @param string $follower_url The follower URL.
			 * @param string $actor_url    The remote actor URL.
			 */
			\do_action( 'activitypub_followers_sync_follower_mismatch', $user_id, $follower_url, $actor_url );
		}

		/**
		 * Action triggered after reconciliation is complete.
		 *
		 * @param int    $user_id         The local user ID.
		 * @param string $actor_url       The remote actor URL.
		 * @param array  $to_remove       Followers that were removed.
		 * @param array  $to_check        Followers that need checking.
		 */
		\do_action( 'activitypub_followers_sync_reconciled', $user_id, $actor_url, $to_remove, $to_check );
	}
}
