<?php
/**
 * NodeInfo integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use function Activitypub\get_active_users;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\get_total_users;

/**
 * Compatibility with the NodeInfo plugin.
 *
 * @see https://wordpress.org/plugins/nodeinfo/
 */
class Nodeinfo {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'nodeinfo_data', array( self::class, 'add_nodeinfo_data' ), 10, 2 );
		\add_filter( 'nodeinfo2_data', array( self::class, 'add_nodeinfo2_data' ) );

		\add_filter( 'wellknown_nodeinfo_data', array( self::class, 'add_wellknown_nodeinfo_data' ) );
	}

	/**
	 * Extend NodeInfo data.
	 *
	 * @param array  $nodeinfo NodeInfo data.
	 * @param string $version  The NodeInfo Version.
	 *
	 * @return array The extended array.
	 */
	public static function add_nodeinfo_data( $nodeinfo, $version ) {
		if ( $version >= '2.0' ) {
			$nodeinfo['protocols'][] = 'activitypub';
		} else {
			$nodeinfo['protocols']['inbound'][]  = 'activitypub';
			$nodeinfo['protocols']['outbound'][] = 'activitypub';
		}

		$nodeinfo['usage']['users'] = array(
			'total'          => get_total_users(),
			'activeMonth'    => get_active_users(),
			'activeHalfyear' => get_active_users( 6 ),
		);

		return $nodeinfo;
	}

	/**
	 * Extend NodeInfo2 data.
	 *
	 * @param  array $nodeinfo NodeInfo2 data.
	 *
	 * @return array The extended array.
	 */
	public static function add_nodeinfo2_data( $nodeinfo ) {
		$nodeinfo['protocols'][] = 'activitypub';

		$nodeinfo['usage']['users'] = array(
			'total'          => get_total_users(),
			'activeMonth'    => get_active_users(),
			'activeHalfyear' => get_active_users( 6 ),
		);

		return $nodeinfo;
	}

	/**
	 * Extend the well-known nodeinfo data.
	 *
	 * @param array $data The well-known nodeinfo data.
	 *
	 * @return array The extended array.
	 */
	public static function add_wellknown_nodeinfo_data( $data ) {
		$data['links'][] = array(
			'rel'  => 'https://www.w3.org/ns/activitystreams#Application',
			'href' => get_rest_url_by_path( 'application' ),
		);

		return $data;
	}
}
