<?php
/**
 * ActivityPub Actors REST-Class
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use Activitypub\Webfinger;
use Activitypub\Collection\Actors as User_Collection;

use function Activitypub\is_activitypub_request;

/**
 * ActivityPub Actors REST-Class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#followers
 */
class Actors {
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
			'/(users|actors)/(?P<user_id>[\w\-\.]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/(users|actors)/(?P<user_id>[\w\-\.]+)/remote-follow',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'remote_follow_get' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'resource' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Handle GET request
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response|\WP_Error The response object or WP_Error.
	 */
	public static function get( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = User_Collection::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$link_header = sprintf( '<%1$s>; rel="alternate"; type="application/activity+json"', $user->get_id() );

		// Redirect to canonical URL if it is not an ActivityPub request.
		if ( ! is_activitypub_request() ) {
			header( 'Link: ' . $link_header );
			header( 'Location: ' . $user->get_canonical_url(), true, 301 );
			exit;
		}

		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 */
		\do_action( 'activitypub_rest_users_pre' );

		$json = $user->to_array();

		$rest_response = new WP_REST_Response( $json, 200 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
		$rest_response->header( 'Link', $link_header );

		return $rest_response;
	}


	/**
	 * Endpoint for remote follow UI/Block.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response|\WP_Error The response object or WP_Error.
	 */
	public static function remote_follow_get( WP_REST_Request $request ) {
		$resource = $request->get_param( 'resource' );
		$user_id  = $request->get_param( 'user_id' );
		$user     = User_Collection::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$template = Webfinger::get_remote_follow_endpoint( $resource );

		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$resource = $user->get_webfinger();
		$url      = str_replace( '{uri}', $resource, $template );

		return new WP_REST_Response(
			array(
				'url'      => $url,
				'template' => $template,
			),
			200
		);
	}
}
