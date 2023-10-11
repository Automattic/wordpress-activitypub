<?php
namespace Activitypub\Integration;

use Activitypub\Collection\Users as User_Collection;

/**
 * Compatibility with the WebFinger plugin
 *
 * @see https://wordpress.org/plugins/webfinger/
 */
class Webfinger {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'webfinger_user_data', array( self::class, 'add_user_discovery' ), 10, 3 );
		\add_filter( 'webfinger_data', array( self::class, 'add_pseudo_user_discovery' ), 99, 2 );
	}

	/**
	 * Add WebFinger discovery links
	 *
	 * @param array   $array    the jrd array
	 * @param string  $resource the WebFinger resource
	 * @param WP_User $user     the WordPress user
	 *
	 * @return array the jrd array
	 */
	public static function add_user_discovery( $array, $resource, $user ) {
		$user = User_Collection::get_by_id( $user->ID );

		$array['links'][] = array(
			'rel'  => 'self',
			'type' => 'application/activity+json',
			'href' => $user->get_url(),
		);

		return $array;
	}

	/**
	 * Add WebFinger discovery links
	 *
	 * @param array   $array    the jrd array
	 * @param string  $resource the WebFinger resource
	 * @param WP_User $user     the WordPress user
	 *
	 * @return array the jrd array
	 */
	public static function add_pseudo_user_discovery( $array, $resource ) {
		if ( $array ) {
			return $array;
		}

		return self::get_profile( $resource );
	}
}
