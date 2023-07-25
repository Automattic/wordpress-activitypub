<?php
namespace Activitypub\Rest;

use WP_Error;
use WP_REST_Server;
use WP_REST_Response;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Users as User_Collection;

use function Activitypub\get_context;
use function Activitypub\url_to_authorid;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\get_remote_metadata_by_actor;

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
		\add_action( 'rest_api_init', array( self::class, 'register_routes' ) );

		\add_action( 'activitypub_inbox_create', array( self::class, 'handle_create' ), 10, 2 );
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
					'methods'             => WP_REST_Server::EDITABLE,
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
					'methods'             => WP_REST_Server::EDITABLE,
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
		$json = \apply_filters( 'activitypub_inbox_array', $json );

		/*
		 * Action triggerd after the ActivityPub profile has been created and sent to the client
		 */
		\do_action( 'activitypub_inbox_post' );

		$response = new WP_REST_Response( $json, 200 );

		$response->header( 'Content-Type', 'application/activity+json' );

		return $response;
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

		$data = $request->get_params();
		$type = $request->get_param( 'type' );
		$type = \strtolower( $type );

		\do_action( 'activitypub_inbox', $data, $user->get__id(), $type );
		\do_action( "activitypub_inbox_{$type}", $data, $user->get__id() );

		return new WP_REST_Response( array(), 202 );
	}

	/**
	 * The shared inbox
	 *
	 * @param  WP_REST_Request   $request
	 *
	 * @return WP_REST_Response
	 */
	public static function shared_inbox_post( $request ) {
		$data = $request->get_params();
		$type = $request->get_param( 'type' );
		$users = self::extract_recipients( $data );

		if ( ! $users ) {
			return new WP_Error(
				'rest_invalid_param',
				\__( 'No recipients found', 'activitypub' ),
				array(
					'status' => 404,
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
			$type = \strtolower( $type );

			\do_action( 'activitypub_inbox', $data, $user->ID, $type );
			\do_action( "activitypub_inbox_{$type}", $data, $user->ID );
		}

		return new WP_REST_Response( array(), 202 );
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
				if ( ! \is_string( $param ) ) {
					$param = $param['id'];
				}
				return \esc_url_raw( $param );
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
				if ( ! \is_string( $param ) ) {
					$param = $param['id'];
				}
				return \esc_url_raw( $param );
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
	 * Handles "Reaction" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_reaction( $object, $user_id ) {
		$meta = get_remote_metadata_by_actor( $object['actor'] );

		$comment_post_id = \url_to_postid( $object['object'] );

		// save only replys and reactions
		if ( ! $comment_post_id ) {
			return false;
		}

		$commentdata = array(
			'comment_post_ID' => $comment_post_id,
			'comment_author' => \esc_attr( $meta['name'] ),
			'comment_author_email' => '',
			'comment_author_url' => \esc_url_raw( $object['actor'] ),
			'comment_content' => \esc_url_raw( $object['actor'] ),
			'comment_type' => \esc_attr( \strtolower( $object['type'] ) ),
			'comment_parent' => 0,
			'comment_meta' => array(
				'source_url' => \esc_url_raw( $object['id'] ),
				'avatar_url' => \esc_url_raw( $meta['icon']['url'] ),
				'protocol' => 'activitypub',
			),
		);

		// disable flood control
		\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		// do not require email for AP entries
		\add_filter( 'pre_option_require_name_email', '__return_false' );

		$state = \wp_new_comment( $commentdata, true );

		\remove_filter( 'pre_option_require_name_email', '__return_false' );

		// re-add flood control
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
	}

	/**
	 * Handles "Create" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_create( $object, $user_id ) {
		$meta = get_remote_metadata_by_actor( $object['actor'] );

		if ( ! isset( $object['object']['inReplyTo'] ) ) {
			return;
		}

		// check if Activity is public or not
		if ( ! self::is_activity_public( $object ) ) {
			// @todo maybe send email
			return;
		}

		$comment_post_id = \url_to_postid( $object['object']['inReplyTo'] );

		// save only replys and reactions
		if ( ! $comment_post_id ) {
			return false;
		}

		$commentdata = array(
			'comment_post_ID' => $comment_post_id,
			'comment_author' => \esc_attr( $meta['name'] ),
			'comment_author_url' => \esc_url_raw( $object['actor'] ),
			'comment_content' => \wp_filter_kses( $object['object']['content'] ),
			'comment_type' => '',
			'comment_author_email' => '',
			'comment_parent' => 0,
			'comment_meta' => array(
				'source_url' => \esc_url_raw( $object['object']['url'] ),
				'avatar_url' => \esc_url_raw( $meta['icon']['url'] ),
				'protocol' => 'activitypub',
			),
		);

		// disable flood control
		\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		// do not require email for AP entries
		\add_filter( 'pre_option_require_name_email', '__return_false' );

		$state = \wp_new_comment( $commentdata, true );

		\remove_filter( 'pre_option_require_name_email', '__return_false' );

		// re-add flood control
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
	}

	/**
	 * Extract recipient URLs from Activity object
	 *
	 * @param  array $data
	 *
	 * @return array The list of user URLs
	 */
	public static function extract_recipients( $data ) {
		$recipient_items = array();

		foreach ( array( 'to', 'bto', 'cc', 'bcc', 'audience' ) as $i ) {
			if ( array_key_exists( $i, $data ) ) {
				if ( is_array( $data[ $i ] ) ) {
					$recipient = $data[ $i ];
				} else {
					$recipient = array( $data[ $i ] );
				}
				$recipient_items = array_merge( $recipient_items, $recipient );
			}

			if ( array_key_exists( $i, $data['object'] ) ) {
				if ( is_array( $data['object'][ $i ] ) ) {
					$recipient = $data['object'][ $i ];
				} else {
					$recipient = array( $data['object'][ $i ] );
				}
				$recipient_items = array_merge( $recipient_items, $recipient );
			}
		}

		$recipients = array();

		// flatten array
		foreach ( $recipient_items as $recipient ) {
			if ( is_array( $recipient ) ) {
				// check if recipient is an object
				if ( array_key_exists( 'id', $recipient ) ) {
					$recipients[] = $recipient['id'];
				}
			} else {
				$recipients[] = $recipient;
			}
		}

		return array_unique( $recipients );
	}

	/**
	 * Get local user recipients
	 *
	 * @param  array $data
	 *
	 * @return array The list of local users
	 */
	public static function get_recipients( $data ) {
		$recipients = self::extract_recipients( $data );
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

	/**
	 * Check if passed Activity is Public
	 *
	 * @param array $data
	 * @return boolean
	 */
	public static function is_activity_public( $data ) {
		$recipients = self::extract_recipients( $data );

		return in_array( 'https://www.w3.org/ns/activitystreams#Public', $recipients, true );
	}
}
