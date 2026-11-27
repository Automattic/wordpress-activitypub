<?php
/**
 * Request schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

use function Activitypub\is_same_host;

/**
 * The schema of a request for consent, such as QuoteRequest (FEP-044f) or FeatureRequest (FEP-7aa9).
 *
 * @since unreleased
 */
abstract class Request extends Activity {
	/**
	 * A request names the requester's own object as its instrument, so it is required and lives on
	 * the actor's host. Otherwise a remote server could bind a third-party reference to a local post or actor.
	 *
	 * @return true|\WP_Error True if valid, an error if not.
	 */
	protected function check() {
		$valid = parent::check();
		$data  = $this->data;

		if ( true !== $valid ) {
			return $valid;
		}

		if ( ! isset( $data['instrument'] ) ) {
			return new \WP_Error( 'activitypub_instrument_missing', \__( 'A request needs an instrument.', 'activitypub' ), array( 'status' => 400 ) );
		}

		if ( ! is_same_host( $data['actor'], $data['instrument'] ) ) {
			return new \WP_Error( 'activitypub_instrument_host', \__( 'The instrument is not on the host of the actor.', 'activitypub' ), array( 'status' => 400 ) );
		}

		return true;
	}
}
