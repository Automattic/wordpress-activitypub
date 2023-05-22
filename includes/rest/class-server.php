<?php
namespace Activitypub\Rest;

use stdClass;
use WP_REST_Response;
use Activitypub\Signature;
use Activitypub\Model\User;

use function Activitypub\get_context;
use function Activitypub\get_rest_url_by_path;


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
		\add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
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
		$json = new stdClass();

		$json->{'@context'} = get_context();
		$json->id = get_rest_url_by_path( 'application' );
		$json->type = 'Application';
		$json->preferredUsername = str_replace( array( '.' ), '-', wp_parse_url( get_site_url(), PHP_URL_HOST ) ); // phpcs:ignore WordPress.NamingConventions
		$json->name = get_bloginfo( 'name' );
		$json->summary = __( 'WordPress-ActivityPub application actor', 'activitypub' );
		$json->manuallyApprovesFollowers = true; // phpcs:ignore WordPress.NamingConventions
		$json->icon = array( get_site_icon_url() ); // phpcs:ignore WordPress.NamingConventions short array syntax
		$json->publicKey = array( // phpcs:ignore WordPress.NamingConventions
			'id' => get_rest_url_by_path( 'application#main-key' ),
			'owner' => get_rest_url_by_path( 'application' ),
			'publicKeyPem' => Signature::get_public_key( User::APPLICATION_USER_ID ), // phpcs:ignore WordPress.NamingConventions
		);

		$response = new WP_REST_Response( $json, 200 );

		$response->header( 'Content-Type', 'application/activity+json' );

		return $response;
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
		$route = $request->get_route();

		if (
			! str_starts_with( $route, '/' . ACTIVITYPUB_REST_NAMESPACE ) ||
			str_starts_with( $route, '/' . \trailingslashit( ACTIVITYPUB_REST_NAMESPACE ) . 'webfinger' ) ||
			str_starts_with( $route, '/' . \trailingslashit( ACTIVITYPUB_REST_NAMESPACE ) . 'nodeinfo' )
		) {
			return $response;
		}

		if ( 'POST' === $request->get_method() ) {
			$verified_request = Signature::verify_http_signature( $request );
			if ( \is_wp_error( $verified_request ) ) {
				return $verified_request;
			}
		} elseif ( 'GET' === $request->get_method() ) {
			if ( ACTIVITYPUB_SECURE_MODE ) {
				$verified_request = Signature::verify_http_signature( $request );
				if ( \is_wp_error( $verified_request ) ) {
					return $verified_request;
				}
			}
		}

		return $response;
	}
}
