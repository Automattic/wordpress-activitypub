<?php

class Rest_Activitypub_Webfinger {
	/**
	 * Register routes
	 */
	public static function register_routes() {
		register_rest_route(
			'activitypub/1.0', '/webfinger', array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( 'Rest_Activitypub_Webfinger', 'webfinger' ),
					'args'     => self::request_parameters(),
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

		$matches = array();
		$matched = preg_match( '/^acct:([^@]+)@(.+)$/', $resource, $matches );

		if ( ! $matched ) {
			return new WP_Error( 'activitypub_unsupported_resource', __( 'Resouce is invalid', 'activitypub' ), array( 'status' => 400 ) );
		}

		$resource_identifier = $matches[1];
		$resource_host = $matches[2];

		if ( wp_parse_url( home_url( '/' ), PHP_URL_HOST ) !== $resource_host ) {
			return new WP_Error( 'activitypub_wrong_host', __( 'Resouce host does not match blog host', 'activitypub' ), array( 'status' => 404 ) );
		}

		$user = get_user_by( 'login', esc_sql( $resource_identifier ) );

		if ( ! $user ) {
			return new WP_Error( 'activitypub_user_not_found', __( 'User not found', 'activitypub' ), array( 'status' => 404 ) );
		}

		$json = array(
			'subject' => $resource,
			'aliases' => array(
				get_author_posts_url( $user->ID ),
			),
			'links' => array(
				array(
					'rel'  => 'self',
					'type' => 'application/activity+json',
					'href' => get_author_posts_url( $user->ID ),
				),
				array(
					'rel'  => 'http://webfinger.net/rel/profile-page',
					'type' => 'text/html',
					'href' => get_author_posts_url( $user->ID ),
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
			'pattern' => '^acct:([^@]+)@(.+)$',
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
			'href' => get_author_posts_url( $user->ID ),
		);

		return $array;
	}
}
