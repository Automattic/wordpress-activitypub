<?php
/**
 * Outbox Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;
use Activitypub\Transformer\Factory;

/**
 * ActivityPub Outbox Controller.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#outbox
 */
class Outbox_Controller extends \WP_REST_Controller {
	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = ACTIVITYPUB_REST_NAMESPACE;

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = '(?:users|actors)/(?P<user_id>[\w\-\.]+)/outbox';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'args'   => array(
					'user_id' => array(
						'description'       => 'The ID of the user or actor.',
						'type'              => 'string',
						'validate_callback' => array( $this, 'validate_user_id' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
					'args'                => array(
						'page'     => array(
							'description' => 'Current page of the collection.',
							'type'        => 'integer',
							'default'     => 1,
							'minimum'     => 1,
						),
						'per_page' => array(
							'description' => 'Maximum number of items to be returned in result set.',
							'type'        => 'integer',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 100,
						),
					),
				),
				'schema' => array( $this, 'get_collection_schema' ),
			)
		);
	}

	/**
	 * Retrieves a collection of outbox items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$page    = $request->get_param( 'page' );
		$user    = Actors::get_by_various( $user_id );

		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 *
		 * @param \WP_REST_Request $request The request object.
		 */
		\do_action( 'activitypub_rest_outbox_pre', $request );

		$query_args = array(
			'posts_per_page' => $request->get_param( 'per_page' ),
			'author'         => $user_id > 0 ? $user_id : null,
			'paged'          => $page,
			'post_type'      => Outbox::POST_TYPE,
			'post_status'    => 'draft',
		);

		$outbox_query = new \WP_Query();
		$query_result = $outbox_query->query( $query_args );

		$response = array(
			'@context'     => array( 'https://www.w3.org/ns/activitystreams' ),
			'id'           => \get_rest_url( null, \sprintf( 'actors/%d/outbox', $user_id ) ),
			'generator'    => 'https://wordpress.org/?v=' . \get_bloginfo( 'version' ),
			'actor'        => $user->get_id(),
			'type'         => 'OrderedCollectionPage',
			'partOf'       => \get_rest_url( null, \sprintf( 'actors/%d/outbox', $user_id ) ),
			'totalItems'   => $outbox_query->found_posts,
			'orderedItems' => array(),
		);

		foreach ( $query_result as $outbox_item ) {
			$response['orderedItems'][] = $this->prepare_item_for_response( $outbox_item, $request );
		}

		$post_types = \get_option( 'activitypub_support_post_types', array( 'post' ) );
		if ( $user_id > 0 ) {
			$count_posts            = \count_user_posts( $user_id, $post_types, true );
			$response['totalItems'] = \intval( $count_posts );
		} else {
			foreach ( $post_types as $post_type ) {
				$count_posts             = \wp_count_posts( $post_type );
				$response['totalItems'] += \intval( $count_posts->publish );
			}
		}

		$max_pages         = \ceil( $response['totalItems'] / $request->get_param( 'per_page' ) );
		$response['first'] = \add_query_arg( 'page', 1, $response['partOf'] );
		$response['last']  = \add_query_arg( 'page', $max_pages, $response['partOf'] );

		if ( $max_pages > $page ) {
			$response['next'] = \add_query_arg( 'page', $page + 1, $response['partOf'] );
		}

		if ( $page > 1 ) {
			$response['prev'] = \add_query_arg( 'page', $page - 1, $response['partOf'] );
		}

		if ( $page > $max_pages && $response['totalItems'] > 0 ) {
			return new \WP_Error(
				'rest_post_invalid_page_number',
				'The page number requested is larger than the number of pages available.',
				array( 'status' => 400 )
			);
		}

		/**
		 * Filter the ActivityPub outbox array.
		 *
		 * @param array $response The ActivityPub outbox array.
		 */
		$response = \apply_filters( 'activitypub_rest_outbox_array', $response );

		/**
		 * Action triggered after the ActivityPub profile has been created and sent to the client.
		 */
		\do_action( 'activitypub_outbox_post' );

		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Validates the user_id parameter.
	 *
	 * @param mixed $user_id The user_id parameter.
	 * @return bool|\WP_Error True if the user_id is valid, WP_Error otherwise.
	 */
	public function validate_user_id( $user_id ) {
		$user = Actors::get_by_various( $user_id );
		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		return true;
	}

	/**
	 * Prepares the item for the REST response.
	 *
	 * @param mixed            $item    WordPress representation of the item.
	 * @param \WP_REST_Request $request Request object.
	 * @return array Response object on success, or WP_Error object on failure.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$transformer = Factory::get_transformer( $item->post_content );

		$type  = 'Object';
		$terms = wp_get_object_terms( $item->ID, 'ap_activity_type' );
		if ( isset( $terms[0]->name ) ) {
			$type = ucfirst( $terms[0]->name );
		}

		$activity = $transformer->to_activity( $type );

		return $activity->to_array( false );
	}

	/**
	 * Retrieves the outbox schema, conforming to JSON Schema.
	 *
	 * @return array Collection schema data.
	 */
	public function get_collection_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'outbox',
			'type'       => 'object',
			'properties' => array(
				'@context'     => array(
					'description' => 'The JSON-LD context for the collection.',
					'type'        => array( 'string', 'array', 'object' ),
					'required'    => true,
				),
				'id'           => array(
					'description' => 'The unique identifier for the collection.',
					'type'        => 'string',
					'format'      => 'uri',
					'required'    => true,
				),
				'type'         => array(
					'description' => 'The type of the collection.',
					'type'        => 'string',
					'enum'        => array( 'OrderedCollection', 'OrderedCollectionPage' ),
					'required'    => true,
				),
				'actor'        => array(
					'description' => 'The actor who owns this outbox.',
					'type'        => 'string',
					'format'      => 'uri',
					'required'    => true,
				),
				'totalItems'   => array(
					'description' => 'The total number of items in the collection.',
					'type'        => 'integer',
					'minimum'     => 0,
					'required'    => true,
				),
				'orderedItems' => array(
					'description' => 'The items in the collection.',
					'type'        => 'array',
					'items'       => array(
						'type' => 'object',
					),
					'required'    => true,
				),
				'first'        => array(
					'description' => 'The first page of the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'last'         => array(
					'description' => 'The last page of the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'next'         => array(
					'description' => 'The next page of the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'prev'         => array(
					'description' => 'The previous page of the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
