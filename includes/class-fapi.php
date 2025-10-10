<?php
/**
 * FAPI integration class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * FAPI integration class.
 *
 * Handles FAPI-related integrations that aren't part of the REST API.
 */
class Fapi {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'nodeinfo_data', array( self::class, 'add_fapi_base_url' ) );
	}

	/**
	 * Add FAPI base URL to nodeinfo metadata.
	 *
	 * @param array $nodeinfo Current nodeinfo data.
	 * @return array Modified nodeinfo data with FAPI base URL.
	 */
	public static function add_fapi_base_url( $nodeinfo ) {
		if ( ! isset( $nodeinfo['metadata'] ) ) {
			$nodeinfo['metadata'] = array();
		}

		$nodeinfo['metadata']['faspBaseUrl'] = \rest_url( 'activitypub/v1/fapi' );

		return $nodeinfo;
	}
}
