<?php
/**
 * Server REST-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

/**
 * ActivityPub Server REST-Class.
 *
 * @author Django Doucet
 *
 * @see https://www.w3.org/TR/activitypub/#security-verification
 */
class Server {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'rest_request_before_callbacks', array( self::class, 'validate_requests' ), 9, 3 );
		\add_filter( 'rest_request_parameter_order', array( self::class, 'request_parameter_order' ), 10, 2 );

		\add_filter( 'rest_post_dispatch', array( self::class, 'filter_output' ), 10, 3 );
	}

	/**
	 * Callback function to validate incoming ActivityPub requests
	 *
	 * @param \WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed $response Result to send to the client.
	 *                                                                      Usually a WP_REST_Response or WP_Error.
	 * @param array                                               $handler  Route handler used for the request.
	 * @param \WP_REST_Request                                    $request  Request used to generate the response.
	 *
	 * @return mixed|\WP_Error The response, error, or modified response.
	 */
	public static function validate_requests( $response, $handler, $request ) {
		if ( 'HEAD' === $request->get_method() ) {
			return $response;
		}

		$route = $request->get_route();

		if (
			\is_wp_error( $response ) ||
			! \str_starts_with( $route, '/' . ACTIVITYPUB_REST_NAMESPACE )
		) {
			return $response;
		}

		$params = $request->get_json_params();

		// Type is required for ActivityPub requests, so it fail later in the process.
		if ( ! isset( $params['type'] ) ) {
			return $response;
		}

		if (
			ACTIVITYPUB_DISABLE_INCOMING_INTERACTIONS &&
			in_array( $params['type'], array( 'Create', 'Like', 'Announce' ), true )
		) {
			return new \WP_Error(
				'activitypub_server_does_not_accept_incoming_interactions',
				\__( 'This server does not accept incoming interactions.', 'activitypub' ),
				// We have to use a 2XX status code here, because otherwise the response will be
				// treated as an error and Mastodon might block this WordPress instance.
				array( 'status' => 202 )
			);
		}

		return $response;
	}

	/**
	 * Modify the parameter priority order for a REST API request.
	 *
	 * @param string[]         $order   Array of types to check, in order of priority.
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return string[] The modified order of types to check.
	 */
	public static function request_parameter_order( $order, $request ) {
		$route = $request->get_route();

		// Check if it is an activitypub request and exclude webfinger and nodeinfo endpoints.
		if ( ! \str_starts_with( $route, '/' . ACTIVITYPUB_REST_NAMESPACE ) ) {
			return $order;
		}

		$method = $request->get_method();

		if ( \WP_REST_Server::CREATABLE !== $method ) {
			return $order;
		}

		return array(
			'JSON',
			'POST',
			'URL',
			'defaults',
		);
	}

	/**
	 * Filters the REST API response to properly handle the ActivityPub error formatting.
	 *
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/c180/fep-c180.md
	 *
	 * @param \WP_HTTP_Response $response Result to send to the client. Usually a `WP_REST_Response`.
	 * @param \WP_REST_Server   $server   Server instance.
	 * @param \WP_REST_Request  $request  Request used to generate the response.
	 *
	 * @return \WP_HTTP_Response The filtered response.
	 */
	public static function filter_output( $response, $server, $request ) {
		$route = $request->get_route();

		// Check if it is an activitypub request and exclude webfinger and nodeinfo endpoints.
		if ( ! \str_starts_with( $route, '/' . ACTIVITYPUB_REST_NAMESPACE ) ) {
			return $response;
		}

		// Only alter responses that return an error status code.
		if ( $response->get_status() < 400 ) {
			return $response;
		}

		$data = $response->get_data();

		// Ensure that `$data` was already converted to a response.
		if ( \is_wp_error( $data ) ) {
			$response = \rest_convert_error_to_response( $data );
			$data     = $response->get_data();
		}

		$error = array(
			'type'     => 'about:blank',
			'title'    => $data['code'] ?? '',
			'detail'   => $data['message'] ?? '',
			'status'   => $response->get_status(),

			/*
			 * Provides the unstructured error data.
			 *
			 * @see https://nodeinfo.diaspora.software/schema.html#metadata.
			 */
			'metadata' => $data,
		);

		$response->set_data( $error );

		return $response;
	}
}
