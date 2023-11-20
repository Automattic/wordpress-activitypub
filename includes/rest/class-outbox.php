<?php
namespace Activitypub\Rest;

use stdClass;
use WP_Error;
use WP_REST_Server;
use WP_REST_Response;
use Activitypub\Transformers_Manager;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Users as User_Collection;

use function Activitypub\get_context;
use function Activitypub\get_rest_url_by_path;

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
		self::register_routes();
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/users/(?P<user_id>[\w\-\.]+)/outbox',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'user_outbox_get' ),
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
		$user    = User_Collection::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$post_types = array_keys( \get_option( 'activitypub_transformer_mapping', array( 'post' => 'activitypub/default', 'page' => 'activitypub/default') ) );

		$page = $request->get_param( 'page', 1 );

		/*
		 * Action triggerd prior to the ActivityPub profile being created and sent to the client
		 */
		\do_action( 'activitypub_rest_outbox_pre' );

		$json = new stdClass();

		$json->{'@context'} = get_context();
		$json->id = get_rest_url_by_path( sprintf( 'users/%d/outbox', $user_id ) );
		$json->generator = 'http://wordpress.org/?v=' . \get_bloginfo_rss( 'version' );
		$json->actor = $user->get_id();
		$json->type = 'OrderedCollectionPage';
		$json->partOf = get_rest_url_by_path( sprintf( 'users/%d/outbox', $user_id ) ); // phpcs:ignore
		$json->totalItems = 0; // phpcs:ignore

		foreach ( $post_types as $post_type ) {
			$count_posts = \wp_count_posts( $post_type );
			$json->totalItems += \intval( $count_posts->publish ); // phpcs:ignore
		}

		$json->first = \add_query_arg( 'page', 1, $json->partOf ); // phpcs:ignore
		$json->last  = \add_query_arg( 'page', \ceil ( $json->totalItems / 10 ), $json->partOf ); // phpcs:ignore

		if ( $page && ( ( \ceil ( $json->totalItems / 10 ) ) > $page ) ) { // phpcs:ignore
			$json->next  = \add_query_arg( 'page', $page + 1, $json->partOf ); // phpcs:ignore
		}

		if ( $page && ( $page > 1 ) ) { // phpcs:ignore
			$json->prev  = \add_query_arg( 'page', $page - 1, $json->partOf ); // phpcs:ignore
		}

		if ( $page ) {
			$posts = \get_posts(
				array(
					'posts_per_page' => 10,
					'author'         => $user_id,
					'paged'          => $page,
					'post_type'      => $post_types,
				)
			);

			foreach ( $posts as $post ) {
				$transformer = Transformers_Manager::get_transformer( $wp_post );
				$transformer->transform( $wp_post );
				$post = $transformer->to_object();
				$activity = new Activity();
				$activity->set_type( 'Create' );
				$activity->set_context( null );
				$activity->set_object( $post );

				$json->orderedItems[] = $activity->to_array(); // phpcs:ignore
			}
		}

		// filter output
		$json = \apply_filters( 'activitypub_rest_outbox_array', $json );

		/*
		 * Action triggerd after the ActivityPub profile has been created and sent to the client
		 */
		\do_action( 'activitypub_outbox_post' );

		$rest_response = new WP_REST_Response( $json, 200 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );

		return $rest_response;
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
			'default' => 1,
		);

		$params['user_id'] = array(
			'required' => true,
			'type' => 'string',
		);

		return $params;
	}
}
