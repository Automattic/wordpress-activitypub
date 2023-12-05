<?php
namespace Activitypub\Rest;

use WP_Error;
use WP_REST_Server;
use WP_REST_Response;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Users as User_Collection;

use function Activitypub\get_context;
use function Activitypub\object_to_uri;
use function Activitypub\url_to_authorid;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\get_remote_metadata_by_actor;
use function Activitypub\extract_recipients_from_activity;

/**
 * ActivityPub Inbox REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#inbox
 */
class Inbox {
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
			'/inbox',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'shared_inbox_post' ),
					'args'                => self::shared_inbox_post_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);

		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/users/(?P<user_id>[\w\-\.]+)/inbox',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'user_inbox_post' ),
					'args'                => self::user_inbox_post_parameters(),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'user_inbox_get' ),
					'args'                => self::user_inbox_get_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Renders the user-inbox
	 *
	 * @param  WP_REST_Request   $request
	 * @return WP_REST_Response
	 */
	public static function user_inbox_get( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = User_Collection::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$page = $request->get_param( 'page', 0 );

		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_rest_inbox_pre' );

		$json = new \stdClass();

		$json->{'@context'} = get_context();
		$json->id = get_rest_url_by_path( sprintf( 'users/%d/inbox', $user->get__id() ) );
		$json->generator = 'http://wordpress.org/?v=' . \get_bloginfo_rss( 'version' );
		$json->type = 'OrderedCollectionPage';
		$json->partOf = get_rest_url_by_path( sprintf( 'users/%d/inbox', $user->get__id() ) ); // phpcs:ignore

		$json->totalItems = 0; // phpcs:ignore

		$json->orderedItems = array(); // phpcs:ignore

		$json->first = $json->partOf; // phpcs:ignore

		// filter output
		$json = \apply_filters( 'activitypub_rest_inbox_array', $json );

		/*
		 * Action triggerd after the ActivityPub profile has been created and sent to the client
		 */
		\do_action( 'activitypub_inbox_post' );

		$rest_response = new WP_REST_Response( $json, 200 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );

		return $rest_response;
	}

	/**
	 * Handles user-inbox requests
	 *
	 * @param  WP_REST_Request   $request
	 *
	 * @return WP_REST_Response
	 */
	public static function user_inbox_post( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = User_Collection::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$data     = $request->get_json_params();
		$activity = Activity::init_from_array( $data );
		$type     = $request->get_param( 'type' );
		$type     = \strtolower( $type );

		\do_action( 'activitypub_inbox', $data, $user->get__id(), $type, $activity );
		\do_action( "activitypub_inbox_{$type}", $data, $user->get__id(), $activity );

		$rest_response = new WP_REST_Response( array(), 202 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );

		return $rest_response;
	}

	/**
	 * The shared inbox
	 *
	 * @param  WP_REST_Request   $request
	 *
	 * @return WP_REST_Response
	 */
	public static function shared_inbox_post( $request ) {
		$data     = $request->get_json_params();
		$activity = Activity::init_from_array( $data );
		$type     = $request->get_param( 'type' );
		$users    = self::get_recipients( $data );

		if ( ! $users ) {
			return new WP_Error(
				'rest_invalid_param',
				\__( 'No recipients found', 'activitypub' ),
				array(
					'status' => 400,
					'params' => array(
						'to' => \__( 'Please check/validate "to" field', 'activitypub' ),
						'bto' => \__( 'Please check/validate "bto" field', 'activitypub' ),
						'cc' => \__( 'Please check/validate "cc" field', 'activitypub' ),
						'bcc' => \__( 'Please check/validate "bcc" field', 'activitypub' ),
						'audience' => \__( 'Please check/validate "audience" field', 'activitypub' ),
					),
				)
			);
		}

		foreach ( $users as $user ) {
			$user = User_Collection::get_by_various( $user );

			if ( is_wp_error( $user ) ) {
				continue;
			}

			$type = \strtolower( $type );

			\do_action( 'activitypub_inbox', $data, $user->ID, $type, $activity );
			\do_action( "activitypub_inbox_{$type}", $data, $user->ID, $activity );
		}

		$rest_response = new WP_REST_Response( array(), 202 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );

		return $rest_response;
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function user_inbox_get_parameters() {
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

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function user_inbox_post_parameters() {
		$params = array();

		$params['page'] = array(
			'type' => 'integer',
		);

		$params['user_id'] = array(
			'required' => true,
			'type' => 'string',
		);

		$params['id'] = array(
			'required' => true,
			'sanitize_callback' => 'esc_url_raw',
		);

		$params['actor'] = array(
			'required' => true,
			'sanitize_callback' => function( $param, $request, $key ) {
				return object_to_uri( $param );
			},
		);

		$params['type'] = array(
			'required' => true,
			//'type' => 'enum',
			//'enum' => array( 'Create' ),
			//'sanitize_callback' => function( $param, $request, $key ) {
			//  return \strtolower( $param );
			//},
		);

		$params['object'] = array(
			'required' => true,
		);

		return $params;
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function shared_inbox_post_parameters() {
		$params = array();

		$params['page'] = array(
			'type' => 'integer',
		);

		$params['id'] = array(
			'required' => true,
			'type' => 'string',
			'sanitize_callback' => 'esc_url_raw',
		);

		$params['actor'] = array(
			'required' => true,
			//'type' => array( 'object', 'string' ),
			'sanitize_callback' => function( $param, $request, $key ) {
				return object_to_uri( $param );
			},
		);

		$params['type'] = array(
			'required' => true,
			//'type' => 'enum',
			//'enum' => array( 'Create' ),
			//'sanitize_callback' => function( $param, $request, $key ) {
			//  return \strtolower( $param );
			//},
		);

		$params['object'] = array(
			'required' => true,
			//'type' => 'object',
		);

		$params['to'] = array(
			'required' => false,
			'sanitize_callback' => function( $param, $request, $key ) {
				if ( \is_string( $param ) ) {
					$param = array( $param );
				}

				return $param;
			},
		);

		$params['cc'] = array(
			'sanitize_callback' => function( $param, $request, $key ) {
				if ( \is_string( $param ) ) {
					$param = array( $param );
				}

				return $param;
			},
		);

		$params['bcc'] = array(
			'sanitize_callback' => function( $param, $request, $key ) {
				if ( \is_string( $param ) ) {
					$param = array( $param );
				}

				return $param;
			},
		);

		return $params;
	}

	/**
	 * Get local user recipients
	 *
	 * @param  array $data
	 *
	 * @return array The list of local users
	 */
	public static function get_recipients( $data ) {
		$recipients = extract_recipients_from_activity( $data );
		$users = array();

		foreach ( $recipients as $recipient ) {
			$user_id = url_to_authorid( $recipient );

			$user = get_user_by( 'id', $user_id );

			if ( $user ) {
				$users[] = $user;
			}
		}

		return $users;
	}
}
