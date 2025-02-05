<?php
/**
 * Server REST-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use WP_Error;
use WP_REST_Server;
use WP_REST_Response;
use Activitypub\Signature;

use function Activitypub\use_authorized_fetch;

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
		self::add_hooks();
	}

	/**
	 * Add sever hooks.
	 */
	public static function add_hooks() {
		\add_filter( 'rest_request_before_callbacks', array( self::class, 'validate_requests' ), 9, 3 );
		\add_filter( 'rest_request_parameter_order', array( self::class, 'request_parameter_order' ), 10, 2 );
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

		if (
			// POST-Requests always have to be signed.
			'GET' !== $request->get_method() ||
			// GET-Requests only require a signature in secure mode.
			( 'GET' === $request->get_method() && use_authorized_fetch() )
		) {
			$verified_request = Signature::verify_http_signature( $request );
			if ( \is_wp_error( $verified_request ) ) {
				return new WP_Error(
					'activitypub_signature_verification',
					$verified_request->get_error_message(),
					array( 'status' => 401 )
				);
			}
		}

		return true;
	}

	/**
	 * Callback function to validate incoming ActivityPub requests
	 *
	 * @param WP_REST_Response|\WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
	 *                                                                    Usually a WP_REST_Response or WP_Error.
	 * @param array                                             $handler  Route handler used for the request.
	 * @param \WP_REST_Request                                  $request  Request used to generate the response.
	 *
	 * @return mixed|WP_Error The response, error, or modified response.
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
			return new WP_Error(
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
	 * @param string[]        $order   Array of types to check, in order of priority.
	 * @param WP_REST_Request $request The request object.
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

		if ( WP_REST_Server::CREATABLE !== $method ) {
			return $order;
		}

		return array(
			'JSON',
			'POST',
			'URL',
			'defaults',
		);
	}
}
