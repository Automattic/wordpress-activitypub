<?php
/**
 * Inbox REST-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use WP_REST_Server;
use WP_REST_Response;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;

use function Activitypub\get_context;
use function Activitypub\url_to_authorid;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\get_masked_wp_version;
use function Activitypub\extract_recipients_from_activity;

/**
 * ActivityPub Inbox REST-Class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#inbox
 */
class Inbox {
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
			'/inbox',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'shared_inbox_post' ),
					'args'                => self::shared_inbox_post_parameters(),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
				),
			)
		);

		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/(users|actors)/(?P<user_id>[\w\-\.]+)/inbox',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'user_inbox_post' ),
					'args'                => self::user_inbox_post_parameters(),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'user_inbox_get' ),
					'args'                => self::user_inbox_get_parameters(),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
				),
			)
		);
	}

	/**
	 * Renders the user-inbox.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return WP_REST_Response|\WP_Error The response object or WP_Error.
	 */
	public static function user_inbox_get( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		/**
		 * Fires before the ActivityPub inbox is created and sent to the client.
		 */
		\do_action( 'activitypub_rest_inbox_pre' );

		$json = new \stdClass();

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$json->{'@context'} = get_context();
		$json->id           = get_rest_url_by_path( sprintf( 'actors/%d/inbox', $user->get__id() ) );
		$json->generator    = 'http://wordpress.org/?v=' . get_masked_wp_version();
		$json->type         = 'OrderedCollectionPage';
		$json->partOf       = get_rest_url_by_path( sprintf( 'actors/%d/inbox', $user->get__id() ) );
		$json->totalItems   = 0;
		$json->orderedItems = array();
		$json->first        = $json->partOf;
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		/**
		 * Filters the ActivityPub inbox data before it is sent to the client.
		 *
		 * @param array $json The ActivityPub inbox array.
		 */
		$json = \apply_filters( 'activitypub_rest_inbox_array', $json );

		/**
		 * Fires after the ActivityPub inbox has been created and sent to the client.
		 */
		\do_action( 'activitypub_inbox_post' );

		$rest_response = new WP_REST_Response( $json, 200 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );

		return $rest_response;
	}

	/**
	 * Handles user-inbox requests.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response|\WP_Error The response object or WP_Error.
	 */
	public static function user_inbox_post( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$data     = $request->get_json_params();
		$activity = Activity::init_from_array( $data );
		$type     = $request->get_param( 'type' );
		$type     = \strtolower( $type );

		/**
		 * ActivityPub inbox action.
		 *
		 * @param array    $data     The data array.
		 * @param int|null $user_id  The user ID.
		 * @param string   $type     The type of the activity.
		 * @param Activity $activity The Activity object.
		 */
		\do_action( 'activitypub_inbox', $data, $user->get__id(), $type, $activity );

		/**
		 * ActivityPub inbox action for specific activity types.
		 *
		 * @param array    $data     The data array.
		 * @param int|null $user_id  The user ID.
		 * @param Activity $activity The Activity object.
		 */
		\do_action( "activitypub_inbox_{$type}", $data, $user->get__id(), $activity );

		$rest_response = new WP_REST_Response( array(), 202 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );

		return $rest_response;
	}

	/**
	 * The shared inbox.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public static function shared_inbox_post( $request ) {
		$data     = $request->get_json_params();
		$activity = Activity::init_from_array( $data );
		$type     = $request->get_param( 'type' );
		$type     = \strtolower( $type );

		/**
		 * ActivityPub inbox action.
		 *
		 * @param array    $data     The data array.
		 * @param int|null $user_id  The user ID.
		 * @param string   $type     The type of the activity.
		 * @param Activity $activity The Activity object.
		 */
		\do_action( 'activitypub_inbox', $data, null, $type, $activity );

		/**
		 * ActivityPub inbox action for specific activity types.
		 *
		 * @param array    $data     The data array.
		 * @param int|null $user_id  The user ID.
		 * @param Activity $activity The Activity object.
		 */
		\do_action( "activitypub_inbox_{$type}", $data, null, $activity );

		$rest_response = new WP_REST_Response( array(), 202 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );

		return $rest_response;
	}

	/**
	 * The supported parameters.
	 *
	 * @return array List of parameters.
	 */
	public static function user_inbox_get_parameters() {
		$params = array();

		$params['page'] = array(
			'type' => 'integer',
		);

		return $params;
	}

	/**
	 * The supported parameters.
	 *
	 * @return array List of parameters.
	 */
	public static function user_inbox_post_parameters() {
		$params = array();

		$params['id'] = array(
			'required'          => true,
			'sanitize_callback' => 'esc_url_raw',
		);

		$params['actor'] = array(
			'required'          => true,
			'sanitize_callback' => '\Activitypub\object_to_uri',
		);

		$params['type'] = array(
			'required' => true,
		);

		$params['object'] = array(
			'required'          => true,
			'validate_callback' => function ( $param, $request, $key ) {
				/**
				 * Filter the ActivityPub object validation.
				 *
				 * @param bool   $validate The validation result.
				 * @param array  $param    The object data.
				 * @param object $request  The request object.
				 * @param string $key      The key.
				 */
				return apply_filters( 'activitypub_validate_object', true, $param, $request, $key );
			},
		);

		return $params;
	}

	/**
	 * The supported parameters.
	 *
	 * @return array List of parameters.
	 */
	public static function shared_inbox_post_parameters() {
		$params = self::user_inbox_post_parameters();

		$params['to'] = array(
			'required'          => false,
			'sanitize_callback' => function ( $param ) {
				if ( \is_string( $param ) ) {
					$param = array( $param );
				}

				return $param;
			},
		);

		$params['cc'] = array(
			'sanitize_callback' => function ( $param ) {
				if ( \is_string( $param ) ) {
					$param = array( $param );
				}

				return $param;
			},
		);

		$params['bcc'] = array(
			'sanitize_callback' => function ( $param ) {
				if ( \is_string( $param ) ) {
					$param = array( $param );
				}

				return $param;
			},
		);

		return $params;
	}

	/**
	 * Get local user recipients.
	 *
	 * @param array $data The data array.
	 *
	 * @return array The list of local users.
	 */
	public static function get_recipients( $data ) {
		$recipients = extract_recipients_from_activity( $data );
		$users      = array();

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
