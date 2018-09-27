<?php
/**
 * ActivityPub Outbox Class
 *
 * @author Matthias Pfefferle
 */
class Activitypub_Inbox {
	/**
	 * Register routes
	 */
	public static function register_routes() {
		register_rest_route(
			'activitypub/1.0', '/inbox', array(
				array(
					'methods'  => WP_REST_Server::EDITABLE,
					'callback' => array( 'Activitypub_Inbox', 'shared_edit' ),
				),
			)
		);

		register_rest_route(
			'activitypub/1.0', '/users/(?P<id>\d+)/inbox', array(
				array(
					'methods'  => WP_REST_Server::EDITABLE,
					'callback' => array( 'Activitypub_Inbox', 'user_edit' ),
					'args'     => self::request_parameters(),
				),
			)
		);
	}

	public static function user_edit( $request ) {
		$author_id = $request->get_param( 'id' );
		$author    = get_user_by( 'ID', $author_id );

		$data = json_decode( $request->get_body(), true );

		if ( ! is_array( $data ) || ! array_key_exists( 'type', $data ) ) {
			return new WP_Error( 'rest_invalid_data', __( 'Invalid payload', 'activitypub' ), array( 'status' => 422 ) );
		}

		return new WP_REST_Response( $data );
	}

	public static function shared_edit( $request ) {
		// Create the response object
		return new WP_Error( 'rest_not_implemented', __( 'This method is not yet implemented', 'activitypub' ), array( 'status' => 501 ) );
	}

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
