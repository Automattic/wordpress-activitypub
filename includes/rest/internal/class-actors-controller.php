<?php
/**
 * ActivityPub Internal Actors REST Controller
 *
 * Provides internal REST API endpoints for the block editor to interact with actors
 * via WordPress Core Data API entities.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest\Internal;

use Activitypub\Collection\Actors as Actor_Collection;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Internal Actors REST Controller.
 *
 * Provides CRUD operations for actors as WordPress Core Data entities.
 */
class Actors_Controller extends WP_REST_Controller {
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
	protected $rest_base = 'internal/actors';

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
			'/' . $this->rest_base . '/(?P<id>[\d-]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the actor.', 'activitypub' ),
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
	 * Retrieves a collection of actors.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$type         = $request->get_param( 'type' );
		$relationship = $request->get_param( 'relationship' );
		$user_id      = $request->get_param( 'user_id' );
		$per_page     = (int) $request->get_param( 'per_page' );
		$page         = (int) $request->get_param( 'page' );
		$order        = $request->get_param( 'order' );
		$search       = $request->get_param( 'search' );

		// If requesting followers or following, use appropriate collection.
		if ( $relationship && null !== $user_id ) {
			return $this->get_relationship_actors( $relationship, $user_id, $per_page, $page, $order, $search, $request );
		}

		// Get local actors.
		$actors = Actor_Collection::get_all();

		// Filter by type if specified.
		if ( $type ) {
			$actors = array_filter(
				$actors,
				function ( $actor ) use ( $type ) {
					return Actor_Collection::get_type_by_id( $actor->get_user_id() ) === $type;
				}
			);
		}

		$data = array();
		foreach ( $actors as $actor ) {
			$item_data = $this->prepare_item_for_response( $actor, $request );
			$data[]    = $this->prepare_response_for_collection( $item_data );
		}

		return \rest_ensure_response( $data );
	}

	/**
	 * Retrieves a single actor.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$actor_id = (int) $request->get_param( 'id' );
		$actor    = Actor_Collection::get_by_id( $actor_id );

		if ( \is_wp_error( $actor ) ) {
			return $actor;
		}

		$data = $this->prepare_item_for_response( $actor, $request );

		return \rest_ensure_response( $data );
	}

	/**
	 * Retrieves actors based on relationship (followers or following).
	 *
	 * @param string          $relationship Relationship type ('followers' or 'following').
	 * @param int             $user_id      User ID for the relationship.
	 * @param int             $per_page     Number of items per page.
	 * @param int             $page         Current page.
	 * @param string          $order        Order direction.
	 * @param string          $search       Search term.
	 * @param WP_REST_Request $request      Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	protected function get_relationship_actors( $relationship, $user_id, $per_page, $page, $order, $search, $request ) {
		// Validate actor exists.
		$actor = Actor_Collection::get_by_id( $user_id );
		if ( \is_wp_error( $actor ) ) {
			return $actor;
		}

		$args = array(
			'order' => 'desc' === $order ? 'DESC' : 'ASC',
		);

		// Add search if provided.
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		// Query the appropriate collection.
		if ( 'followers' === $relationship ) {
			$collection_class = 'Activitypub\Collection\Followers';
		} elseif ( 'following' === $relationship ) {
			$collection_class = 'Activitypub\Collection\Following';
		} else {
			return new WP_Error(
				'invalid_relationship',
				__( 'Invalid relationship type.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		$result = $collection_class::query( $user_id, $per_page, $page, $args );

		$total_items = $result['total'] ?? 0;
		$total_pages = $result['pages'] ?? 0;
		$actor_ids   = $result['followers'] ?? $result['following'] ?? array();

		// Prepare response data.
		$data = array();
		foreach ( $actor_ids as $actor_id ) {
			$post = \get_post( $actor_id );
			if ( ! $post ) {
				continue;
			}

			$item_data = $this->prepare_remote_actor_for_response( $post, $request );
			$data[]    = $this->prepare_response_for_collection( $item_data );
		}

		$response = \rest_ensure_response( $data );

		// Add pagination headers.
		$response->header( 'X-WP-Total', $total_items );
		$response->header( 'X-WP-TotalPages', $total_pages );

		return $response;
	}

	/**
	 * Checks if a given request has access to read actors.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Allow any logged-in user to read actors.
		if ( ! \is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to view actors.', 'activitypub' ),
				array( 'status' => \rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Checks if a given request has access to read a specific actor.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Prepares an actor for the REST response.
	 *
	 * @param object          $actor   Actor object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $actor, $request ) {
		$actor_array = $actor->to_array();

		// Remove context.
		unset( $actor_array['@context'] );

		// Map all values through object_to_uri and filter out empty ones.
		$data = array_filter( array_map( '\Activitypub\object_to_uri', $actor_array ) );

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = \rest_ensure_response( $data );

		/**
		 * Filters the actor data for a REST API response.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param object|array     $actor    Actor object or array.
		 * @param WP_REST_Request  $request  Request object.
		 */
		return \apply_filters( 'activitypub_rest_prepare_actor', $response, $actor, $request );
	}

	/**
	 * Prepares links for the request.
	 *
	 * @param object $actor Actor object.
	 * @return array Links for the given actor.
	 */
	protected function prepare_links( $actor ) {
		$actor_id = $actor->get_user_id();

		$links = array(
			'self'       => array(
				'href' => \rest_url( \sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $actor_id ) ),
			),
			'collection' => array(
				'href' => \rest_url( \sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
			),
		);

		return $links;
	}

	/**
	 * Prepares a remote actor (from post) for the REST response.
	 *
	 * @param \WP_Post        $post    Post object representing remote actor.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	protected function prepare_remote_actor_for_response( $post, $request ) {
		// Get actor data from post content or meta.
		$actor_data = \json_decode( $post->post_content, true );

		if ( empty( $actor_data ) || ! is_array( $actor_data ) ) {
			$actor_data = array();
		}

		// Remove context.
		unset( $actor_data['@context'] );

		// Map all values through object_to_uri and filter out empty ones.
		$data = array_filter( array_map( '\Activitypub\object_to_uri', $actor_data ) );

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = \rest_ensure_response( $data );

		/**
		 * Filters the remote actor data for a REST API response.
		 *
		 * @param WP_REST_Response $response The response object.
		 * @param \WP_Post         $post     Post object.
		 * @param WP_REST_Request  $request  Request object.
		 */
		return \apply_filters( 'activitypub_rest_prepare_remote_actor', $response, $post, $request );
	}

	/**
	 * Retrieves the actor schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'activitypub-actor',
			'type'       => 'object',
			'properties' => array(
				'id'                 => array(
					'description' => __( 'Unique identifier for the actor.', 'activitypub' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'type'               => array(
					'description' => __( 'The type of actor (user, blog, application).', 'activitypub' ),
					'type'        => 'string',
					'enum'        => array( 'user', 'blog', 'application' ),
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'name'               => array(
					'description' => __( 'The display name of the actor.', 'activitypub' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'preferred_username' => array(
					'description' => __( 'The preferred username of the actor.', 'activitypub' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'url'                => array(
					'description' => __( 'The URL of the actor profile.', 'activitypub' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'icon'               => array(
					'description' => __( 'The icon/avatar of the actor.', 'activitypub' ),
					'type'        => array( 'object', 'null' ),
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'summary'            => array(
					'description' => __( 'The biography/summary of the actor.', 'activitypub' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				'activitypub_id'     => array(
					'description' => __( 'The ActivityPub ID (URI) of the actor.', 'activitypub' ),
					'type'        => 'string',
					'format'      => 'uri',
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
			'context'      => $this->get_context_param( array( 'default' => 'view' ) ),
			'type'         => array(
				'description' => __( 'Limit results to actors of a specific type.', 'activitypub' ),
				'type'        => 'string',
				'enum'        => array( 'user', 'blog', 'application', 'remote' ),
			),
			'relationship' => array(
				'description' => __( 'Filter actors by relationship to a user.', 'activitypub' ),
				'type'        => 'string',
				'enum'        => array( 'followers', 'following' ),
			),
			'user_id'      => array(
				'description' => __( 'User ID for relationship filtering.', 'activitypub' ),
				'type'        => 'integer',
			),
			'page'         => array(
				'description' => __( 'Current page of the collection.', 'activitypub' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page'     => array(
				'description' => __( 'Maximum number of items to be returned in result set.', 'activitypub' ),
				'type'        => 'integer',
				'default'     => 10,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'order'        => array(
				'description' => __( 'Order sort attribute ascending or descending.', 'activitypub' ),
				'type'        => 'string',
				'default'     => 'desc',
				'enum'        => array( 'asc', 'desc' ),
			),
			'search'       => array(
				'description' => __( 'Limit results to those matching a string.', 'activitypub' ),
				'type'        => 'string',
			),
		);

		return $params;
	}
}
