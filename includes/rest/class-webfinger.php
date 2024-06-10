<?php
namespace Activitypub\Rest;

use WP_Error;
use WP_REST_Response;
use Activitypub\Collection\Users as User_Collection;

/**
 * ActivityPub WebFinger REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://webfinger.net/
 */
class Webfinger {
	/**
	 * Initialize the class, registering WordPress hooks.
	 *
	 * @return void
	 */
	public static function init() {
		self::register_routes();
	}

	/**
	 * Register routes.
	 *
	 * @return void
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
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function webfinger( $request ) {
		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
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
				'Content-Type' => 'application/jrd+json; charset=' . get_option( 'blog_charset' ),
			)
		);
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
			'pattern' => '^(acct:)|^(https?://)(.+)$',
		);

		return $params;
	}

	/**
	 * Get the WebFinger profile.
	 *
	 * @param string $resource the WebFinger resource.
	 *
	 * @return array the WebFinger profile.
	 */
	public static function get_profile( $resource ) {
		$user = User_Collection::get_by_resource( $resource );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		$aliases = array(
			$user->get_url(),
			$user->get_alternate_url(),
		);

		$aliases = array_unique( $aliases );

		$profile = array(
			'subject' => sprintf( 'acct:%s', $user->get_webfinger() ),
			'aliases' => array_values( array_unique( $aliases ) ),
			'links'   => array(
				array(
					'rel'  => 'self',
					'type' => 'application/activity+json',
					'href' => $user->get_url(),
				),
				array(
					'rel'  => 'http://webfinger.net/rel/profile-page',
					'type' => 'text/html',
					'href' => $user->get_url(),
				),
			),
		);

		if ( 'Person' !== $user->get_type() ) {
			$profile['links'][0]['properties'] = array(
				'https://www.w3.org/ns/activitystreams#type' => $user->get_type(),
			);
		}

		return $profile;
	}
}
