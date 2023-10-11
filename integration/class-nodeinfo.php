<?php
namespace Activitypub\Integration;

/**
 * Compatibility with the NodeInfo plugin
 *
 * @see https://wordpress.org/plugins/nodeinfo/
 */
class Nodeinfo {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'nodeinfo_data', array( self::class, 'add_nodeinfo_discovery' ), 10, 2 );
		\add_filter( 'nodeinfo2_data', array( self::class, 'add_nodeinfo2_discovery' ), 10 );
	}

	/**
	 * Extend NodeInfo data
	 *
	 * @param array  $nodeinfo NodeInfo data
	 * @param string           The NodeInfo Version
	 *
	 * @return array The extended array
	 */
	public static function add_nodeinfo_discovery( $nodeinfo, $version ) {
		if ( $version >= '2.0' ) {
			$nodeinfo['protocols'][] = 'activitypub';
		} else {
			$nodeinfo['protocols']['inbound'][]  = 'activitypub';
			$nodeinfo['protocols']['outbound'][] = 'activitypub';
		}

		return $nodeinfo;
	}

	/**
	 * Extend NodeInfo2 data
	 *
	 * @param  array $nodeinfo NodeInfo2 data
	 *
	 * @return array The extended array
	 */
	public static function add_nodeinfo2_discovery( $nodeinfo ) {
		$nodeinfo['protocols'][] = 'activitypub';

		return $nodeinfo;
	}
}
