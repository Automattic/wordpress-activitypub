<?php
namespace Activitypub\Rest;

use WP_REST_Response;
use Activitypub\Signature;

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
		\add_filter( 'rest_request_before_callbacks', array( self::class, 'authorize_activitypub_requests' ), 10, 3 );
		\add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			'activitypub/1.0',
			'/service',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'service_actor' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Render Service actor profile
	 *
	 * @return WP_REST_Response
	 */
	public static function service_actor() {
		$json = new \stdClass();

		$json->{'@context'} = \Activitypub\get_context();
		$json->id = \get_rest_url( null, 'activitypub/1.0/service' );
		$json->type = 'Application';
		$json->preferredUsername = parse_url( get_site_url(), PHP_URL_HOST );
		$json->name = get_bloginfo( 'name' );
		$json->summary = "ActivityPub service actor";
		$json->manuallyApprovesFollowers = TRUE;
		$json->icon = [ get_site_icon_url() ];
		$json->publicKey = (object) array(
			'id' => \get_rest_url( null, 'activitypub/1.0/service#main-key' ),
			'owner' => \get_rest_url( null, 'activitypub/1.0/service' ),
			'publicKeyPem' => Signature::get_public_key( -1 ),
		);

		$response = new WP_REST_Response( $json, 200 );

		$response->header( 'Content-Type', 'application/activity+json' );

		return $response;
	}

	/**
	 * Callback function to authorize each api requests
	 *
	 * @see \WP_REST_Request
	 *
	 * @param                  $response
	 * @param                  $handler
	 * @param \WP_REST_Request $request
	 *
	 * @return mixed|\WP_Error
	 */
	public static function authorize_activitypub_requests( $response, $handler, $request ) {

		$maybe_activitypub = $request->get_route();
		if ( str_starts_with( $maybe_activitypub, '/activitypub' ) ) {
			if ( 'POST' === $request->get_method() ) {
				$verified_request = Signature::verify_http_signature( $request );

				if ( \is_wp_error( $verified_request ) ) {
					return $verified_request;
				}
			} else {
				// SecureMode/Authorized fetch.
				// $secure_mode = \get_option( 'activitypub_use_secure_mode', '0' );
				// if ( $secure_mode ) {
				//	$verified_request = Signature::verify_http_signature( $request );
				// 	if ( \is_wp_error( $verified_request ) ) {
				// 		return $verified_request;
				// 	}
				// }
			}
		}
	}
}
