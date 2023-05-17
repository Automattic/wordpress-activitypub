<?php
namespace Activitypub\Rest;

use WP_Error;
use WP_REST_Response;

/**
 * ActivityPub WebFinger REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://webfinger.net/
 */
class Webfinger {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		\add_action( 'parse_request', array( self::class, 'parse_request' ) );
		\add_action( 'webfinger_user_data', array( self::class, 'add_webfinger_discovery' ), 10, 3 );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/webfinger',
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
	 * Render API output
	 *
	 * @param  WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response|WP_Error The REST response or an Error object.
	 */
	public static function get( $request ) {
		$resource = $request->get_param( 'resource' );
		$json     = self::webfinger( $resource );

		header( 'Access-Control-Allow-Origin: *' );

		return new WP_REST_Response( $json, 200 );
	}

	/**
	 * Render query output
	 *
	 * @param WP $wp WordPress request context
	 *
	 * @uses apply_filters() Calls 'webfinger' on webfinger data array
	 * @uses do_action() Calls 'webfinger_render' to render webfinger data
	 */
	public static function parse_request( $wp ) {
		// check if it is a webfinger request or not
		if (
			! array_key_exists( 'well-known', $wp->query_vars ) ||
			'webfinger' !== $wp->query_vars['well-known']
		) {
			return;
		}

		header( 'Access-Control-Allow-Origin: *' );

		$json = self::webfinger( $wp->query_vars['resource'] );

		wp_send_json( $json );
	}

	/**
	 * Build JRD file
	 *
	 * @param  string $request The WebFinger resource
	 *
	 * @return array|WP_Error The JRD data or an Error object.
	 */
	public static function webfinger( $resource ) {
		if ( empty( $resource ) ) {
			return new WP_Error(
				'activitypub_missing_resource',
				\__( 'Resource parameter is missing', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		if ( \strpos( $resource, '@' ) === false ) {
			return new WP_Error(
				'activitypub_unsupported_resource',
				\__( 'Resource is invalid', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		$resource = \str_replace( 'acct:', '', $resource );

		$resource_identifier = \substr( $resource, 0, \strrpos( $resource, '@' ) );
		$resource_host = \str_replace( 'www.', '', \substr( \strrchr( $resource, '@' ), 1 ) );
		$blog_host = \str_replace( 'www.', '', \wp_parse_url( \home_url( '/' ), \PHP_URL_HOST ) );

		if ( $blog_host !== $resource_host ) {
			return new WP_Error(
				'activitypub_wrong_host',
				\__( 'Resource host does not match blog host', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$user = \get_user_by( 'login', \esc_sql( $resource_identifier ) );

		if ( ! $user || ! \user_can( $user, 'publish_posts' ) ) {
			return new WP_Error(
				'activitypub_user_not_found',
				\__( 'User not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$json = array(
			'subject' => $resource,
			'aliases' => array(
				\get_author_posts_url( $user->ID ),
			),
			'links' => array(
				array(
					'rel'  => 'self',
					'type' => 'application/activity+json',
					'href' => \get_author_posts_url( $user->ID ),
				),
				array(
					'rel'  => 'http://webfinger.net/rel/profile-page',
					'type' => 'text/html',
					'href' => \get_author_posts_url( $user->ID ),
				),
			),
		);

		return $json;
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function request_parameters() {
		$params = array();

		$params['resource'] = array(
			'required' => true,
			'type' => 'string',
			'pattern' => '^acct:(.+)@(.+)$',
		);

		return $params;
	}

	/**
	 * Add WebFinger discovery links
	 *
	 * @param array   $array    the jrd array
	 * @param string  $resource the WebFinger resource
	 * @param WP_User $user     the WordPress user
	 */
	public static function add_webfinger_discovery( $array, $resource, $user ) {
		$array['links'][] = array(
			'rel'  => 'self',
			'type' => 'application/activity+json',
			'href' => \get_author_posts_url( $user->ID ),
		);

		return $array;
	}
}
