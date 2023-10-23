<?php
namespace Activitypub\Rest;

use stdClass;
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

		$user->set_context(
			\Activitypub\Activity\Activity::CONTEXT
		);

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
			\str_starts_with( $route, '/' . \trailingslashit( ACTIVITYPUB_REST_NAMESPACE ) . 'nodeinfo' )
		) {
			return $response;
		}

		// POST-Requets are always signed
		if ( 'GET' !== $request->get_method() ) {
			$verified_request = Signature::verify_http_signature( $request );
			if ( \is_wp_error( $verified_request ) ) {
				return $verified_request;
			}
		} elseif ( 'GET' === $request->get_method() ) { // GET-Requests are only signed in secure mode
			if ( ACTIVITYPUB_AUTHORIZED_FETCH ) {
				$verified_request = Signature::verify_http_signature( $request );
				if ( \is_wp_error( $verified_request ) ) {
					return $verified_request;
				}
			}
		}

		return $response;
	}
}
