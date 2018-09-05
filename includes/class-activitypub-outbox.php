<?php
/**
 * ActivityPub Outbox Class
 *
 * @author Matthias Pfefferle
 */
class Activitypub_Outbox {
	/**
	 * Register the Route.
	 */
	public static function register_routes() {
		register_rest_route(
			'activitypub/1.0', '/outbox', array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( 'Activitypub_Outbox', 'get' ),
				),
			)
		);
	}

	public static function get( $request ) {
		$outbox = new stdClass();

		$outbox->{'@context'} = array(
			'https://www.w3.org/ns/activitystreams',
			'https://w3id.org/security/v1',
		);

		//var_dump($request->get_param('page'));

		return new WP_REST_Response( $outbox, 200 );
	}
}
