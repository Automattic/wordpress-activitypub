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
		\add_filter( 'rest_post_dispatch', array( self::class, 'add_cors_headers' ), 10, 3 );
	}

	/**
	 * Callback function to authorize an api request.
	 *
	 * The function is meant to be used as part of permission callbacks for rest api endpoints.
	 *
	 * It verifies the signature of POST, PUT, PATCH, and DELETE requests, as well as GET requests in secure mode.
	 * You can use the filter 'activitypub_defer_signature_verification' to defer the signature verification.
	 * HEAD requests are always bypassed.
	 *
	 * On successful signature verification, the verified keyId is stored in the request
	 * as the 'activitypub_verified_keyid' attribute for use by endpoint callbacks.
	 *
	 * @see https://www.w3.org/wiki/SocialCG/ActivityPub/Primer/Authentication_Authorization#Authorized_fetch
	 * @see https://swicg.github.io/activitypub-http-signature/#authorized-fetch
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return bool|\WP_Error True if the request is authorized, WP_Error if not.
	 */
	public static function verify_signature( $request ) {
		if ( 'HEAD' === $request->get_method() ) {
			return true;
		}

		/**
		 * Filter to defer signature verification.
		 *
		 * Skip signature verification for debugging purposes or to reduce load for
		 * certain Activity-Types, like "Delete".
		 *
		 * @param bool             $defer   Whether to defer signature verification.
		 * @param \WP_REST_Request $request The request used to generate the response.
		 *
		 * @return bool Whether to defer signature verification.
		 */
		$defer = \apply_filters( 'activitypub_defer_signature_verification', false, $request );

		if ( $defer ) {
			return true;
		}

		// POST-Requests always have to be signed, GET-Requests only require a signature in secure mode.
		if ( 'GET' !== $request->get_method() || use_authorized_fetch() ) {
			$verified_keyid = Signature::verify_http_signature( $request );
			if ( \is_wp_error( $verified_keyid ) ) {
				return new \WP_Error(
					'activitypub_signature_verification',
					$verified_keyid->get_error_message(),
					array( 'status' => 401 )
				);
			}

			// Store the verified keyId in the request for use by endpoint callbacks.
			$request->set_param( 'activitypub_verified_keyid', $verified_keyid );
		}

		return true;
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

		// Exclude OAuth endpoints - they have their own error format per RFC 6749.
		if ( \str_starts_with( $route, '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth' ) ) {
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

	/**
	 * Add CORS headers to ActivityPub REST responses.
	 *
	 * @param \WP_REST_Response $response The REST response.
	 * @param \WP_REST_Server   $server   The REST server instance.
	 * @param \WP_REST_Request  $request  The request object.
	 *
	 * @return \WP_REST_Response The modified response.
	 */
	public static function add_cors_headers( $response, $server, $request ) {
		$route     = $request->get_route();
		$namespace = '/' . ACTIVITYPUB_REST_NAMESPACE;

		// Only add CORS to ActivityPub endpoints, except the interactive OAuth authorize endpoint.
		if ( ! \str_starts_with( $route, $namespace ) || \str_contains( $route, $namespace . '/oauth/authorize' ) ) {
			return $response;
		}

		$origin = self::get_cors_origin();
		$response->header( 'Access-Control-Allow-Origin', $origin ? $origin : '*' );
		$response->header( 'Access-Control-Allow-Methods', 'GET, POST, OPTIONS' );
		$response->header( 'Access-Control-Allow-Headers', 'Accept, Content-Type, Authorization, Last-Event-ID' );

		if ( $origin ) {
			$response->header( 'Access-Control-Allow-Credentials', 'true' );
			$response->header( 'Vary', 'Origin' );
		}

		return $response;
	}

	/**
	 * Send CORS headers directly via header().
	 *
	 * Use this for endpoints that bypass the REST response flow
	 * (e.g. SSE streams that call exit() instead of returning a WP_REST_Response).
	 *
	 * @since unreleased
	 */
	public static function send_cors_headers() {
		$origin = self::get_cors_origin();

		\header( 'Access-Control-Allow-Origin: ' . ( $origin ? $origin : '*' ) );
		\header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
		\header( 'Access-Control-Allow-Headers: Accept, Content-Type, Authorization, Last-Event-ID' );

		if ( $origin ) {
			\header( 'Access-Control-Allow-Credentials: true' );
			\header( 'Vary: Origin' );
		}
	}

	/**
	 * Get the CORS origin from the request.
	 *
	 * Reflects the request Origin instead of using a wildcard to avoid
	 * leaking private data to arbitrary origins on authenticated endpoints.
	 *
	 * @since unreleased
	 *
	 * @return string The origin or empty string.
	 */
	private static function get_cors_origin() {
		return isset( $_SERVER['HTTP_ORIGIN'] ) ? \esc_url_raw( \wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
	}
}
