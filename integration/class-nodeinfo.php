<?php
/**
 * NodeInfo integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Webfinger;

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
		$nodeinfo = wp_parse_args(
			$nodeinfo,
			array(
				'version'   => $version,
				'software'  => array(),
				'usage'     => array(
					'users' => array(
						'total'          => 0,
						'activeMonth'    => 0,
						'activeHalfyear' => 0,
					),
				),
				'protocols' => array(),
				'services'  => array(
					'inbound'  => array(),
					'outbound' => array(),
				),
				'metadata'  => array(),
			)
		);

		if ( \version_compare( $version, '2.1', '>=' ) ) {
			$nodeinfo['software']['homepage']   = 'https://wordpress.org/plugins/activitypub/';
			$nodeinfo['software']['repository'] = 'https://github.com/Automattic/wordpress-activitypub';
		}

		$nodeinfo['protocols'][] = 'activitypub';

		$nodeinfo['usage']['users'] = array(
			'total'          => get_total_users(),
			'activeMonth'    => get_active_users(),
			'activeHalfyear' => get_active_users( 6 ),
		);

		$nodeinfo['metadata']['federation']    = array( 'enabled' => true );
		$nodeinfo['metadata']['staffAccounts'] = self::get_staff();

		$nodeinfo['services']['inbound'][]  = 'activitypub';
		$nodeinfo['services']['outbound'][] = 'activitypub';

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

	/**
	 * Get all staff accounts (admin users with the "activitypub" capability) and return them in WebFinger resource format.
	 *
	 * @return array List of staff accounts in WebFinger resource format.
	 */
	private static function get_staff() {
		// Get all admin users with the cap activitypub.
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'cap'     => 'activitypub',
				'fields'  => array( 'ID' ),
			)
		);

		return array_map(
			function ( $user ) {
				return Webfinger::get_user_resource( $user->ID );
			},
			$admins
		);
	}
}
