<?php
namespace Activitypub\Integration;

use Activitypub\Rest\Webfinger as Webfinger_Rest;
use Activitypub\Collection\Users as User_Collection;

use function Activitypub\get_rest_url_by_path;

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
	 * @param array   $jrd    the jrd array
	 * @param string  $entity the WebFinger resource
	 * @param WP_User $user   the WordPress user
	 *
	 * @return array the jrd array
	 */
	public static function add_user_discovery( $jrd, $entity, $user ) {
		$user = User_Collection::get_by_id( $user->ID );

		if ( ! $user || is_wp_error( $user ) ) {
			return $jrd;
		}

		$jrd['subject'] = sprintf( 'acct:%s', $user->get_webfinger() );

		$jrd['aliases'][] = $user->get_url();
		$jrd['aliases'][] = $user->get_alternate_url();

		$jrd['links'][] = array(
			'rel'  => 'self',
			'type' => 'application/activity+json',
			'href' => $user->get_url(),
		);

		$jrd['links'][] = array(
			'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
			'template' => get_rest_url_by_path( 'interactions?uri={uri}' ),
		);

		return $jrd;
	}

	/**
	 * Add WebFinger discovery links
	 *
	 * @param array   $jrd the jrd array
	 * @param string  $uri the WebFinger resource
	 *
	 * @return array the jrd array
	 */
	public static function add_pseudo_user_discovery( $jrd, $uri ) {
		$user = User_Collection::get_by_resource( $uri );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		$aliases = array(
			$user->get_url(),
			$user->get_alternate_url(),
		);

		$aliases = array_unique( $aliases );

		$profile = array(
			'subject' => sprintf( 'acct:%s', $user->get_webfinger() ),
			'aliases' => array_values( array_unique( $aliases ) ),
			'links'   => array(
				array(
					'rel'  => 'self',
					'type' => 'application/activity+json',
					'href' => $user->get_url(),
				),
				array(
					'rel'  => 'http://webfinger.net/rel/profile-page',
					'type' => 'text/html',
					'href' => $user->get_url(),
				),
				array(
					'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
					'template' => get_rest_url_by_path( 'interactions?uri={uri}' ),
				),
			),
		);

		if ( 'Person' !== $user->get_type() ) {
			$profile['links'][0]['properties'] = array(
				'https://www.w3.org/ns/activitystreams#type' => $user->get_type(),
			);
		}

		return $profile;
	}
}
