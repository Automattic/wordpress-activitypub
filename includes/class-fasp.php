<?php
/**
 * FASP integration class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * FASP integration class.
 *
 * Handles FASP-related integrations that aren't part of the REST API.
 */
class Fasp {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'nodeinfo_data', array( self::class, 'add_fasp_base_url' ) );
	}

	/**
	 * Add FASP base URL to nodeinfo metadata.
	 *
	 * @param array $nodeinfo Current nodeinfo data.
	 * @return array Modified nodeinfo data with FASP base URL.
	 */
	public static function add_fasp_base_url( $nodeinfo ) {
		if ( ! isset( $nodeinfo['metadata'] ) ) {
			$nodeinfo['metadata'] = array();
		}

		$nodeinfo['metadata']['faspBaseUrl'] = \rest_url( 'activitypub/1.0/fasp' );

		return $nodeinfo;
	}
}
