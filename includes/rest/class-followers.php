<?php
namespace Activitypub\Rest;

use WP_Error;
use stdClass;
use WP_REST_Server;
use WP_REST_Response;
use Activitypub\Collection\Users as User_Collection;
use Activitypub\Collection\Followers as Follower_Collection;

use function Activitypub\get_rest_url_by_path;

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
		\add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/users/(?P<user_id>[\w\-\.]+)/followers',
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
		$context = $request->get_param( 'context' );
		if ( 'view' === $context ) {
			return self::get_followers( $request );
		}
		$user_id = $request->get_param( 'user_id' );
		$user    = User_Collection::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$page = $request->get_param( 'page', 1 );

		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_outbox_pre' );

		$json = new stdClass();

		$json->{'@context'} = \Activitypub\get_context();

		$json->id = \home_url( \add_query_arg( null, null ) );
		$json->generator = 'http://wordpress.org/?v=' . \get_bloginfo_rss( 'version' );
		$json->actor = $user->get_id();
		$json->type = 'OrderedCollectionPage';

		$json->totalItems = Follower_Collection::count_followers( $user->get__id() ); // phpcs:ignore
		$json->partOf = get_rest_url_by_path( sprintf( 'users/%d/followers', $user->get__id() ) ); // phpcs:ignore

		$json->first = \add_query_arg( 'page', 1, $json->partOf ); // phpcs:ignore
		$json->last  = \add_query_arg( 'page', \ceil ( $json->totalItems / 20 ), $json->partOf ); // phpcs:ignore

		if ( $page && ( ( \ceil ( $json->totalItems / 20 ) ) > $page ) ) { // phpcs:ignore
			$json->next  = \add_query_arg( 'page', $page + 1, $json->partOf ); // phpcs:ignore
		}

		if ( $page && ( $page > 1 ) ) { // phpcs:ignore
			$json->prev  = \add_query_arg( 'page', $page - 1, $json->partOf ); // phpcs:ignore
		}

		// phpcs:ignore
		$json->orderedItems = array_map(
			function( $item ) {
				return $item->get_url();
			},
			Follower_Collection::get_followers( $user->get__id(), 20, $page )
		);

		$response = new WP_REST_Response( $json, 200 );
		$response->header( 'Content-Type', 'application/activity+json' );

		return $response;
	}

	private static function get_followers( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$order = $request->get_param( 'order' );
		$per_page = $request->get_param( 'per_page' );
		$page = $request->get_param( 'page' );
		$offset = ( $page - 1 ) * $per_page;
		$query = Follower_Collection::get_followers_query( $user_id, $per_page, $offset, array( 'order' => ucwords( $order ) ) );

		$followers = array();
		foreach ( $query->get_posts() as $post ) {
			$followers[] = Follower::from_custom_post_type( $post )->to_array();
		}

		$total = $query->found_posts;
		$total_pages = ceil( $total / (int) $query->query_vars['posts_per_page'] );

		return compact( 'followers', 'total', 'total_pages' );
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
			'default' => 10,
		);

		$params['order'] = array(
			'type'    => 'string',
			'default' => 'desc',
			'enum'    => array( 'asc', 'desc' ),
		);

		$params['user_id'] = array(
			'required' => true,
			'type' => 'string',
			'validate_callback' => function( $param, $request, $key ) {
				// despite being an integer, user_id is passed as string.
				$param = (int) $param;
				return 0 === $param || user_can( $param, 'publish_posts' );
			},
		);

		$params['context'] = array(
			'type' => 'string',
			'default' => 'outbox',
			'validate_callback' => function( $param, $request, $key ) {
				return in_array( $param, array( 'outbox', 'view' ), true );
			},
		);

		return $params;
	}
}
