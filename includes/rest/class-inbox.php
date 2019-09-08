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
		add_action( 'rest_api_init', array( '\Activitypub\Rest\Inbox', 'register_routes' ) );
		//add_filter( 'rest_pre_serve_request', array( '\Activitypub\Rest\Inbox', 'serve_request' ), 11, 4 );
		add_action( 'activitypub_inbox_follow', array( '\Activitypub\Rest\Inbox', 'handle_follow' ), 10, 2 );
		add_action( 'activitypub_inbox_unfollow', array( '\Activitypub\Rest\Inbox', 'handle_unfollow' ), 10, 2 );
		//add_action( 'activitypub_inbox_like', array( '\Activitypub\Rest\Inbox', 'handle_reaction' ), 10, 2 );
		//add_action( 'activitypub_inbox_announce', array( '\Activitypub\Rest\Inbox', 'handle_reaction' ), 10, 2 );
		add_action( 'activitypub_inbox_create', array( '\Activitypub\Rest\Inbox', 'handle_create' ), 10, 2 );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		register_rest_route(
			'activitypub/1.0', '/inbox', array(
				array(
					'methods'  => \WP_REST_Server::EDITABLE,
					'callback' => array( '\Activitypub\Rest\Inbox', 'global_inbox' ),
				),
			)
		);

		register_rest_route(
			'activitypub/1.0', '/users/(?P<id>\d+)/inbox', array(
				array(
					'methods'  => \WP_REST_Server::EDITABLE,
					'callback' => array( '\Activitypub\Rest\Inbox', 'user_inbox' ),
					'args'     => self::request_parameters(),
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
		if ( '/activitypub' !== substr( $request->get_route(), 0, 12 ) ) {
			return $served;
		}

		if ( 'POST' !== $request->get_method() ) {
			return $served;
		}

		$signature = $request->get_header( 'signature' );

		if ( ! $signature ) {
			return $served;
		}

		$headers = $request->get_headers();

		//\Activitypub\Signature::verify_signature( $headers, $key );

		return $served;
	}

	/**
	 * Renders the user-inbox
	 *
	 * @param  WP_REST_Request   $request
	 *
	 * @return WP_REST_Response
	 */
	public static function user_inbox( $request ) {
		$author_id = $request->get_param( 'id' );
		$author    = get_user_by( 'ID', $author_id );

		$data = json_decode( $request->get_body(), true );

		$type = 'create';
		if ( ! empty( $data['type'] ) ) {
			$type = strtolower( $data['type'] );
		}

		if ( ! is_array( $data ) || ! array_key_exists( 'type', $data ) ) {
			return new \WP_Error( 'rest_invalid_data', __( 'Invalid payload', 'activitypub' ), array( 'status' => 422 ) );
		}

		do_action( 'activitypub_inbox', $data, $author_id, $type );
		do_action( "activitypub_inbox_{$type}", $data, $author_id );

		return new \WP_REST_Response( array(), 202 );
	}

	/**
	 * The shared inbox
	 *
	 * @param  [type] $request [description]
	 *
	 * @return WP_Error not yet implemented
	 */
	public static function global_inbox( $request ) {
		// Create the response object
		return new \WP_Error( 'rest_not_implemented', __( 'This method is not yet implemented', 'activitypub' ), array( 'status' => 501 ) );
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function request_parameters() {
		$params = array();

		$params['page'] = array(
			'type' => 'integer',
		);

		$params['id'] = array(
			'required' => true,
			'type' => 'integer',
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
		if ( ! array_key_exists( 'actor', $object ) ) {
			return new \WP_Error( 'activitypub_no_actor', __( 'No "Actor" found', 'activitypub' ), $metadata );
		}

		// save follower
		\Activitypub\Db\Followers::add_follower( $object['actor'], $user_id );

		// get inbox
		$inbox = \Activitypub\get_inbox_by_actor( $object['actor'] );

		// send "Accept" activity
		$activity = new \Activitypub\Activity( 'Accept', \Activitypub\Activity::TYPE_SIMPLE );
		$activity->set_object( $object );
		$activity->set_actor( get_author_posts_url( $user_id ) );
		$activity->set_to( $object['actor'] );
		$activity->set_id( get_author_posts_url( $user_id ) . '#follow' . preg_replace( '~^https?://~', '', $object['actor'] ) );

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
		if ( ! array_key_exists( 'actor', $object ) ) {
			return new \WP_Error( 'activitypub_no_actor', __( 'No "Actor" found', 'activitypub' ), $metadata );
		}

		\Activitypub\Db\Followers::remove_follower( $object['actor'], $user_id );
	}

	/**
	 * Handles "Reaction" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_reaction( $object, $user_id ) {
		if ( ! array_key_exists( 'actor', $object ) ) {
			return new \WP_Error( 'activitypub_no_actor', __( 'No "Actor" found', 'activitypub' ), $metadata );
		}

		$meta = \Activitypub\get_remote_metadata_by_actor( $object['actor'] );

		$commentdata = array(
			'comment_post_ID' => url_to_postid( $object['object'] ),
			'comment_author' => esc_attr( $meta['name'] ),
			'comment_author_email' => '',
			'comment_author_url' => esc_url_raw( $object['actor'] ),
			'comment_content' => esc_url_raw( $object['actor'] ),
			'comment_type' => esc_attr( strtolower( $object['type'] ) ),
			'comment_parent' => 0,
			'comment_meta' => array(
				'source_url' => esc_url_raw( $object['id'] ),
				'avatar_url' => esc_url_raw( $meta['icon']['url'] ),
				'protocol' => 'activitypub',
			),
		);

		// disable flood control
		remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		$state = wp_new_comment( $commentdata, true );

		// re-add flood control
		add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
	}

	/**
	 * Handles "Create" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_create( $object, $user_id ) {
		if ( ! array_key_exists( 'actor', $object ) ) {
			return new \WP_Error( 'activitypub_no_actor', __( 'No "Actor" found', 'activitypub' ), $metadata );
		}

		$meta = \Activitypub\get_remote_metadata_by_actor( $object['actor'] );

		$commentdata = array(
			'comment_post_ID' => url_to_postid( $object['object']['inReplyTo'] ),
			'comment_author' => esc_attr( $meta['name'] ),
			'comment_author_url' => esc_url_raw( $object['actor'] ),
			'comment_content' => wp_filter_kses( $object['object']['content'] ),
			'comment_type' => '',
			'comment_author_email' => '',
			'comment_parent' => 0,
			'comment_meta' => array(
				'source_url' => esc_url_raw( $object['object']['url'] ),
				'avatar_url' => esc_url_raw( $meta['icon']['url'] ),
				'protocol' => 'activitypub',
			),
		);

		// disable flood control
		remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		$state = wp_new_comment( $commentdata, true );

		// re-add flood control
		add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
	}
}
