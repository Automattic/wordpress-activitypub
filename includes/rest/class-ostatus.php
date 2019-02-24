<?php
namespace Activitypub\Rest;

class Ostatus {
	/**
	 * Register routes
	 */
	public static function register_routes() {
		register_rest_route(
			'activitypub/1.0', '/ostatus/remote-follow', array(
				array(
					'methods'  => \WP_REST_Server::READABLE,
					'callback' => array( '\Activitypub\Rest\Ostatus', 'webfinger' ),
					'args'     => self::request_parameters(),
				),
			)
		);
	}
}
