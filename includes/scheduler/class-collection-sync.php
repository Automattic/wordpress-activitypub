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
			time() + MINUTE_IN_SECONDS,
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
		$data = Http::get_remote_object( $params['url'], 5 * MINUTE_IN_SECONDS );

		if ( \is_wp_error( $data ) || ! isset( $data['orderedItems'] ) || ! \is_array( $data['orderedItems'] ) ) {
			return;
		}

		$remote_followers = $data['orderedItems'];

		// Get our authority.
		$home_authority = get_url_authority( \home_url() );

		$accepted = Following::get_by_authority( $user_id, $home_authority );
		foreach ( $accepted as $following ) {
			$key = array_search( $following->guid, $remote_followers, true );
			if ( false === $key ) {
				Following::reject( $following, $user_id );
			} else {
				unset( $remote_followers[ $key ] );
			}
		}

		$remote_followers = array_values( $remote_followers ); // Reindex.

		$pending = Following::get_by_authority( $user_id, $home_authority, Following::PENDING_META_KEY );
		foreach ( $pending as $following ) {
			$key = array_search( $following->guid, $remote_followers, true );
			if ( false === $key ) {
				Following::reject( $following, $user_id );
			} else {
				Following::accept( $following, $user_id );
				unset( $remote_followers[ $key ] );
			}
		}

		/**
		 * Action triggered after reconciliation is complete.
		 *
		 * @param int    $user_id   The local user ID that triggered the reconciliation.
		 * @param string $actor_url The remote actor URL.
		 */
		\do_action( 'activitypub_followers_sync_reconciled', $user_id, $actor_url );
	}
}
