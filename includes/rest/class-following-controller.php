<?php
/**
 * Following_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\get_context;
use function Activitypub\get_masked_wp_version;
use function Activitypub\get_rest_url_by_path;

/**
 * Following_Controller class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#following
 */
class Following_Controller extends Actors_Controller {
	use Collection;

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/following',
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
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 100,
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
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Retrieves following list.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = null;
		if ( \has_filter( 'activitypub_rest_following' ) ) {
			$user = Actors::get_by_id( $user_id );
		}

		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 */
		\do_action( 'activitypub_rest_following_pre' );

		$order    = $request->get_param( 'order' );
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' ) ?? 1;
		$context  = $request->get_param( 'context' );

		$data = Following::get_following_with_count( $user_id, $per_page, $page, array( 'order' => \ucwords( $order ) ) );

		$response = array(
			'@context'     => get_context(),
			'id'           => get_rest_url_by_path( \sprintf( 'actors/%d/following', $user_id ) ),
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
					$data['following']
				)
			),
		);

		/**
		 * Filter the list of following urls
		 *
		 * @param array                   $items The array of following urls.
		 * @param \Activitypub\Model\User $user  The user object.
		 *
		 * @deprecated 7.1.0 Please migrate your Followings to the new internal Following structure.
		 */
		$items = \apply_filters_deprecated( 'activitypub_rest_following', array( array(), $user ), '7.1.0', 'Please migrate your Followings to the new internal Following structure.' );

		if ( ! empty( $items ) ) {
			$response['totalItems']   = count( $items );
			$response['orderedItems'] = $items;
		}

		$response = $this->prepare_collection_response( $response, $request );
		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Retrieves the following schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		// Define the schema for items in the following collection.
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

		// Add following-specific properties.
		$schema['title']                   = 'following';
		$schema['properties']['actor']     = array(
			'description' => 'The actor who owns the following collection.',
			'type'        => 'string',
			'format'      => 'uri',
			'readonly'    => true,
		);
		$schema['properties']['generator'] = array(
			'description' => 'The generator of the following collection.',
			'type'        => 'string',
			'format'      => 'uri',
			'readonly'    => true,
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
