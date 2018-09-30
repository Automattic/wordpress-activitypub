<?php
/**
 * ActivityPub Inbox Class
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
					'callback' => array( 'Activitypub_Inbox', 'shared_inbox' ),
				),
			)
		);

		register_rest_route(
			'activitypub/1.0', '/users/(?P<id>\d+)/inbox', array(
				array(
					'methods'  => WP_REST_Server::EDITABLE,
					'callback' => array( 'Activitypub_Inbox', 'user_inbox' ),
					'args'     => self::request_parameters(),
				),
			)
		);
	}

	public static function user_inbox( $request ) {
		$author_id = $request->get_param( 'id' );
		$author    = get_user_by( 'ID', $author_id );

		$data = json_decode( $request->get_body(), true );

		do_action( 'activitypub_inbox', $data );

		if ( ! is_array( $data ) || ! array_key_exists( 'type', $data ) ) {
			return new WP_Error( 'rest_invalid_data', __( 'Invalid payload', 'activitypub' ), array( 'status' => 422 ) );
		}

		return new WP_REST_Response();
	}

	/**
	 * The shared inbox
	 *
	 * @param  [type] $request [description]
	 *
	 * @return WP_Error not yet implemented
	 */
	public static function shared_inbox( $request ) {
		// Create the response object
		return new WP_Error( 'rest_not_implemented', __( 'This method is not yet implemented', 'activitypub' ), array( 'status' => 501 ) );
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
