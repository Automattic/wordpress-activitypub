<?php
namespace Activitypub\Rest;

use Activitypub\Collection\Users as User_Collection;

use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub Following REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#following
 */
class Following {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/users/(?P<user_id>[\w\-\.]+)/following',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get' ),
					'args'                => self::request_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Handle GET request
	 *
	 * @param  WP_REST_Request   $request
	 *
	 * @return WP_REST_Response
	 */
	public static function get( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = User_Collection::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_rest_following_pre' );

		$json = new \stdClass();

		$json->{'@context'} = \Activitypub\get_context();

		$json->id = get_rest_url_by_path( sprintf( 'users/%d/following', $user->get__id() ) );
		$json->generator = 'http://wordpress.org/?v=' . \get_bloginfo_rss( 'version' );
		$json->actor = $user->get_id();
		$json->type = 'OrderedCollectionPage';

		$json->partOf = get_rest_url_by_path( sprintf( 'users/%d/following', $user->get__id() ) ); // phpcs:ignore

		$items = apply_filters( 'activitypub_rest_following', array(), $user ); // phpcs:ignore

		$json->totalItems = count( $items ); // phpcs:ignore
		$json->orderedItems = $items; // phpcs:ignore

		$json->first = $json->partOf; // phpcs:ignore

		$response = new \WP_REST_Response( $json, 200 );
		$response->header( 'Content-Type', 'application/activity+json' );

		return $response;
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function request_parameters() {
		$params = array();

		$params['page'] = array(
			'type' => 'integer',
		);

		$params['user_id'] = array(
			'required' => true,
			'type' => 'string',
		);

		return $params;
	}
}
