<?php
namespace Activitypub\Rest;

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
		\add_action( 'rest_api_init', array( '\Activitypub\Rest\Inbox', 'register_routes' ) );
		\add_filter( 'rest_pre_serve_request', array( '\Activitypub\Rest\Inbox', 'serve_request' ), 11, 4 );
		\add_action( 'activitypub_inbox_follow', array( '\Activitypub\Rest\Inbox', 'handle_follow' ), 10, 2 );
		\add_action( 'activitypub_inbox_undo', array( '\Activitypub\Rest\Inbox', 'handle_unfollow' ), 10, 2 );
		//\add_action( 'activitypub_inbox_like', array( '\Activitypub\Rest\Inbox', 'handle_reaction' ), 10, 2 );
		//\add_action( 'activitypub_inbox_announce', array( '\Activitypub\Rest\Inbox', 'handle_reaction' ), 10, 2 );
		\add_action( 'activitypub_inbox_create', array( '\Activitypub\Rest\Inbox', 'handle_create' ), 10, 2 );
		\add_action( 'activitypub_inbox_update', array( '\Activitypub\Rest\Inbox', 'handle_update' ), 10, 2 );
		\add_action( 'activitypub_inbox_delete', array( '\Activitypub\Rest\Inbox', 'handle_delete' ), 10, 2 );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			'activitypub/1.0',
			'/inbox',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( '\Activitypub\Rest\Inbox', 'shared_inbox_post' ),
					'args'                => self::shared_inbox_post_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);

		\register_rest_route(
			'activitypub/1.0',
			'/users/(?P<user_id>\d+)/inbox',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( '\Activitypub\Rest\Inbox', 'user_inbox_post' ),
					'args'                => self::user_inbox_post_parameters(),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( '\Activitypub\Rest\Inbox', 'user_inbox_get' ),
					'args'                => self::user_inbox_get_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Hooks into the REST API request to verify the signature.
	 *
	 * @param bool                      $served  Whether the request has already been served.
	 * @param WP_HTTP_ResponseInterface $result  Result to send to the client. Usually a WP_REST_Response.
	 * @param WP_REST_Request           $request Request used to generate the response.
	 * @param WP_REST_Server            $server  Server instance.
	 *
	 * @return true
	 */
	public static function serve_request( $served, $result, $request, $server ) {
		if ( '/activitypub' !== \substr( $request->get_route(), 0, 12 ) ) {
			return $served;
		}

		$signature = $request->get_header( 'signature' );

		if ( ! $signature ) {
			return $served;
		}

		$headers = $request->get_headers();

		// verify signature
		//\Activitypub\Signature::verify_signature( $headers, $key );

		return $served;
	}

	/**
	 * Renders the user-inbox
	 *
	 * @param  WP_REST_Request   $request
	 * @return WP_REST_Response
	 */
	public static function user_inbox_get( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$page = $request->get_param( 'page', 0 );

		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_inbox_pre' );

		$json = new \stdClass();

		$json->{'@context'} = \Activitypub\get_context();
		$json->id = \home_url( \add_query_arg( null, null ) );
		$json->generator = 'http://wordpress.org/?v=' . \get_bloginfo_rss( 'version' );
		$json->type = 'OrderedCollectionPage';
		$json->partOf = \get_rest_url( null, "/activitypub/1.0/users/$user_id/inbox" ); // phpcs:ignore

		$json->totalItems = 0; // phpcs:ignore

		$json->orderedItems = array(); // phpcs:ignore

		$json->first = $json->partOf; // phpcs:ignore

		// filter output
		$json = \apply_filters( 'activitypub_inbox_array', $json );

		/*
		 * Action triggerd after the ActivityPub profile has been created and sent to the client
		 */
		\do_action( 'activitypub_inbox_post' );

		$response = new \WP_REST_Response( $json, 200 );

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

		$data = $request->get_params();
		$type = $request->get_param( 'type' );
		$type = \strtolower( $type );

		\do_action( 'activitypub_inbox', $data, $user_id, $type );
		\do_action( "activitypub_inbox_{$type}", $data, $user_id );

		return new \WP_REST_Response( array(), 202 );
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
			return new \WP_Error(
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

		return new \WP_REST_Response( array(), 202 );
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
			'type' => 'integer',
			'validate_callback' => function( $param, $request, $key ) {
				return user_can( $param, 'publish_posts' );
			},
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
			'type' => 'integer',
			'validate_callback' => function( $param, $request, $key ) {
				return user_can( $param, 'publish_posts' );
			},
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
			//	return \strtolower( $param );
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
			//	return \strtolower( $param );
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
	 * Handles "Follow" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_follow( $object, $user_id ) {
		// save follower
		\Activitypub\Peer\Followers::add_follower( $object['actor'], $user_id );

		// get inbox
		$inbox = \Activitypub\get_inbox_by_actor( $object['actor'] );

		// send "Accept" activity
		$activity = new \Activitypub\Model\Activity( 'Accept', \Activitypub\Model\Activity::TYPE_SIMPLE );
		$activity->set_object( $object );
		$activity->set_actor( \get_author_posts_url( $user_id ) );
		$activity->set_to( $object['actor'] );
		$activity->set_id( \get_author_posts_url( $user_id ) . '#follow-' . \preg_replace( '~^https?://~', '', $object['actor'] ) );

		$activity = $activity->to_simple_json();

		$response = \Activitypub\safe_remote_post( $inbox, $activity, $user_id );
	}

	/**
	 * Handles "Unfollow" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_unfollow( $object, $user_id ) {
		if ( isset( $object['object'] ) && isset( $object['object']['type'] ) && 'Follow' === $object['object']['type'] ) {
			\Activitypub\Peer\Followers::remove_follower( $object['actor'], $user_id );
		}
	}

	/**
	 * Handles "Reaction" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_reaction( $object, $user_id ) {
		$meta = \Activitypub\get_remote_metadata_by_actor( $object['actor'] );

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
	 * Converts a new ActivityPub object to comment data suitable for creating a comment
	 *
	 * @param  array $object  The activity-object.
	 *
	 * @return array Comment data suitable for creating a comment.
	 */
	public static function convert_object_to_comment_data( $object ) {
		if ( ! isset( $object['object'] ) ) {
			return false;
		}

		// check if Activity is public or not
		if ( ! self::is_activity_public( $object ) ) {
			// @todo maybe send email
			return false;
		}

		$meta = \Activitypub\get_remote_metadata_by_actor( $object['actor'] );

		// Objects must have IDs
		if ( ! isset( $object['object']['id'] ) ) {
			\error_log( 'Comment provided without ID' );
			return;
		}
		$id = $object['object']['id'];

		// Only handle replies
		if ( ! isset( $object['object']['inReplyTo'] ) ) {
			return;
		}
		$in_reply_to = $object['object']['inReplyTo'];

		// Comment already exists
		if ( \Activitypub\object_id_to_comment( $id ) ) {
			return;
		}

		$parent_comment = \Activitypub\object_id_to_comment( $in_reply_to );

		// save only replies and reactions
		$comment_post_id = \Activitypub\object_to_post_id_by_field_name( $object, 'context' );
		if ( ! $comment_post_id ) {
			$comment_post_id = \Activitypub\object_to_post_id_by_field_name( $object, 'inReplyTo' );
		}
		if ( ! $comment_post_id ) {
			$comment_post_id = $parent_comment->comment_post_ID;
		}
		if ( ! $comment_post_id ) {
			return;
		}

		return array(
			'comment_post_ID' => $comment_post_id,
			'comment_author' => \esc_attr( $meta['name'] ),
			'comment_author_url' => \esc_url_raw( $object['actor'] ),
			'comment_content' => \wp_filter_kses( $object['object']['content'] ),
			'comment_type' => '',
			'comment_author_email' => '',
			'comment_parent' => $parent_comment ? $parent_comment->comment_ID : 0,
			'comment_meta' => array(
				'ap_object' => \serialize( $object ),
				'source_id' => \esc_url_raw( $id ),
				'source_url' => \esc_url_raw( $object['object']['url'] ),
				'avatar_url' => \esc_url_raw( $meta['icon']['url'] ),
				'protocol' => 'activitypub',
			),
		);
	}

	/**
	 * Handles "Create" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_create( $object, $user_id ) {
		$commentdata = self::convert_object_to_comment_data( $object );
		if ( ! $commentdata ) {
			return false;
		}

			// disable flood control
			\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

			// do not require email for AP entries
			\add_filter( 'pre_option_require_name_email', '__return_false' );

			$state = \wp_new_comment( $commentdata, true );

			\remove_filter( 'pre_option_require_name_email', '__return_false' );

			// re-add flood control
			\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
		}
	}

	/**
	 * Handles "Update" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_update( $object, $user_id ) {
		$meta = \Activitypub\get_remote_metadata_by_actor( $object['actor'] );

		//Determine comment_ID
		$object_comment_id = \Activitypub\url_to_commentid( \esc_url_raw( $object['object']['id'] ) );
		if ( ! is_null( $object_comment_id ) ) {

			//found a local comment id
			$commentdata = \get_comment( $object_comment_id, ARRAY_A );
			$commentdata['comment_author'] = \esc_attr( $meta['name'] ? $meta['name'] : $meta['preferredUsername'] );
			$commentdata['comment_content'] = \wp_filter_kses( $object['object']['content'] );
			$commentdata['comment_meta']['avatar_url'] = \esc_url_raw( $meta['icon']['url'] );
			$commentdata['comment_meta']['ap_published'] = \wp_date( 'Y-m-d H:i:s', strtotime( $object['object']['published'] ) );
			$commentdata['comment_meta']['ap_last_modified'] = $object['object']['updated'];
			$commentdata['comment_meta']['ap_object'] = \serialize( $object );

			// disable flood control
			\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

			// do not require email for AP entries
			\add_filter( 'pre_option_require_name_email', '__return_false' );

			$state = \wp_update_comment( $commentdata, true );

			\remove_filter( 'pre_option_require_name_email', '__return_false' );

			// re-add flood control
			\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
		}
	}

	/**
	 * Handles "Delete" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_delete( $object, $user_id ) {
		if ( ! isset( $object['object']['id'] ) ) {
			return;
		}
		//Determine comment_ID
		$object_comment_id = \Activitypub\url_to_commentid( \esc_url_raw( $object['object']['id'] ) );
		if ( ! is_null( $object_comment_id ) ) {

			//found a local comment id
			$commentdata = \get_comment( $object_comment_id, ARRAY_A );

			// disable flood control
			\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

			// do not require email for AP entries
			\add_filter( 'pre_option_require_name_email', '__return_false' );

			// Should we trash or send back to moderation
			$state = \wp_trash_comment( $commentdata['comment_ID'], true );

			\remove_filter( 'pre_option_require_name_email', '__return_false' );

			// re-add flood control
			\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
		}
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
				$recipient_items = array_merge( $recipient_items, $data[ $i ] );
			}

			if ( array_key_exists( $i, $data['object'] ) ) {
				$recipient_items = array_merge( $recipient_items, $data[ $i ] );
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
			$user_id = \Activitypub\url_to_authorid( $recipient );

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
