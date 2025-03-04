<?php
/**
 * Actors_Inbox_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;

use function Activitypub\get_context;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\get_masked_wp_version;

/**
 * Actors_Inbox_Controller class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#inbox
 */
class Actors_Inbox_Controller extends Actors_Controller {
	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/inbox',
			array(
				'args'   => array(
					'user_id' => array(
						'description' => 'The ID or username of the actor.',
						'type'        => 'string',
						'required'    => true,
						'pattern'     => '[\w\-\.]+',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
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
							'default'     => 20,
							'minimum'     => 1,
						),
					),
					'schema'              => array( $this, 'get_collection_schema' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
					'args'                => array(
						'id'     => array(
							'description' => 'The unique identifier for the activity.',
							'type'        => 'string',
							'format'      => 'uri',
							'required'    => true,
						),
						'actor'  => array(
							'description'       => 'The actor performing the activity.',
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => '\Activitypub\object_to_uri',
						),
						'type'   => array(
							'description' => 'The type of the activity.',
							'type'        => 'string',
							'required'    => true,
						),
						'object' => array(
							'description'       => 'The object of the activity.',
							'required'          => true,
							'validate_callback' => function ( $param, $request, $key ) {
								/**
								 * Filter the ActivityPub object validation.
								 *
								 * @param bool   $validate The validation result.
								 * @param array  $param    The object data.
								 * @param object $request  The request object.
								 * @param string $key      The key.
								 */
								return \apply_filters( 'activitypub_validate_object', true, $param, $request, $key );
							},
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Renders the user-inbox.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error.
	 */
	public function get_items( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_various( $user_id );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		/**
		 * Fires before the ActivityPub inbox is created and sent to the client.
		 */
		\do_action( 'activitypub_rest_inbox_pre' );

		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$response = array(
			'@context'     => get_context(),
			'id'           => get_rest_url_by_path( \sprintf( 'actors/%d/inbox', $user->get__id() ) ),
			'generator'    => 'https://wordpress.org/?v=' . get_masked_wp_version(),
			'type'         => 'OrderedCollectionPage',
			'partOf'       => get_rest_url_by_path( \sprintf( 'actors/%d/inbox', $user->get__id() ) ),
			'totalItems'   => 0,
			'orderedItems' => array(),
			'first'        => get_rest_url_by_path( \sprintf( 'actors/%d/inbox', $user->get__id() ) ),
		);

		/**
		 * Filters the ActivityPub inbox data before it is sent to the client.
		 *
		 * @param array $response The ActivityPub inbox array.
		 */
		$response = \apply_filters( 'activitypub_rest_inbox_array', $response );

		$max_pages = \ceil( $response['totalItems'] / $per_page );

		if ( $page > $max_pages ) {
			return new \WP_Error(
				'rest_post_invalid_page_number',
				'The page number requested is larger than the number of pages available.',
				array( 'status' => 400 )
			);
		}

		$response['last'] = \add_query_arg( 'page', \max( $max_pages, 1 ), $response['partOf'] );

		if ( $max_pages > $page ) {
			$response['next'] = \add_query_arg( 'page', $page + 1, $response['partOf'] );
		}

		if ( $page > 1 ) {
			$response['prev'] = \add_query_arg( 'page', $page - 1, $response['partOf'] );
		}

		/**
		 * Fires after the ActivityPub inbox has been created and sent to the client.
		 */
		\do_action( 'activitypub_inbox_post' );

		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Handles user-inbox requests.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error.
	 */
	public function create_item( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_various( $user_id );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		$data     = $request->get_json_params();
		$activity = Activity::init_from_array( $data );
		$type     = $request->get_param( 'type' );
		$type     = \strtolower( $type );

		/**
		 * ActivityPub inbox action.
		 *
		 * @param array    $data     The data array.
		 * @param int|null $user_id  The user ID.
		 * @param string   $type     The type of the activity.
		 * @param Activity $activity The Activity object.
		 */
		\do_action( 'activitypub_inbox', $data, $user->get__id(), $type, $activity );

		/**
		 * ActivityPub inbox action for specific activity types.
		 *
		 * @param array    $data     The data array.
		 * @param int|null $user_id  The user ID.
		 * @param Activity $activity The Activity object.
		 */
		\do_action( 'activitypub_inbox_' . $type, $data, $user->get__id(), $activity );

		$response = \rest_ensure_response( array() );
		$response->set_status( 202 );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Retrieves the schema for the inbox collection, conforming to JSON Schema.
	 *
	 * @return array Collection schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'https://json-schema.org/draft-04/schema#',
			'title'      => 'inbox',
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
				'partOf'       => array(
					'description' => 'The collection this page is part of.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'generator'    => array(
					'description' => 'The software used to generate the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
