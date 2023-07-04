<?php
namespace Activitypub\Rest;

use WP_REST_Server;
use WP_REST_Request;
use Activitypub\Webfinger;
use Activitypub\Collection\Users as User_Collection;

/**
 * ActivityPub OStatus REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/community/ostatus/
 */
class Users {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}
	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/users/(?P<user_id>\d+)/remote-follow',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( '\Activitypub\Rest\Users', 'get' ),
					'args'                => array(
						'resource' => array(
							'required'          => true,
							//'sanitize_callback' => '',
						),
					),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	public static function get( WP_REST_Request $request ) {
		$resource = $request->get_param( 'resource' );
		$user_id = $request->get_param( 'user_id' );

		$template = WebFinger::get_remote_follow_endpoint( $resource );

		$resource = Webfinger::get_user_resource( $user_id );

		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$url = str_replace( '{uri}', $resource, $template );

		return $url;
	}
}
