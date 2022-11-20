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
			'activitypub/1.0',
			'/users/(?P<user_id>\d+)/outbox',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( '\Activitypub\Rest\Outbox', 'user_outbox_get' ),
					'args'                => self::request_parameters(),
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
	public static function user_outbox_get( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$author  = \get_user_by( 'ID', $user_id );
		$post_types = \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) );

		if ( ! $author ) {
			return new \WP_Error(
				'rest_invalid_param',
				\__( 'User not found', 'activitypub' ),
				array(
					'status' => 404,
					'params' => array(
						'user_id' => \__( 'User not found', 'activitypub' ),
					),
				)
			);
		}

		$page = $request->get_param( 'page', 0 );

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

		$json->totalItems = 0;
		foreach ( $post_types as $post_type ) {
			$count_posts = \wp_count_posts( $post_type );
			$json->totalItems += \intval( $count_posts->publish ); // phpcs:ignore
		}

		$json->first = \add_query_arg( 'page', 1, $json->partOf ); // phpcs:ignore
		$json->last  = \add_query_arg( 'page', \ceil ( $json->totalItems / 10 ), $json->partOf ); // phpcs:ignore

		if ( $page && ( ( \ceil ( $json->totalItems / 10 ) ) > $page ) ) { // phpcs:ignore
			$json->next  = \add_query_arg( 'page', $page + 1, $json->partOf ); // phpcs:ignore
		}

		if ( $page ) {
			$posts = \get_posts(
				array(
					'posts_per_page' => 10,
					'author' => $user_id,
					'offset' => ( $page - 1 ) * 10,
					'post_type' => $post_types,
				)
			);

			foreach ( $posts as $post ) {
				$activitypub_post = new \Activitypub\Model\Post( $post );
				$activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_NONE );
				$activitypub_activity->from_post( $activitypub_post->to_array() );
				$json->orderedItems[] = $activitypub_activity->to_array(); // phpcs:ignore
			}
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
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function request_parameters() {
		$params = array();

		$params['page'] = array(
			'type' => 'integer',
		);

		$params['user_id'] = array(
			'required' => true,
			'type' => 'integer',
		);

		return $params;
	}
}
