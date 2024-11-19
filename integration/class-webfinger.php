<?php
/**
 * WebFinger integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Collection\Actors as User_Collection;

use function Activitypub\get_rest_url_by_path;

/**
 * Compatibility with the WebFinger plugin
 *
 * @see https://wordpress.org/plugins/webfinger/
 */
class Webfinger {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'webfinger_user_data', array( self::class, 'add_user_discovery' ), 1, 3 );
		\add_filter( 'webfinger_data', array( self::class, 'add_pseudo_user_discovery' ), 1, 2 );
	}

	/**
	 * Add WebFinger discovery links.
	 *
	 * @param array    $jrd  The jrd array.
	 * @param string   $uri  The WebFinger resource.
	 * @param \WP_User $user The WordPress user.
	 *
	 * @return array The jrd array.
	 */
	public static function add_user_discovery( $jrd, $uri, $user ) {
		$user = User_Collection::get_by_id( $user->ID );

		if ( ! $user || is_wp_error( $user ) ) {
			return $jrd;
		}

		$jrd['subject'] = sprintf( 'acct:%s', $user->get_webfinger() );

		$jrd['aliases'][] = $user->get_id();
		$jrd['aliases'][] = $user->get_url();
		$jrd['aliases'][] = $user->get_alternate_url();
		$jrd['aliases']   = array_unique( $jrd['aliases'] );
		$jrd['aliases']   = array_values( $jrd['aliases'] );

		$jrd['links'][] = array(
			'rel'  => 'self',
			'type' => 'application/activity+json',
			'href' => $user->get_id(),
		);

		$jrd['links'][] = array(
			'rel'      => 'http://ostatus.org/schema/1.0/subscribe',
			'template' => get_rest_url_by_path( 'interactions?uri={uri}' ),
		);

		return $jrd;
	}

	/**
	 * Add WebFinger discovery links.
	 *
	 * @param array  $jrd The jrd array.
	 * @param string $uri The WebFinger resource.
	 *
	 * @return array|\WP_Error The jrd array or WP_Error.
	 */
	public static function add_pseudo_user_discovery( $jrd, $uri ) {
		$user = User_Collection::get_by_resource( $uri );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		$aliases = array(
			$user->get_id(),
			$user->get_url(),
			$user->get_alternate_url(),
		);

		$aliases = array_unique( $aliases );
		$aliases = array_values( $aliases );

		$profile = array(
			'subject' => sprintf( 'acct:%s', $user->get_webfinger() ),
			'aliases' => $aliases,
			'links'   => array(
				array(
					'rel'  => 'self',
					'type' => 'application/activity+json',
					'href' => $user->get_id(),
				),
				array(
					'rel'  => 'http://webfinger.net/rel/profile-page',
					'type' => 'text/html',
					'href' => $user->get_id(),
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
