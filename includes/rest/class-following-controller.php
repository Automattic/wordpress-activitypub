<?php
/**
 * Following_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;

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
					'permission_callback' => array( $this, 'verify_signature' ),
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
						'item'     => $this->get_seek_item_arg(),
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

		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 */
		\do_action( 'activitypub_rest_following_pre' );

		$seek = $this->maybe_seek_item( $request, get_rest_url_by_path( \sprintf( 'actors/%d/following', $user_id ) ) );
		if ( null !== $seek ) {
			return $seek;
		}

		$order    = $request->get_param( 'order' );
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' ) ?? 1;
		$context  = $request->get_param( 'context' );

		$data = Following::query( $user_id, $per_page, $page, array( 'order' => \ucwords( $order ) ) );

		$response = array(
			'id'         => get_rest_url_by_path( \sprintf( 'actors/%d/following', $user_id ) ),
			'generator'  => 'https://wordpress.org/?v=' . get_masked_wp_version(),
			'type'       => 'OrderedCollection',
			'totalItems' => $data['total'],
		);

		if ( 'full' === $context ) {
			// Ensure the context is the first element in the response.
			$response = array( '@context' => Base_Object::JSON_LD_CONTEXT ) + $response;
		}

		if ( $this->show_social_graph( $request ) ) {
			$response['orderedItems'] = \array_filter(
				\array_map(
					static function ( $item ) use ( $context ) {
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
			);
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
	 * Get the position of a followed actor in the collection, under the collection's own query rules.
	 *
	 * @param string           $item    The ActivityPub actor ID of the followed actor.
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return int|false|\WP_Error Zero-based index of the item, false or WP_Error when not found.
	 */
	public function get_item_index( $item, $request ) {
		global $wpdb;

		if ( ! $this->show_social_graph( $request ) ) {
			return false;
		}

		$actor = Remote_Actors::get_by_uri( $item );
		if ( \is_wp_error( $actor ) ) {
			return $actor;
		}

		$user_id = $request->get_param( 'user_id' );
		$order   = $request->get_param( 'order' );
		$args    = array(
			'fields' => 'ids',
			'order'  => \ucwords( $order ),
		);

		// Confirm membership through the collection's own query before computing the index.
		$membership = Following::query( $user_id, 1, null, \array_merge( $args, array( 'post__in' => array( $actor->ID ) ) ) );
		if ( ! $membership['total'] ) {
			return false;
		}

		if ( 'asc' === $order ) {
			$where = $wpdb->prepare( " AND {$wpdb->posts}.ID < %d", $actor->ID );
		} else {
			$where = $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $actor->ID );
		}

		// Count the followed actors that sort before the item; that count is the item's zero-based index.
		$preceding = $this->with_posts_where(
			$where,
			static function () use ( $user_id, $args ) {
				return Following::query( $user_id, 1, null, $args );
			}
		);

		return (int) $preceding['total'];
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
