<?php
namespace Activitypub\Rest;

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
		\add_filter( 'rest_request_before_callbacks', array( '\Activitypub\Rest\Server', 'authorize_activitypub_requests' ), 10, 3 );
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
				$secure_mode = \get_option( 'activitypub_use_secure_mode', '0' );

				if ( $secure_mode ) {
					if ( \is_wp_error( $verified_request ) ) {
						return $verified_request;
					}
				}
			}
		}
	}
}
