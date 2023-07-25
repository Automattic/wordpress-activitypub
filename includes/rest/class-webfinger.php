<?php
namespace Activitypub\Rest;

use WP_Error;
use WP_REST_Response;
use Activitypub\Collection\Users as User_Collection;

/**
 * ActivityPub WebFinger REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://webfinger.net/
 */
class Webfinger {
	/**
	 * Initialize the class, registering WordPress hooks.
	 *
	 * @return void
	 */
	public static function init() {
		\add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		\add_filter( 'webfinger_user_data', array( self::class, 'add_user_discovery' ), 10, 3 );
		\add_filter( 'webfinger_data', array( self::class, 'add_pseudo_user_discovery' ), 99, 2 );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/webfinger',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'webfinger' ),
					'args'                => self::request_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * WebFinger endpoint.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function webfinger( $request ) {
		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_rest_webfinger_pre' );

		$resource = $request->get_param( 'resource' );
		$response = self::get_profile( $resource );

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function request_parameters() {
		$params = array();

		$params['resource'] = array(
			'required' => true,
			'type' => 'string',
			'pattern' => '^acct:(.+)@(.+)$',
		);

		return $params;
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

	/**
	 * Get the WebFinger profile.
	 *
	 * @param string $resource the WebFinger resource.
	 *
	 * @return array the WebFinger profile.
	 */
	public static function get_profile( $resource ) {
		$user = User_Collection::get_by_resource( $resource );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$aliases = array(
			$user->get_url(),
		);

		$profile = array(
			'subject' => $resource,
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
			),
		);

		return $profile;
	}
}
