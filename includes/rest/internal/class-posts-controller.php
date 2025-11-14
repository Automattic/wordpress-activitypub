<?php
/**
 * ActivityPub Internal Posts REST Controller
 *
 * Provides internal REST API endpoints for the block editor to interact with ActivityPub posts
 * via WordPress Core Data API entities.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest\Internal;

use Activitypub\Collection\Posts as Posts_Collection;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Internal Posts REST Controller.
 *
 * Provides CRUD operations for ActivityPub posts as WordPress Core Data entities.
 */
class Posts_Controller extends WP_REST_Controller {
	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = 'activitypub/1.0';

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = 'internal/posts';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// Collection endpoint.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		// Single item endpoint.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the post.', 'activitypub' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Retrieves a collection of ActivityPub posts.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$user_id  = $request->get_param( 'user_id' );
		$actor_id = $request->get_param( 'actor_id' );
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$order    = $request->get_param( 'order' );
		$search   = $request->get_param( 'search' );

		$args = array(
			'post_type'      => Posts_Collection::POST_TYPE,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'order'          => 'desc' === $order ? 'DESC' : 'ASC',
			'post_status'    => 'publish',
		);

		// Filter by recipient user.
		if ( null !== $user_id ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$args['meta_query'] = array(
				array(
					'key'   => '_activitypub_user_id',
					'value' => (int) $user_id,
				),
			);
		}

		// Filter by remote actor.
		if ( $actor_id ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$args['meta_query'] = array(
				array(
					'key'   => '_activitypub_remote_actor_id',
					'value' => (int) $actor_id,
				),
			);
		}

		// Add search if provided.
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );

		$total_items = $query->found_posts;
		$total_pages = $query->max_num_pages;

		$data = array();
		foreach ( $query->posts as $post ) {
			$item_data = $this->prepare_item_for_response( $post, $request );
			$data[]    = $this->prepare_response_for_collection( $item_data );
		}

		$response = \rest_ensure_response( $data );

		// Add pagination headers.
		$response->header( 'X-WP-Total', $total_items );
		$response->header( 'X-WP-TotalPages', $total_pages );

		return $response;
	}

	/**
	 * Retrieves a single ActivityPub post.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$post_id = (int) $request->get_param( 'id' );
		$post    = Posts_Collection::get( $post_id );

		if ( ! $post || Posts_Collection::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'rest_post_invalid_id',
				__( 'Invalid post ID.', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$data = $this->prepare_item_for_response( $post, $request );

		return \rest_ensure_response( $data );
	}

	/**
	 * Checks if a given request has access to read posts.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Allow any logged-in user to read posts.
		if ( ! \is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to view posts.', 'activitypub' ),
				array( 'status' => \rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Checks if a given request has access to read a specific post.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Prepares an ActivityPub post for the REST response.
	 *
	 * @param \WP_Post        $post    Post object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $post, $request ) {
		// Get ActivityPub data from post content.
		$post_data = \json_decode( $post->post_content, true );

		if ( empty( $post_data ) || ! is_array( $post_data ) ) {
			$post_data = array();
		}

		// Remove context.
		unset( $post_data['@context'] );

		// Map all values through object_to_uri and filter out empty ones.
		$data = array_filter( array_map( '\Activitypub\object_to_uri', $post_data ) );

		// Add WordPress post data.
		$data['wp_id']     = $post->ID;
		$data['wp_status'] = $post->post_status;

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = \rest_ensure_response( $data );

		/**
		 * Filters the post data for a REST API response.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param \WP_Post         $post     Post object.
		 * @param WP_REST_Request  $request  Request object.
		 */
		return \apply_filters( 'activitypub_rest_prepare_post', $response, $post, $request );
	}

	/**
	 * Retrieves the post schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'activitypub-post',
			'type'       => 'object',
			'properties' => array(
				'id'        => array(
					'description' => __( 'The ActivityPub ID (URI) of the post.', 'activitypub' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'type'      => array(
					'description' => __( 'The ActivityPub object type.', 'activitypub' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'name'      => array(
					'description' => __( 'The title of the post.', 'activitypub' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'content'   => array(
					'description' => __( 'The content of the post.', 'activitypub' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'summary'   => array(
					'description' => __( 'The summary/excerpt of the post.', 'activitypub' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'published' => array(
					'description' => __( 'The publication date.', 'activitypub' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'wp_id'     => array(
					'description' => __( 'The WordPress post ID.', 'activitypub' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'wp_status' => array(
					'description' => __( 'The WordPress post status.', 'activitypub' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Retrieves the query params for the collection.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$params = array(
			'context'  => $this->get_context_param( array( 'default' => 'view' ) ),
			'user_id'  => array(
				'description' => __( 'Limit results to posts for a specific user (recipient).', 'activitypub' ),
				'type'        => 'integer',
			),
			'actor_id' => array(
				'description' => __( 'Limit results to posts from a specific remote actor.', 'activitypub' ),
				'type'        => 'integer',
			),
			'page'     => array(
				'description' => __( 'Current page of the collection.', 'activitypub' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page' => array(
				'description' => __( 'Maximum number of items to be returned in result set.', 'activitypub' ),
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'order'    => array(
				'description' => __( 'Order sort attribute ascending or descending.', 'activitypub' ),
				'type'        => 'string',
				'default'     => 'desc',
				'enum'        => array( 'asc', 'desc' ),
			),
			'search'   => array(
				'description' => __( 'Limit results to those matching a string.', 'activitypub' ),
				'type'        => 'string',
			),
		);

		return $params;
	}
}
