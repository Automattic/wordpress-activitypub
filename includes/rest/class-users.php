<?php
namespace Activitypub\Rest;

use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use Activitypub\Webfinger;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Users as User_Collection;

use function Activitypub\is_activitypub_request;

/**
 * ActivityPub Followers REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#followers
 */
class Users {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		self::register_routes();
		add_filter( 'activitypub_defer_signature_verification', array( self::class, 'no_signature_here' ), 10, 2 );
	}

	/**
	 * We require signatures requests from external actors, but this route is used in wp-admin
	 * to update the user's profile.
	 *
	 * @param bool $defer
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public static function no_signature_here( $defer, $request ) {
		if ( 'PUT' !== $request->get_method() || ! $request->has_param( 'user_id' ) ) {
			return $defer;
		}

		$expected_route = sprintf(
			'/%s/users/%d',
			ACTIVITYPUB_REST_NAMESPACE,
			$request->get_param( 'user_id' )
		);

		if ( $request->get_route() === $expected_route ) {
			return true;
		}

		return $defer;
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/users/(?P<user_id>[\w\-\.]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get' ),
					'args'                => self::request_parameters(),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update' ),
					'args'                => self::update_request_parameters(),
					'permission_callback' => array( self::class, 'user_can_update' ),
				),
			)
		);

		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/users/(?P<user_id>[\w\-\.]+)/remote-follow',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'remote_follow_get' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'resource' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	public static function user_can_update() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle POST request
	 *
	 * @param  WP_REST_Request   $request
	 *
	 * @return WP_REST_Response
	 */
	public static function update( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = User_Collection::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$params = $request->get_params();

		if ( ! empty( $params['header'] ) ) {
			$user->set_image( $params['header'] );
		}

		if ( ! empty( $params['avatar'] ) ) {
			// check for empty avatar and that $user has set_icon_id method
			if ( ! empty( $params['avatarId'] ) && method_exists( $user, 'set_icon_id' ) ) {
				$user->set_icon_id( $params['avatarId'] );
			} else {
				$user->set_icon( $params['avatar'] );
			}
		}

		if ( ! empty( $params['name'] ) ) {
			$user->set_name( $params['name'] );
		}

		if ( ! empty( $params['summary'] ) ) {
			$user->set_summary( $params['summary'] );
		}

		return self::get_user( $user );
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

		// redirect to canonical URL if it is not an ActivityPub request
		if ( ! is_activitypub_request() ) {
			header( 'Location: ' . $user->get_canonical_url(), true, 301 );
			exit;
		}

		return self::get_user( $user );
	}

	/**
	 * Convert a user to a WP_REST_Response
	 *
	 * @param  User $user
	 *
	 * @return WP_REST_Response
	 */
	private static function get_user( $user ) {
		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_rest_users_pre' );

		$user->set_context( Activity::JSON_LD_CONTEXT );

		$json = $user->to_array();

		$response = new WP_REST_Response( $json, 200 );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );

		return $response;
	}


	/**
	 * Endpoint for remote follow UI/Block
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return void|string The URL to the remote follow page
	 */
	public static function remote_follow_get( WP_REST_Request $request ) {
		$resource = $request->get_param( 'resource' );
		$user_id  = $request->get_param( 'user_id' );
		$user     = User_Collection::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$template = Webfinger::get_remote_follow_endpoint( $resource );

		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$resource = $user->get_webfinger();
		$url      = str_replace( '{uri}', $resource, $template );

		return new WP_REST_Response(
			array( 'url' => $url ),
			200
		);
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function request_parameters() {
		$params = array();

		$params['page'] = array(
			'type' => 'string',
		);

		$params['user_id'] = array(
			'required' => true,
			'type'     => 'string',
		);

		return $params;
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function update_request_parameters() {
		return array(
			'user_id' => array(
				'required' => true,
				'type'     => 'string',
			),
			'avatar' => array(
				'type' => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'avatarId' => array(
				'type' => 'number',
				'sanitize_callback' => 'absint',
			),
			'header' => array(
				'type' => 'string',
				'sanitize_callback' => 'esc_url_raw',
			),
			'name' => array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'summary' => array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}
}
