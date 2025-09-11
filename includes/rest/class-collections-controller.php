<?php
/**
 * Collections_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Actors;
use Activitypub\Transformer\Factory;

use function Activitypub\esc_hashtag;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\is_single_user;

/**
 * Collections_Controller class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://docs.joinmastodon.org/spec/activitypub/#featured
 * @see https://docs.joinmastodon.org/spec/activitypub/#featuredTags
 * @see https://www.w3.org/TR/activitypub/#collections
 */
class Collections_Controller extends Actors_Controller {
	use Collection;

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/collections/(?P<type>[\w\-\.]+)',
			array(
				'args'   => array(
					'user_id' => array(
						'description'       => 'The user ID or username.',
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_user_id' ),
					),
					'type'    => array(
						'description' => 'The type of collection to query.',
						'type'        => 'string',
						'enum'        => array( 'tags', 'featured' ),
						'required'    => true,
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
							'minimum'     => 1,
							// No default so we can differentiate between Collection and CollectionPage requests.
						),
						'per_page' => array(
							'description' => 'Maximum number of items to be returned in result set.',
							'type'        => 'integer',
							'default'     => 20,
							'minimum'     => 1,
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieves a collection of featured tags.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error object.
	 */
	public function get_items( $request ) {
		$user_id = $request->get_param( 'user_id' );

		switch ( $request->get_param( 'type' ) ) {
			case 'tags':
				$response = $this->get_tags( $request, $user_id );
				break;

			case 'featured':
				$response = $this->get_featured( $request, $user_id );
				break;

			default:
				$response = new \WP_Error( 'rest_unknown_collection_type', 'Unknown collection type.', array( 'status' => 404 ) );
		}

		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Retrieves a collection of featured tags.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id Actor ID.
	 *
	 * @return array Collection of featured tags.
	 */
	public function get_tags( $request, $user_id ) {
		$tags = \get_terms(
			array(
				'taxonomy' => 'post_tag',
				'orderby'  => 'count',
				'order'    => 'DESC',
				'number'   => 4,
			)
		);

		if ( \is_wp_error( $tags ) ) {
			$tags = array();
		}

		$response = array(
			'@context'   => Base_Object::JSON_LD_CONTEXT,
			'id'         => get_rest_url_by_path( sprintf( 'actors/%d/collections/tags', $user_id ) ),
			'type'       => 'Collection',
			'totalItems' => \is_countable( $tags ) ? \count( $tags ) : 0,
			'items'      => array(),
		);

		foreach ( $tags as $tag ) {
			$response['items'][] = array(
				'type' => 'Hashtag',
				'href' => \esc_url( \get_tag_link( $tag ) ),
				'name' => esc_hashtag( $tag->name ),
			);
		}

		return $this->prepare_collection_response( $response, $request );
	}

	/**
	 * Retrieves a collection of featured posts.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @param int              $user_id Actor ID.
	 *
	 * @return array Collection of featured posts.
	 */
	public function get_featured( $request, $user_id ) {
		$posts = array();

		if ( is_single_user() || Actors::BLOG_USER_ID !== $user_id ) {
			$sticky_posts = \get_option( 'sticky_posts' );

			if ( $sticky_posts && is_array( $sticky_posts ) ) {
				// Only show public posts.
				$args = array(
					'post__in'            => $sticky_posts,
					'ignore_sticky_posts' => 1,
					'orderby'             => 'date',
					'order'               => 'DESC',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'          => array(
						array(
							'key'     => 'activitypub_content_visibility',
							'compare' => 'NOT EXISTS',
						),
					),
				);

				if ( $user_id > 0 ) {
					$args['author'] = $user_id;
				}

				$posts = \get_posts( $args );
			}
		}

		$response = array(
			'@context'     => Base_Object::JSON_LD_CONTEXT,
			'id'           => get_rest_url_by_path( sprintf( 'actors/%d/collections/featured', $user_id ) ),
			'type'         => 'OrderedCollection',
			'totalItems'   => \is_countable( $posts ) ? \count( $posts ) : 0,
			'orderedItems' => array(),
		);

		foreach ( $posts as $post ) {
			$transformer = Factory::get_transformer( $post );

			if ( \is_wp_error( $transformer ) ) {
				continue;
			}

			$response['orderedItems'][] = $transformer->to_object()->to_array( false );
		}

		return $this->prepare_collection_response( $response, $request );
	}

	/**
	 * Retrieves the schema for the Collections endpoint.
	 *
	 * @return array Schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = $this->get_collection_schema();

		// Add collections-specific properties.
		$schema['title']                   = 'featured';
		$schema['properties']['generator'] = array(
			'description' => 'The software used to generate the collection.',
			'type'        => 'string',
			'format'      => 'uri',
		);
		$schema['properties']['oneOf']     = array(
			'orderedItems' => array(
				'type'  => 'array',
				'items' => array(
					'type' => 'object',
				),
			),
			'items'        => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'type' => array(
							'type'     => 'string',
							'enum'     => array( 'Hashtag' ),
							'required' => true,
						),
						'href' => array(
							'type'     => 'string',
							'format'   => 'uri',
							'required' => true,
						),
						'name' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				),
			),
		);

		unset( $schema['properties']['orderedItems'] );

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
