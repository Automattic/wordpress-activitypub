<?php
namespace Activitypub\Rest;

/**
 * ActivityPub Outbox REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#outbox
 */
class Outbox {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'rest_api_init', array( '\Activitypub\Rest\Outbox', 'register_routes' ) );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			'activitypub/1.0', '/users/(?P<id>\d+)/outbox', array(
				array(
					'methods'  => \WP_REST_Server::READABLE,
					'callback' => array( '\Activitypub\Rest\Outbox', 'user_outbox' ),
					'args'     => self::request_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);
		
		\register_rest_route(
			'activitypub/1.0', '/post/(?P<id>\d+)/replies', array(
				array(
					'methods'  => \WP_REST_Server::READABLE,
					'callback' => array( '\Activitypub\Rest\Outbox', 'post_replies' ),
					'args'     => self::request_parameters(),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Renders the user-outbox
	 *
	 * @param  WP_REST_Request   $request
	 * @return WP_REST_Response
	 */
	public static function user_outbox( $request ) {
		$user_id = $request->get_param( 'id' );
		$author  = \get_user_by( 'ID', $user_id );
// /with_replies include comments
		if ( ! $author ) {
			return new \WP_Error( 'rest_invalid_param', __( 'User not found', 'activitypub' ), array(
				'status' => 404,
				'params' => array(
					'user_id' => \__( 'User not found', 'activitypub' ),
				),
			) );
		}

		$page = $request->get_param( 'page', 0 );
		//$page = $request->get_param( 'page', true );
		error_log('outbox');
		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_outbox_pre' );

		$json = new \stdClass();

		$json->{'@context'} = \Activitypub\get_context();
		$json->id = \home_url( \add_query_arg( null, null ) );
		$json->generator = 'http://wordpress.org/?v=' . \get_bloginfo_rss( 'version' );
		$json->actor = \get_author_posts_url( $user_id );
		$json->type = 'OrderedCollectionPage';
		$json->partOf = \get_rest_url( null, "/activitypub/1.0/users/$user_id/outbox" ); // phpcs:ignore

		$count_posts = \count_user_posts( $user_id );// get allowed post types, public_only = true
		$json->totalItems = \intval( $count_posts ); // phpcs:ignore

		$posts = \get_posts( array(
			'posts_per_page' => 10,
			'author' => $user_id,
			'offset' => $page * 10,
		) );
		$comments = \get_comments(
			array(
				'number' => 10,
				'paged' => true,
				'user_id' => $user_id,
				'status' => 'approved',
				'offset' => $page * 10,
			) 
		);
		//error_log('$posts: ' . print_r($posts, true ) );
// add_query_arg( array(
//     'page' => 'true',
//     'max_id' => $posts[9]->ID,//nextß
//     'min_id' => $posts[0]->ID,//prev
// ), $json->partOf );

		//if ( ( \ceil ( $json->totalItems / 10 ) ) - 1 > $page ) { // phpcs:ignore
			// $json->next  = add_query_arg( array(
			// 	'max_id' => $posts[9]->ID,//nextß
			// 	'page' => 'true',
			// ), $json->partOf );
			// $json->prev  = add_query_arg( array(
			// 	'min_id' => $posts[0]->ID,//prev
			// 	'page' => 'true',
			// ), $json->partOf );
		//}
		// if ( ( \ceil ( $json->totalItems / 10 ) ) - 1 > $page ) { // phpcs:ignore
		// 	$json->next  = \add_query_arg( 'page', ++$page, $json->partOf ); // phpcs:ignore
		// }
		$json->first = \add_query_arg( 'page', 0, $json->partOf ); // phpcs:ignore
		$json->last  = \add_query_arg( 'page', ( \ceil ( $json->totalItems / 10 ) ) - 1, $json->partOf ); // phpcs:ignore

		if ( ( \ceil ( $json->totalItems / 10 ) ) - 1 > $page ) { // phpcs:ignore
			$json->next  = \add_query_arg( 'page', ++$page, $json->partOf ); // phpcs:ignore
		}

		foreach ( $posts as $post ) {
			$activitypub_post = new \Activitypub\Model\Post( $post );
			$activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_NONE );
			$activitypub_activity->from_post( $activitypub_post->to_array() );
			$json->orderedItems[] = $activitypub_activity->to_array(); // phpcs:ignore
		}
		foreach ( $comments as $comment ) {
			$activitypub_comment = new \Activitypub\Model\Comment( $comment );
			$activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_NONE );
			$activitypub_activity->from_post( $activitypub_comment->to_array() );
			$json->orderedItems[] = $activitypub_activity->to_array(); // phpcs:ignore
		}

		// filter output
		$json = \apply_filters( 'activitypub_outbox_array', $json );

		/*
		 * Action triggerd after the ActivityPub profile has been created and sent to the client
		 */
		\do_action( 'activitypub_outbox_post' );

		$response = new \WP_REST_Response( $json, 200 );

		$response->header( 'Content-Type', 'application/activity+json' );

		return $response;
	}

	/**
	 * Renders the replies collection for a post
	 *
	 * @param  WP_REST_Request   $request
	 * @return WP_REST_Response
	 */
	public static function post_replies( $request ) {
		$post_id = $request->get_param( 'id' );
		$comments  = \get_comments( array('post_id' =>  $post_id) );

		if ( ! $comments ) {
			return new \WP_Error( 'rest_invalid_param', __( 'No comments found', 'activitypub' ), array(
				'status' => 404,
				'params' => array(
					'post_id' => \__( 'No comments found', 'activitypub' ),
				),
			) );
		}

		$page = $request->get_param( 'page', 0 );
		error_log('comments');
		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_replies_pre' );

		$json = new \stdClass();

		$json->{'@context'} = \Activitypub\get_context();
		$json->id = \home_url( \add_query_arg( null, null ) );
		//$json->generator = 'http://wordpress.org/?v=' . \get_bloginfo_rss( 'version' );
		//$json->actor = \get_author_posts_url( $user_id );
		$json->type = 'CollectionPage';
		
		$json->partOf = \get_rest_url( null, "/activitypub/1.0/post/$post_id/replies" ); // phpcs:ignore

		$count_comments = \get_comments_number( $post_id );// get allowed post types, public_only = true
		$json->totalItems = \intval( $count_comments ); // phpcs:ignore
	
		// $posts = \get_posts( array(
		// 	'posts_per_page' => 10,
		// 	'author' => $user_id,
		// 	'offset' => $page * 10,
		// ) );
	/*add_query_arg( array(
		'page' => 'true',
		'max_id' => 'value2',//next
		'min_id' => 'value2',//prev
	), $json->partOf );
	*/
		$json->first = \add_query_arg( 'page', 0, $json->partOf ); // phpcs:ignore
		$json->last  = \add_query_arg( 'page', ( \ceil ( $json->totalItems / 10 ) ) - 1, $json->partOf ); // phpcs:ignore

		if ( ( \ceil ( $json->totalItems / 10 ) ) - 1 > $page ) { // phpcs:ignore
			$json->next  = \add_query_arg( 'page', ++$page, $json->partOf ); // phpcs:ignore
		}

		foreach ( $comments as $comment ) {
			$activitypub_comment = new \Activitypub\Model\Comment( $comment );
			$activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_NONE );

			$json->orderedItems[] = $activitypub_comment->to_array(); // phpcs:ignore

			// $activitypub_post = new \Activitypub\Model\Post( $post );
			// $activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_NONE );
			// $activitypub_activity->from_post( $activitypub_post->to_array() );
			// $json->orderedItems[] = $activitypub_activity->to_array(); // phpcs:ignore
		}

		// filter output
		$json = \apply_filters( 'activitypub_comment_array', $json );

		/*
		 * Action triggerd after the ActivityPub profile has been created and sent to the client
		 */
		\do_action( 'activitypub_comment_post' );

		$response = new \WP_REST_Response( $json, 200 );

		$response->header( 'Content-Type', 'application/activity+json' );

		return $response;
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
}

/**
 * ActivityPub Replies CollectionPage REST-Class
 *
 * @author Django Doucet
 *
 * @see https://www.w3.org/TR/activitypub/#outbox
 */
class Post {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'rest_api_init', array( '\Activitypub\Rest\Post', 'register_routes' ) );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			'activitypub/1.0', '/post/(?P<id>\d+)/replies', array(
				array(
					'methods'  => \WP_REST_Server::READABLE,
					'callback' => array( '\Activitypub\Rest\Post', 'post_replies' ),
					'args'     => self::request_parameters(),
				),
			)
		);
	}

	/**
	 * Renders the user-outbox
	 *
	 * @param  WP_REST_Request   $request
	 * @return WP_REST_Response
	 */
	public static function post_replies( $request ) {
		$post_id = $request->get_param( 'id' );
		$comments  = \get_comments( 'post_id', $post_id );

		if ( ! $comments ) {
			return new \WP_Error( 'rest_invalid_param', __( 'No comments found', 'activitypub' ), array(
				'status' => 404,
				'params' => array(
					'post_id' => \__( 'No comments found', 'activitypub' ),
				),
			) );
		}

		$page = $request->get_param( 'page', 0 );
		error_log('comments');
		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_replies_pre' );

		$json = new \stdClass();

		$json->{'@context'} = \Activitypub\get_context();
		$json->id = \home_url( \add_query_arg( null, null ) );
		//$json->generator = 'http://wordpress.org/?v=' . \get_bloginfo_rss( 'version' );
		//$json->actor = \get_author_posts_url( $user_id );
		$json->type = 'CollectionPage';
		if ( ( \ceil ( $json->totalItems / 10 ) ) - 1 > $page ) { // phpcs:ignore
			$json->next  = \add_query_arg( 'page', ++$page, $json->partOf ); // phpcs:ignore
		}
		$json->partOf = \get_rest_url( null, "/activitypub/1.0/post/$post_id/replies" ); // phpcs:ignore

		$count_comments = \get_comments_number( $post_id );// get allowed post types, public_only = true
		$json->totalItems = \intval( $count_comments ); // phpcs:ignore

		// $posts = \get_posts( array(
		// 	'posts_per_page' => 10,
		// 	'author' => $user_id,
		// 	'offset' => $page * 10,
		// ) );
/*add_query_arg( array(
    'page' => 'true',
    'max_id' => 'value2',//next
    'min_id' => 'value2',//prev
), $json->partOf );
*/
		$json->first = \add_query_arg( 'page', 0, $json->partOf ); // phpcs:ignore
		$json->last  = \add_query_arg( 'page', ( \ceil ( $json->totalItems / 10 ) ) - 1, $json->partOf ); // phpcs:ignore

		if ( ( \ceil ( $json->totalItems / 10 ) ) - 1 > $page ) { // phpcs:ignore
			$json->next  = \add_query_arg( 'page', ++$page, $json->partOf ); // phpcs:ignore
		}

		foreach ( $comments as $comment ) {
			$activitypub_comment = new \Activitypub\Model\Comment( $comment );
			//$activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_NONE );

			$json->orderedItems[] = $activitypub_comment->to_array(); // phpcs:ignore
		}

		// filter output
		$json = \apply_filters( 'activitypub_comment_array', $json );

		/*
		 * Action triggerd after the ActivityPub profile has been created and sent to the client
		 */
		\do_action( 'activitypub_comment_post' );

		$response = new \WP_REST_Response( $json, 200 );

		$response->header( 'Content-Type', 'application/activity+json' );

		return $response;
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function request_parameters() {
		$params = array();

		// $params['page'] = array(
		// 	'type' => 'integer',
		// );

		// $params['id'] = array(
		// 	'required' => true,
		// 	'type' => 'integer',
		// );

		return $params;
	}

	/**
	 * [post_replies_endpoint description]
	 * @return array Post replies CollectionPage
	 */
	// public function post_replies_endpoint( $vars ) {
	// 	if( isset( $vars['replies'] ) ) $vars['replies'] = true;
  //   return $vars;
	// }
}
