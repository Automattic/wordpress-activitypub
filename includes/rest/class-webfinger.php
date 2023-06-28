<?php
namespace Activitypub\Rest;

use WP_Error;
use WP_REST_Response;
use Activitypub\User_Factory;

/**
 * ActivityPub WebFinger REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://webfinger.net/
 */
class Webfinger {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		\add_action( 'webfinger_user_data', array( self::class, 'add_webfinger_discovery' ), 10, 3 );
	}

	/**
	 * Register routes
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
	 * Render JRD file
	 *
	 * @param  WP_REST_Request   $request
	 * @return WP_REST_Response
	 */
	public static function webfinger( $request ) {
		$resource = $request->get_param( 'resource' );
		$user     = User_Factory::get_by_resource( $resource );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$aliases = array(
			$user->get_url(),
			$user->get_at_url(),
		);

		$json = array(
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

		return new WP_REST_Response( $json, 200 );
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
	 */
	public static function add_webfinger_discovery( $array, $resource, $user ) {
		$array['links'][] = array(
			'rel'  => 'self',
			'type' => 'application/activity+json',
			'href' => \get_author_posts_url( $user->ID ),
		);

		return $array;
	}
}
