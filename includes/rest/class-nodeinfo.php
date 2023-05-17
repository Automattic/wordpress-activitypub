<?php
namespace Activitypub\Rest;

use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub NodeInfo REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see http://nodeinfo.diaspora.software/
 */
class Nodeinfo {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		\add_action( 'parse_request', array( self::class, 'parse_request' ) );
		\add_filter( 'nodeinfo_data', array( self::class, 'add_nodeinfo_data' ), 10, 2 );
		\add_filter( 'nodeinfo2_data', array( self::class, 'add_nodeinfo2_data' ), 10 );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/nodeinfo/discovery',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'discovery' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/nodeinfo',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'nodeinfo' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/nodeinfo2',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'nodeinfo2' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Render NodeInfo file
	 *
	 * @param  WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response The response data.
	 */
	public static function nodeinfo( $request ) {
		$nodeinfo = array();

		$nodeinfo['version'] = '2.0';
		$nodeinfo['software'] = array(
			'name' => 'wordpress',
			'version' => \get_bloginfo( 'version' ),
		);

		$users = \get_users(
			array(
				'capability__in' => array( 'publish_posts' ),
			)
		);

		if ( is_array( $users ) ) {
			$users = count( $users );
		} else {
			$users = 1;
		}

		$posts = \wp_count_posts();
		$comments = \wp_count_comments();

		$nodeinfo['usage'] = array(
			'users' => array(
				'total' => $users,
			),
			'localPosts' => (int) $posts->publish,
			'localComments' => (int) $comments->approved,
		);

		$nodeinfo['openRegistrations'] = false;
		$nodeinfo['protocols'] = array( 'activitypub' );

		$nodeinfo['services'] = array(
			'inbound' => array(),
			'outbound' => array(),
		);

		return new \WP_REST_Response( $nodeinfo, 200 );
	}

	/**
	 * Render NodeInfo file
	 *
	 * @param  WP_REST_Request|WP $request The REST or WordPress query request.
	 *
	 * @return WP_REST_Response The response data.
	 */
	public static function nodeinfo2( $request ) {
		$nodeinfo = array();

		$nodeinfo['version'] = '1.0';
		$nodeinfo['server'] = array(
			'baseUrl' => \home_url( '/' ),
			'name' => \get_bloginfo( 'name' ),
			'software' => 'wordpress',
			'version' => \get_bloginfo( 'version' ),
		);

		$users = \get_users(
			array(
				'capability__in' => array( 'publish_posts' ),
			)
		);

		if ( is_array( $users ) ) {
			$users = count( $users );
		} else {
			$users = 1;
		}

		$posts = \wp_count_posts();
		$comments = \wp_count_comments();

		$nodeinfo['usage'] = array(
			'users' => array(
				'total' => (int) $users,
			),
			'localPosts' => (int) $posts->publish,
			'localComments' => (int) $comments->approved,
		);

		$nodeinfo['openRegistrations'] = false;
		$nodeinfo['protocols'] = array( 'activitypub' );

		$nodeinfo['services'] = array(
			'inbound' => array(),
			'outbound' => array(),
		);

		if ( $request instanceof WP_REST_Request ) {
			return new \WP_REST_Response( $nodeinfo, 200 );
		} else {
			wp_send_json( $nodeinfo );
		}
	}

	/**
	 * Render NodeInfo discovery file
	 *
	 * @param  WP_REST_Request|WP $request The REST or WordPress query request.
	 *
	 * @return WP_REST_Response The response data.
	 */
	public static function discovery( $request ) {
		$discovery = array();
		$discovery['links'] = array(
			array(
				'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
				'href' => get_rest_url_by_path( 'nodeinfo' ),
			),
		);

		$discovery = apply_filters( 'activitypub_nodeinfo_discovery', $discovery );

		if ( $request instanceof WP_REST_Request ) {
			return new \WP_REST_Response( $discovery, 200 );
		} else {
			wp_send_json( $discovery );
		}
	}

	/**
	 * Render query output
	 *
	 * @param WP $wp WordPress request context.
	 */
	public static function parse_request( $wp ) {
		// check if it is a webfinger request or not
		if (
			! array_key_exists( 'well-known', $wp->query_vars ) ||
			! in_array( $wp->query_vars['well-known'], array( 'nodeinfo2', 'nodeinfo/discovery' ), true )
		) {
			return;
		}

		if ( 'nodeinfo2' === $wp->query_vars['well-known'] ) {
			self::nodeinfo2( $wp );
		} else {
			self::discovery( $wp );
		}
	}

	/**
	 * Extend NodeInfo data
	 *
	 * @param array  $nodeinfo NodeInfo data
	 * @param string           The NodeInfo Version
	 *
	 * @return array           The extended array
	 */
	public static function add_nodeinfo_data( $nodeinfo, $version ) {
		if ( version_compare( $version, '2.0', '>=' ) ) {
			$nodeinfo['protocols'][] = 'activitypub';
		} else {
			$nodeinfo['protocols']['inbound'][]  = 'activitypub';
			$nodeinfo['protocols']['outbound'][] = 'activitypub';
		}

		return $nodeinfo;
	}

	/**
	 * Extend NodeInfo2 data
	 *
	 * @param  array $nodeinfo NodeInfo2 data
	 *
	 * @return array           The extended array
	 */
	public static function add_nodeinfo2_data( $nodeinfo ) {
		$nodeinfo['protocols'][] = 'activitypub';

		return $nodeinfo;
	}
}
