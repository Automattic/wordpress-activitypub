<?php

class Rest_Activitypub_Followers {
	/**
	 * Register routes
	 */
	public static function register_routes() {
		register_rest_route(
			'activitypub/1.0', '/users/(?P<id>\d+)/followers', array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( 'Rest_Activitypub_Followers', 'get' ),
					'args'     => self::request_parameters(),
				),
			)
		);
	}

	public static function get( $request ) {
		$author_id = $request->get_param( 'id' );
		$author    = get_user_by( 'ID', $author_id );

		if ( ! $author ) {
			return new WP_Error( 'rest_invalid_param', __( 'User not found', 'activitypub' ), array(
				'status' => 404, 'params' => array(
					'user_id' => __( 'User not found', 'activitypub' )
				)
			) );
		}

		$page = $request->get_param( 'page', 0 );

		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		do_action( 'activitypub_outbox_pre' );

		$json = new stdClass();

		$json->{'@context'} = get_activitypub_context();

		$followers = get_user_option( 'activitypub_followers', $author_id );

		if ( ! is_array( $followers ) ) {
			$followers = array();
		}

		$json->totlaItems = count( $followers );
		$json->orderedItems = $followers;

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
			'type' => 'integer',
		);

		$params['id'] = array(
			'required' => true,
			'type' => 'integer',
		);

		return $params;
	}
}
