<?php
namespace Activitypub\Integration;

use Activitypub\Rest\Webfinger as Webfinger_Rest;
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
		\add_filter( 'webfinger_user_data', array( self::class, 'add_user_discovery' ), 1, 3 );
		\add_filter( 'webfinger_data', array( self::class, 'add_pseudo_user_discovery' ), 1, 2 );
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

		if ( ! $user || is_wp_error( $user ) ) {
			return $array;
		}

		$array['subject'] = sprintf( 'acct:%s', $user->get_resource() );

		$array['aliases'][] = $user->get_url();
		$array['aliases'][] = $user->get_alternate_url();

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
		$user = Webfinger_Rest::get_profile( $resource );

		if ( ! $user || is_wp_error( $user ) ) {
			return $array;
		}

		return $user;
	}
}
