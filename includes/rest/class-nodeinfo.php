<?php
namespace Activitypub\Rest;

use WP_REST_Response;

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
		self::register_routes();
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
	 * @param  WP_REST_Request   $request
	 *
	 * @return WP_REST_Response
	 */
	public static function nodeinfo( $request ) {
		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_rest_nodeinfo_pre' );

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

		if ( is_countable( $users ) ) {
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

		return new WP_REST_Response( $nodeinfo, 200 );
	}

	/**
	 * Render NodeInfo file
	 *
	 * @param  WP_REST_Request   $request
	 *
	 * @return WP_REST_Response
	 */
	public static function nodeinfo2( $request ) {
		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_rest_nodeinfo2_pre' );

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

		if ( is_countable( $users ) ) {
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

		return new WP_REST_Response( $nodeinfo, 200 );
	}

	/**
	 * Render NodeInfo discovery file
	 *
	 * @param  WP_REST_Request   $request
	 *
	 * @return WP_REST_Response
	 */
	public static function discovery( $request ) {
		$discovery = array();
		$discovery['links'] = array(
			array(
				'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
				'href' => get_rest_url_by_path( 'nodeinfo' ),
			),
		);

		return new \WP_REST_Response( $discovery, 200 );
	}
}
