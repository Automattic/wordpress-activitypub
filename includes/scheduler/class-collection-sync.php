<?php
/**
 * Collection Sync Scheduler.
 *
 * Handles async reconciliation when FEP-8fcf Collection-Synchronization
 * digest mismatches are detected.
 *
 * @package Activitypub
 */

namespace Activitypub\Scheduler;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Following;
use Activitypub\Http;

use function Activitypub\get_url_authority;

/**
 * Collection_Sync class.
 */
class Collection_Sync {
	/**
	 * Initialize the scheduler.
	 */
	public static function init() {
		\add_action( 'activitypub_collection_sync', array( self::class, 'schedule_reconciliation' ), 10, 4 );
		\add_action( 'activitypub_followers_sync_reconcile', array( self::class, 'reconcile_followers' ), 10, 3 );
	}

	/**
	 * Schedule a reconciliation job.
	 *
	 * @param string $type      The collection type (e.g., 'followers').
	 * @param int    $user_id   The local user ID.
	 * @param string $actor_url The remote actor URL.
	 * @param array  $params    The Collection-Synchronization header parameters.
	 */
	public static function schedule_reconciliation( $type, $user_id, $actor_url, $params ) {
		// Schedule async processing to avoid blocking the inbox.
		\wp_schedule_single_event(
			time() + 60, // Process in 1 minute.
			"activitypub_{$type}_sync_reconcile",
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
		$our_authority = get_url_authority( \home_url() );

		if ( ! $our_authority ) {
			return;
		}

		$remote_followers = self::filter_actor_list_by_authority( $remote_followers, $our_authority );
		sort( $remote_followers );

		$snapshot = Following::get_local_followers_snapshot( $actor_url );

		if ( \is_wp_error( $snapshot ) ) {
			return;
		}

		$accepted_followers = self::filter_actor_map_by_authority( $snapshot['followers'], $our_authority );
		$pending_followers  = self::filter_actor_map_by_authority( $snapshot['pending'], $our_authority );

		$remote_followers = array_values( array_unique( $remote_followers ) );
		$local_followers  = array_keys( $accepted_followers );

		$to_remove         = array_diff( $local_followers, $remote_followers );
		$pending_to_accept = array_intersect( $remote_followers, array_keys( $pending_followers ) );
		$remote_unknown    = array_diff( $remote_followers, $local_followers, array_keys( $pending_followers ) );

		foreach ( $to_remove as $actor_uri ) {
			$user_to_remove = $accepted_followers[ $actor_uri ];
			Following::unfollow( $snapshot['remote_post'], $user_to_remove );

			/**
			 * Action triggered when a follow is removed due to synchronization.
			 *
			 * @param int    $user_id      The local user ID whose follow was undone.
			 * @param string $actor_uri    The local actor URI.
			 * @param string $remote_actor The remote actor URL.
			 */
			\do_action( 'activitypub_followers_sync_follower_removed', $user_to_remove, $actor_uri, $actor_url );
		}

		foreach ( $pending_to_accept as $actor_uri ) {
			$user_to_accept = $pending_followers[ $actor_uri ];
			Following::accept( $snapshot['remote_post'], $user_to_accept );

			/**
			 * Action triggered when a pending follow is auto-accepted during synchronization.
			 *
			 * @param int    $user_id      The local user ID whose follow was accepted.
			 * @param string $actor_uri    The local actor URI.
			 * @param string $remote_actor The remote actor URL.
			 */
			\do_action( 'activitypub_followers_sync_follow_request_accepted', $user_to_accept, $actor_uri, $actor_url );
		}

		foreach ( $remote_unknown as $actor_uri ) {
			$local_user_id = Actors::get_id_by_resource( $actor_uri );

			if ( \is_wp_error( $local_user_id ) ) {
				continue;
			}

			Following::unfollow( $snapshot['remote_post'], $local_user_id );

			/**
			 * Action triggered when an unexpected follow entry is reconciled with an Undo.
			 *
			 * @param int    $user_id      The local user ID whose unexpected follow was undone.
			 * @param string $actor_uri    The local actor URI.
			 * @param string $remote_actor The remote actor URL.
			 */
			\do_action( 'activitypub_followers_sync_follower_mismatch', $local_user_id, $actor_uri, $actor_url );
		}

		/**
		 * Action triggered after reconciliation is complete.
		 *
		 * @param int    $user_id         The local user ID that triggered the reconciliation.
		 * @param string $actor_url       The remote actor URL.
		 * @param array  $to_remove       Local actor URIs removed from the follow list.
		 * @param array  $remote_unknown  Local actor URIs that required Undo operations.
		 */
		\do_action( 'activitypub_followers_sync_reconciled', $user_id, $actor_url, $to_remove, $remote_unknown );
	}
}
