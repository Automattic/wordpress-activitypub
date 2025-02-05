<?php
/**
 * Followers_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers as Follower_Collection;

use function Activitypub\get_rest_url_by_path;
use function Activitypub\get_masked_wp_version;

/**
 * Followers_Controller class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#followers
 */
class Followers_Controller extends Actors_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/followers',
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
							'default'     => 20,
							'minimum'     => 1,
						),
						'order'    => array(
							'description' => 'Order sort attribute ascending or descending.',
							'type'        => 'string',
							'default'     => 'desc',
							'enum'        => array( 'asc', 'desc' ),
						),
						'context'  => array(
							'description' => 'The context in which the request is made.',
							'type'        => 'string',
							'default'     => 'simple',
							'enum'        => array( 'simple', 'full' ),
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieves followers list.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_various( $user_id );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 */
		\do_action( 'activitypub_rest_followers_pre' );

		$order    = $request->get_param( 'order' );
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );
		$context  = $request->get_param( 'context' );

		$data = Follower_Collection::get_followers_with_count( $user_id, $per_page, $page, array( 'order' => \ucwords( $order ) ) );

		$response = array(
			'@context'     => \Activitypub\get_context(),
			'id'           => get_rest_url_by_path( \sprintf( 'actors/%d/followers', $user->get__id() ) ),
			'generator'    => 'https://wordpress.org/?v=' . get_masked_wp_version(),
			'actor'        => $user->get_id(),
			'type'         => 'OrderedCollectionPage',
			'totalItems'   => $data['total'],
			'partOf'       => get_rest_url_by_path( \sprintf( 'actors/%d/followers', $user->get__id() ) ),
			'orderedItems' => array_map(
				function ( $item ) use ( $context ) {
					if ( 'full' === $context ) {
						return $item->to_array( false );
					}
					return $item->get_id();
				},
				$data['followers']
			),
		);

		$max_pages = \ceil( $response['totalItems'] / $per_page );

		if ( $page > $max_pages ) {
			return new \WP_Error(
				'rest_post_invalid_page_number',
				'The page number requested is larger than the number of pages available.',
				array( 'status' => 400 )
			);
		}

		$response['first'] = \add_query_arg( 'page', 1, $response['partOf'] );
		$response['last']  = \add_query_arg( 'page', \max( $max_pages, 1 ), $response['partOf'] );

		if ( $max_pages > $page ) {
			$response['next'] = \add_query_arg( 'page', $page + 1, $response['partOf'] );
		}

		if ( $page > 1 ) {
			$response['prev'] = \add_query_arg( 'page', $page - 1, $response['partOf'] );
		}

		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Retrieves the followers schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'followers',
			'type'       => 'object',
			'properties' => array(
				'@context'     => array(
					'description' => 'The JSON-LD context for the response.',
					'type'        => array( 'array', 'object' ),
					'readonly'    => true,
				),
				'id'           => array(
					'description' => 'The unique identifier for the followers collection.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'generator'    => array(
					'description' => 'The generator of the followers collection.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'actor'        => array(
					'description' => 'The actor who owns the followers collection.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'type'         => array(
					'description' => 'The type of the followers collection.',
					'type'        => 'string',
					'enum'        => array( 'OrderedCollectionPage' ),
					'readonly'    => true,
				),
				'totalItems'   => array(
					'description' => 'The total number of items in the followers collection.',
					'type'        => 'integer',
					'readonly'    => true,
				),
				'partOf'       => array(
					'description' => 'The collection this page is part of.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'orderedItems' => array(
					'description' => 'The items in the followers collection.',
					'type'        => 'array',
					'items'       => array(
						'oneOf' => array(
							array(
								'type'   => 'string',
								'format' => 'uri',
							),
							array(
								'type'       => 'object',
								'properties' => array(
									'id'                => array(
										'type'   => 'string',
										'format' => 'uri',
									),
									'type'              => array(
										'type' => 'string',
									),
									'name'              => array(
										'type' => 'string',
									),
									'icon'              => array(
										'type'       => 'object',
										'properties' => array(
											'type'      => array(
												'type' => 'string',
											),
											'mediaType' => array(
												'type' => 'string',
											),
											'url'       => array(
												'type'   => 'string',
												'format' => 'uri',
											),
										),
									),
									'published'         => array(
										'type'   => 'string',
										'format' => 'date-time',
									),
									'summary'           => array(
										'type' => 'string',
									),
									'updated'           => array(
										'type'   => 'string',
										'format' => 'date-time',
									),
									'url'               => array(
										'type'   => 'string',
										'format' => 'uri',
									),
									'streams'           => array(
										'type' => 'array',
									),
									'preferredUsername' => array(
										'type' => 'string',
									),
									'manuallyApprovesFollowers' => array(
										'type' => 'boolean',
									),
								),
							),
						),
					),
					'readonly'    => true,
				),
				'next'         => array(
					'description' => 'The next page in the collection.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'prev'         => array(
					'description' => 'The previous page in the collection.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'first'        => array(
					'description' => 'The first page in the collection.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'last'         => array(
					'description' => 'The last page in the collection.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
