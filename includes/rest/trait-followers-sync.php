<?php
/**
 * Followers_Sync trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Followers;

/**
 * Followers_Sync trait.
 *
 * Provides FEP-8fcf followers collection synchronization functionality for inbox controllers.
 *
 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md
 */
trait Followers_Sync {
	/**
	 * Process Collection-Synchronization header for followers if present (FEP-8fcf).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param array            $data    The activity data.
	 * @param int              $user_id The local user ID receiving the activity.
	 */
	private function process_followers_synchronization( $request, $data, $user_id ) {
		// Get the Collection-Synchronization header.
		$sync_header = $request->get_header( 'collection_synchronization' );

		if ( empty( $sync_header ) ) {
			return;
		}

		// Parse the header.
		$params = Followers::parse_sync_header( $sync_header );

		if ( false === $params ) {
			return;
		}

		// Validate the header parameters.
		$actor_url = isset( $data['actor'] ) ? $data['actor'] : null;

		if ( ! $actor_url ) {
			return;
		}

		if ( ! Followers::validate_sync_header_params( $params, $actor_url ) ) {
			return;
		}

		// Get our local authority.
		$our_authority = Followers::get_authority( \home_url() );

		if ( ! $our_authority ) {
			return;
		}

		// Compute our local digest for this actor's followers from our instance.
		$local_digest = Followers::compute_partial_digest( $user_id, $our_authority );

		// Compare digests.
		if ( $local_digest === $params['digest'] ) {
			// Digests match, no synchronization needed.
			return;
		}

		// Digests do not match, trigger reconciliation.

		/**
		 * Action triggered when Collection-Synchronization digest mismatch is detected.
		 *
		 * This allows for async processing of the reconciliation.
		 *
		 * @param int    $user_id    The local user ID.
		 * @param string $actor_url  The remote actor URL.
		 * @param array  $params     The parsed Collection-Synchronization header parameters.
		 */
		\do_action( 'activitypub_followers_sync_mismatch', $user_id, $actor_url, $params );
	}
}
