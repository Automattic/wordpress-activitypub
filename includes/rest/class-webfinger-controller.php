<?php
/**
 * WebFinger REST-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use WP_REST_Response;

/**
 * ActivityPub WebFinger REST-Class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://webfinger.net/
 */
class Webfinger {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		self::register_routes();
	}

	/**
	 * Register routes.
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/webfinger',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'webfinger' ),
					'args'                => self::request_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * WebFinger endpoint.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function webfinger( $request ) {
		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 */
		\do_action( 'activitypub_rest_webfinger_pre' );

		$code = 200;

		$resource = $request->get_param( 'resource' );
		$response = self::get_profile( $resource );

		if ( \is_wp_error( $response ) ) {
			$code       = 400;
			$error_data = $response->get_error_data();

			if ( isset( $error_data['status'] ) ) {
				$code = $error_data['status'];
			}
		}

		return new WP_REST_Response(
			$response,
			$code,
			array(
				'Access-Control-Allow-Origin' => '*',
				'Content-Type'                => 'application/jrd+json; charset=' . get_option( 'blog_charset' ),
			)
		);
	}

	/**
	 * The supported parameters.
	 *
	 * @return array list of parameters
	 */
	public static function request_parameters() {
		$params = array();

		$params['resource'] = array(
			'required'          => true,
			'type'              => 'string',
			'pattern'           => '^(acct:)|^(https?://)(.+)$',
			'sanitize_callback' => 'sanitize_text_field',
		);

		return $params;
	}

	/**
	 * Get the WebFinger profile.
	 *
	 * @param string $webfinger the WebFinger resource.
	 *
	 * @return array|\WP_Error The WebFinger profile or WP_Error if not found.
	 */
	public static function get_profile( $webfinger ) {
		/**
		 * Filter the WebFinger data.
		 *
		 * @param array  $data      The WebFinger data.
		 * @param string $webfinger The WebFinger resource.
		 */
		return apply_filters( 'webfinger_data', array(), $webfinger );
	}
}
