<?php
/**
 * Followers_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\get_context;
use function Activitypub\get_masked_wp_version;
use function Activitypub\get_rest_url_by_path;

/**
 * Followers_Controller class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#followers
 */
class Followers_Controller extends Actors_Controller {
	use Collection;

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
						'description'       => 'The ID of the actor.',
						'type'              => 'integer',
						'required'          => true,
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
							'minimum'     => 1,
							// No default so we can differentiate between Collection and CollectionPage requests.
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

		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 */
		\do_action( 'activitypub_rest_followers_pre' );

		$order    = $request->get_param( 'order' );
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' ) ?? 1;
		$context  = $request->get_param( 'context' );

		$data = Followers::get_followers_with_count( $user_id, $per_page, $page, array( 'order' => \ucwords( $order ) ) );

		$response = array(
			'@context'     => get_context(),
			'id'           => get_rest_url_by_path( \sprintf( 'actors/%d/followers', $user_id ) ),
			'generator'    => 'https://wordpress.org/?v=' . get_masked_wp_version(),
			'type'         => 'OrderedCollection',
			'totalItems'   => $data['total'],
			'orderedItems' => \array_filter(
				\array_map(
					function ( $item ) use ( $context ) {
						if ( 'full' === $context ) {
							$actor = Remote_Actors::get_actor( $item );
							if ( \is_wp_error( $actor ) ) {
								return false;
							}
							return $actor->to_array( false );
						}
						return $item->guid;
					},
					$data['followers']
				)
			),
		);

		$response = $this->prepare_collection_response( $response, $request );
		if ( \is_wp_error( $response ) ) {
			return $response;
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

		// Define the schema for items in the followers collection.
		$item_schema = array(
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
					),
				),
			),
		);

		$schema = $this->get_collection_schema( $item_schema );

		// Add followers-specific properties.
		$schema['title']                   = 'followers';
		$schema['properties']['actor']     = array(
			'description' => 'The actor who owns the followers collection.',
			'type'        => 'string',
			'format'      => 'uri',
			'readonly'    => true,
		);
		$schema['properties']['generator'] = array(
			'description' => 'The generator of the followers collection.',
			'type'        => 'string',
			'format'      => 'uri',
			'readonly'    => true,
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
