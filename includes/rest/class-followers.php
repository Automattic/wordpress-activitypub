<?php
namespace Activitypub\Rest;

use WP_Error;
use stdClass;
use WP_REST_Server;
use WP_REST_Response;
use Activitypub\Collection\Users as User_Collection;
use Activitypub\Collection\Followers as Follower_Collection;

use function Activitypub\get_rest_url_by_path;
use function Activitypub\get_masked_wp_version;

/**
 * ActivityPub Followers REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#followers
 */
class Followers {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		self::register_routes();
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/(users|actors)/(?P<user_id>[\w\-\.]+)/followers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
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

		$order    = $request->get_param( 'order' );
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$context  = $request->get_param( 'context' );

		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_rest_followers_pre' );

		$data = Follower_Collection::get_followers_with_count( $user_id, $per_page, $page, array( 'order' => ucwords( $order ) ) );
		$json = new stdClass();

		$json->{'@context'} = \Activitypub\get_context();

		$json->id = get_rest_url_by_path( sprintf( 'actors/%d/followers', $user->get__id() ) );
		$json->generator = 'http://wordpress.org/?v=' . get_masked_wp_version();
		$json->actor = $user->get_id();
		$json->type = 'OrderedCollectionPage';

		$json->totalItems = $data['total']; // phpcs:ignore
		$json->partOf = get_rest_url_by_path( sprintf( 'actors/%d/followers', $user->get__id() ) ); // phpcs:ignore

		$json->first = \add_query_arg( 'page', 1, $json->partOf ); // phpcs:ignore
		$json->last  = \add_query_arg( 'page', \ceil ( $json->totalItems / $per_page ), $json->partOf ); // phpcs:ignore

		if ( $page && ( ( \ceil ( $json->totalItems / $per_page ) ) > $page ) ) { // phpcs:ignore
			$json->next  = \add_query_arg( 'page', $page + 1, $json->partOf ); // phpcs:ignore
		}

		if ( $page && ( $page > 1 ) ) { // phpcs:ignore
			$json->prev  = \add_query_arg( 'page', $page - 1, $json->partOf ); // phpcs:ignore
		}

		// phpcs:ignore
		$json->orderedItems = array_map(
			function ( $item ) use ( $context ) {
				if ( 'full' === $context ) {
					return $item->to_array( false );
				}
				return $item->get_url();
			},
			$data['followers']
		);

		$rest_response = new WP_REST_Response( $json, 200 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );

		return $rest_response;
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
			'default' => 1,
		);

		$params['per_page'] = array(
			'type' => 'integer',
			'default' => 20,
		);

		$params['order'] = array(
			'type'    => 'string',
			'default' => 'desc',
			'enum'    => array( 'asc', 'desc' ),
		);

		$params['user_id'] = array(
			'required' => true,
			'type' => 'string',
		);

		$params['context'] = array(
			'type' => 'string',
			'default' => 'simple',
			'enum' => array( 'simple', 'full' ),
		);

		return $params;
	}
}
