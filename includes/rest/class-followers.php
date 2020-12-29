<?php
namespace Activitypub\Rest;

/**
 * ActivityPub Followers REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#followers
 */
class Followers {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'rest_api_init', array( '\Activitypub\Rest\Followers', 'register_routes' ) );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			'activitypub/1.0', '/users/(?P<id>\d+)/followers', array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( '\Activitypub\Rest\Followers', 'get' ),
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
		$user_id = $request->get_param( 'id' );
		$user    = \get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			return new \WP_Error( 'rest_invalid_param', \__( 'User not found', 'activitypub' ), array(
				'status' => 404,
				'params' => array(
					'user_id' => \__( 'User not found', 'activitypub' ),
				),
			) );
		}

		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_outbox_pre' );

		$json = new \stdClass();

		$json->{'@context'} = \Activitypub\get_context();

		$json->partOf = \get_rest_url( null, "/activitypub/1.0/users/$user_id/followers" ); // phpcs:ignore
		$json->totalItems = \Activitypub\count_followers( $user_id ); // phpcs:ignore
		$json->orderedItems = \Activitypub\Peer\Followers::get_followers( $user_id ); // phpcs:ignore

		$json->first = $json->partOf; // phpcs:ignore

		$json->first = \get_rest_url( null, "/activitypub/1.0/users/$user_id/followers" );

		$response = new \WP_REST_Response( $json, 200 );
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
			'type' => 'integer',
		);

		$params['id'] = array(
			'required' => true,
			'type' => 'integer',
		);

		return $params;
	}
}
