<?php
namespace Activitypub\Rest;

use WP_REST_Server;
use WP_REST_Response;

use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub Collections REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://docs.joinmastodon.org/spec/activitypub/#featured
 * @see https://docs.joinmastodon.org/spec/activitypub/#featuredTags
 */
class Collection {
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
			'/users/(?P<user_id>[\w\-\.]+)/collections/tags',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'tags_get' ),
					'args'                => self::request_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);

		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/users/(?P<user_id>[\w\-\.]+)/collections/featured',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'featured_get' ),
					'args'                => self::request_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 *
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return void
	 */
	public static function tags_get( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$number  = 5;

		$tags = \get_terms(
			array(
				'taxonomy' => 'post_tag',
				'orderby'  => 'count',
				'order'    => 'DESC',
				'number'   => $number,
			)
		);

		$response = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			array(
				'Hashtah' => 'as:Hastag',
			),
			'id'         => get_rest_url_by_path( sprintf( 'users/%d/collections/tags', $user_id ) ),
			'totalItems' => count( $tags ),
			'items'      => array(),
		);

		foreach ( $tags as $tag ) {
			$response['items'][] = array(
				'type' => 'Hashtag',
				'href' => \get_tag_link( $tag ),
				'name' => $tag->name,
			);
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 *
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return void
	 */
	public static function featured_get( $request ) {
		$user_id = $request->get_param( 'user_id' );

		$response = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			array(
				'Hashtah' => 'as:Hastag',
			),
			'id'         => get_rest_url_by_path( sprintf( 'users/%d/collections/featured', $user_id ) ),
			'totalItems' => 0,
			'items'      => array(),
		);

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function request_parameters() {
		$params = array();

		$params['user_id'] = array(
			'required' => true,
			'type' => 'string',
		);

		return $params;
	}
}
