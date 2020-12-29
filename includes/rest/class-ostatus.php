<?php
namespace Activitypub\Rest;

/**
 * ActivityPub OStatus REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/community/ostatus/
 */
class Ostatus {
	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			'activitypub/1.0', '/ostatus/remote-follow', array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( '\Activitypub\Rest\Ostatus', 'get' ),
					// 'args'     => self::request_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	public static function get() {
		// @todo implement
	}
}
