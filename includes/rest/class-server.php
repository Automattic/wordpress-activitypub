<?php
namespace Activitypub\Rest;

use stdClass;
use WP_Error;
use WP_REST_Response;
use Activitypub\Signature;
use Activitypub\Model\Application_User;

/**
 * ActivityPub Server REST-Class
 *
 * @author Django Doucet
 *
 * @see https://www.w3.org/TR/activitypub/#security-verification
 */
class Server {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		self::register_routes();

		\add_filter( 'rest_request_before_callbacks', array( self::class, 'authorize_activitypub_requests' ), 10, 3 );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/application',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'application_actor' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Render Application actor profile
	 *
	 * @return WP_REST_Response The JSON profile of the Application Actor.
	 */
	public static function application_actor() {
		$user = new Application_User();

		$json = $user->to_array();

		$rest_response = new WP_REST_Response( $json, 200 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );

		return $rest_response;
	}

	/**
	 * Callback function to authorize each api requests
	 *
	 * @see WP_REST_Request
	 *
	 * @see https://www.w3.org/wiki/SocialCG/ActivityPub/Primer/Authentication_Authorization#Authorized_fetch
	 * @see https://swicg.github.io/activitypub-http-signature/#authorized-fetch
	 *
	 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send to the client.
	 *                                                                   Usually a WP_REST_Response or WP_Error.
	 * @param array                                            $handler  Route handler used for the request.
	 * @param WP_REST_Request                                  $request  Request used to generate the response.
	 *
	 * @return mixed|WP_Error The response, error, or modified response.
	 */
	public static function authorize_activitypub_requests( $response, $handler, $request ) {
		if ( 'HEAD' === $request->get_method() ) {
			return $response;
		}

		$route = $request->get_route();

		// check if it is an activitypub request and exclude webfinger and nodeinfo endpoints
		if (
			! \str_starts_with( $route, '/' . ACTIVITYPUB_REST_NAMESPACE ) ||
			\str_starts_with( $route, '/' . \trailingslashit( ACTIVITYPUB_REST_NAMESPACE ) . 'webfinger' ) ||
			\str_starts_with( $route, '/' . \trailingslashit( ACTIVITYPUB_REST_NAMESPACE ) . 'nodeinfo' ) ||
			\str_starts_with( $route, '/' . \trailingslashit( ACTIVITYPUB_REST_NAMESPACE ) . 'application' )
		) {
			return $response;
		}

		/**
		 * Filter to defer signature verification
		 *
		 * Skip signature verification for debugging purposes or to reduce load for
		 * certain Activity-Types, like "Delete".
		 *
		 * @param bool            $defer   Whether to defer signature verification.
		 * @param WP_REST_Request $request The request used to generate the response.
		 *
		 * @return bool Whether to defer signature verification.
		 */
		$defer = \apply_filters( 'activitypub_defer_signature_verification', false, $request );

		if ( $defer ) {
			return $response;
		}

		if (
			// POST-Requets are always signed
			'GET' !== $request->get_method() ||
			// GET-Requests are only signed in secure mode
			( 'GET' === $request->get_method() && ACTIVITYPUB_AUTHORIZED_FETCH )
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

		return $response;
	}
}
