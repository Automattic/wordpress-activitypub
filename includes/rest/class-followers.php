<?php
/**
 * Followers REST-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use stdClass;
use WP_REST_Server;
use WP_REST_Response;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers as Follower_Collection;

use function Activitypub\get_rest_url_by_path;
use function Activitypub\get_masked_wp_version;

/**
 * ActivityPub Followers REST-Class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#followers
 */
class Followers {
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
			'/(users|actors)/(?P<user_id>[\w\-\.]+)/followers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get' ),
					'args'                => self::request_parameters(),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
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
		$user    = Actors::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$order    = $request->get_param( 'order' );
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$context  = $request->get_param( 'context' );

		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_rest_followers_pre' );

		$data = Follower_Collection::get_followers_with_count( $user_id, $per_page, $page, array( 'order' => ucwords( $order ) ) );
		$json = new stdClass();

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$json->{'@context'} = \Activitypub\get_context();
		$json->id           = get_rest_url_by_path( sprintf( 'actors/%d/followers', $user->get__id() ) );
		$json->generator    = 'http://wordpress.org/?v=' . get_masked_wp_version();
		$json->actor        = $user->get_id();
		$json->type         = 'OrderedCollectionPage';
		$json->totalItems   = $data['total'];
		$json->partOf       = get_rest_url_by_path( sprintf( 'actors/%d/followers', $user->get__id() ) );

		$json->first = \add_query_arg( 'page', 1, $json->partOf );
		$json->last  = \add_query_arg( 'page', \ceil( $json->totalItems / $per_page ), $json->partOf );

		if ( $page && ( ( \ceil( $json->totalItems / $per_page ) ) > $page ) ) {
			$json->next = \add_query_arg( 'page', $page + 1, $json->partOf );
		}

		if ( $page && ( $page > 1 ) ) {
			$json->prev = \add_query_arg( 'page', $page - 1, $json->partOf );
		}

		$json->orderedItems = array_map(
			function ( $item ) use ( $context ) {
				if ( 'full' === $context ) {
					return $item->to_array( false );
				}
				return $item->get_id();
			},
			$data['followers']
		);
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		$rest_response = new WP_REST_Response( $json, 200 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );

		return $rest_response;
	}

	/**
	 * The supported parameters.
	 *
	 * @return array List of parameters.
	 */
	public static function request_parameters() {
		$params = array();

		$params['page'] = array(
			'type'    => 'integer',
			'default' => 1,
		);

		$params['per_page'] = array(
			'type'    => 'integer',
			'default' => 20,
		);

		$params['order'] = array(
			'type'    => 'string',
			'default' => 'desc',
			'enum'    => array( 'asc', 'desc' ),
		);

		$params['context'] = array(
			'type'    => 'string',
			'default' => 'simple',
			'enum'    => array( 'simple', 'full' ),
		);

		return $params;
	}
}
