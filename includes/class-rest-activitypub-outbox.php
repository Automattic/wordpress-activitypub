<?php
/**
 * ActivityPub Outbox Class
 *
 * @author Matthias Pfefferle
 */
class Rest_Activitypub_Outbox {

	/**
	 * Register routes
	 */
	public static function register_routes() {
		register_rest_route(
			'activitypub/1.0', '/users/(?P<id>\d+)/outbox', array(
				array(
					'methods'  => WP_REST_Server::READABLE,
					'callback' => array( 'Rest_Activitypub_Outbox', 'user_outbox' ),
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
	public static function user_outbox( $request ) {
		$user_id = $request->get_param( 'id' );
		$author  = get_user_by( 'ID', $user_id );

		if ( ! $author ) {
			return new WP_Error( 'rest_invalid_param', __( 'User not found', 'activitypub' ), array(
				'status' => 404, 'params' => array(
					'user_id' => __( 'User not found', 'activitypub' )
				)
			) );
		}

		$page = $request->get_param( 'page', 0 );

		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		do_action( 'activitypub_outbox_pre' );

		$json = new stdClass();

		$json->{'@context'} = get_activitypub_context();
		$json->id = home_url( add_query_arg( NULL, NULL ) );
		$json->generator = 'http://wordpress.org/?v=' . get_bloginfo_rss( 'version' );
		$json->actor = get_author_posts_url( $user_id );
		$json->type = 'OrderedCollectionPage';
		$json->partOf = get_rest_url( null, "/activitypub/1.0/users/$user_id/outbox" ); // phpcs:ignore

		$count_posts = wp_count_posts();
		$json->totalItems = intval( $count_posts->publish );

		$posts = get_posts( array(
			'posts_per_page' => 10,
			'author' => $user_id,
			'offset' => $page * 10,
		) );

		$json->first = add_query_arg( 'page', 0, $json->partOf );
		$json->last  = add_query_arg( 'page', ( ceil ( $json->totalItems / 10 ) ) - 1, $json->partOf );

		if ( ( ceil ( $json->totalItems / 10 ) ) - 1 > $page ) {
			$json->next  = add_query_arg( 'page', ++$page, $json->partOf );
		}

		foreach ( $posts as $post ) {
			$activitypub_post = new Activitypub_Post( $post );
			$activitypub_activity = new Activitypub_Activity( 'Create', Activitypub_Activity::TYPE_NONE );
			$activitypub_activity->from_post( $activitypub_post->to_array() );
			$json->orderedItems[] = $activitypub_activity->to_array(); // phpcs:ignore
		}

		// filter output
		$json = apply_filters( 'activitypub_outbox_array', $json );

		/*
		 * Action triggerd after the ActivityPub profile has been created and sent to the client
		 */
		do_action( 'activitypub_outbox_post' );

		$response = new WP_REST_Response( $json, 200 );

		$response->header( 'Content-Type', 'application/activity+json' );

		return $response;
	}

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

	public static function send_post_activity( $post_id ) {
		$post = get_post( $post_id );
		$user_id = $post->post_author;

		$activitypub_post = new Activitypub_Post( $post );
		$activitypub_activity = new Activitypub_Activity( 'Create', Activitypub_Activity::TYPE_FULL );
		$activitypub_activity->from_post( $activitypub_post->to_array() );

		$activity = $activitypub_activity->to_json(); // phpcs:ignore

		$followers = Db_Activitypub_Followers::get_followers( $user_id );

		foreach ( activitypub_get_follower_inboxes( $user_id, $followers ) as $inbox ) {
			$response = activitypub_safe_remote_post( $inbox, $activity, $user_id );
		}
	}
}
