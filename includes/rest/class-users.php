<?php
namespace Activitypub\Rest;

use WP_Error;
use WP_REST_Server;
use WP_REST_Response;
use Activitypub\User_Factory;

use function Activitypub\is_activitypub_request;

/**
 * ActivityPub Followers REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#followers
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
			'/users/(?P<user_id>[\w\-\.]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get' ),
					'args'                => self::request_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Handle GET request
	 *
	 * @param  WP_REST_Request   $request
	 *
	 * @return WP_REST_Response
	 */
	public static function get( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = User_Factory::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// redirect to canonical URL if it is not an ActivityPub request
		if ( ! is_activitypub_request() ) {
			header( 'Location: ' . $user->get_canonical_url() );
			exit;
		}

		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_outbox_pre' );

		$json = $user->to_array();

		$response = new WP_REST_Response( $json, 200 );
		$response->header( 'Content-Type', 'application/activity+json' );

		return $response;
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function request_parameters() {
		$params = array();

		$params['page'] = array(
			'type' => 'string',
		);

		$params['user_id'] = array(
			'required' => true,
			'type'     => 'string',
		);

		return $params;
	}
}
