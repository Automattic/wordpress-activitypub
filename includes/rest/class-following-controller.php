<?php
/**
 * ActivityPub Following REST-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use WP_REST_Response;
use Activitypub\Collection\Actors;

use function Activitypub\is_single_user;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\get_masked_wp_version;

/**
 * ActivityPub Following REST-Class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#following
 */
class Following {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		self::register_routes();

		\add_filter( 'activitypub_rest_following', array( self::class, 'default_following' ), 10, 2 );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/(users|actors)/(?P<user_id>[\w\-\.]+)/following',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
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

		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 */
		\do_action( 'activitypub_rest_following_pre' );

		$json = new \stdClass();

		$json->{'@context'} = \Activitypub\get_context();

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$json->id        = get_rest_url_by_path( sprintf( 'actors/%d/following', $user->get__id() ) );
		$json->generator = 'http://wordpress.org/?v=' . get_masked_wp_version();
		$json->actor     = $user->get_id();
		$json->type      = 'OrderedCollectionPage';
		$json->partOf    = get_rest_url_by_path( sprintf( 'actors/%d/following', $user->get__id() ) );

		/**
		 * Filter the list of following urls.
		 *
		 * @param array                   $items The array of following urls.
		 * @param \Activitypub\Model\User $user  The user object.
		 */
		$items = apply_filters( 'activitypub_rest_following', array(), $user );

		$json->totalItems   = is_countable( $items ) ? count( $items ) : 0;
		$json->orderedItems = $items;
		$json->first        = $json->partOf;
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
			'type' => 'integer',
		);

		return $params;
	}

	/**
	 * Add the Blog Authors to the following list of the Blog Actor
	 * if Blog not in single mode.
	 *
	 * @param array                   $follow_list The array of following urls.
	 * @param \Activitypub\Model\User $user        The user object.
	 *
	 * @return array The array of following urls.
	 */
	public static function default_following( $follow_list, $user ) {
		if ( 0 !== $user->get__id() || is_single_user() ) {
			return $follow_list;
		}

		$users = Actors::get_collection();

		foreach ( $users as $user ) {
			$follow_list[] = $user->get_id();
		}

		return $follow_list;
	}
}
