<?php
/**
 * Liked_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;

use function Activitypub\get_masked_wp_version;
use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub Liked Controller.
 *
 * Serves the `liked` collection for an actor, listing all objects
 * the actor has liked that have not been subsequently undone.
 *
 * @see https://www.w3.org/TR/activitypub/#liked
 *
 * @since 8.1.0
 */
class Liked_Controller extends Actors_Controller {
	use Collection;

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/liked',
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
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 100,
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieves the liked collection for an actor.
	 *
	 * Queries the outbox for Like activities, excluding those that
	 * have been subsequently undone. Handles re-likes correctly by
	 * checking the most recent activity for each object.
	 *
	 * @since 8.1.0
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object.
	 */
	public function get_items( $request ) {
		$user_id  = $request->get_param( 'user_id' );
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$liked_objects = $this->get_liked_object_ids( $user_id );

		// Paginate the results.
		$offset        = ( null !== $page ) ? ( $page - 1 ) * $per_page : 0;
		$ordered_items = array_slice( $liked_objects, $offset, $per_page );

		$response = array(
			'@context'     => Base_Object::JSON_LD_CONTEXT,
			'id'           => get_rest_url_by_path( sprintf( 'actors/%d/liked', $user_id ) ),
			'generator'    => 'https://wordpress.org/?v=' . get_masked_wp_version(),
			'actor'        => Actors::get_by_id( $user_id )->get_id(),
			'type'         => 'OrderedCollection',
			'totalItems'   => count( $liked_objects ),
			'orderedItems' => $ordered_items,
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
	 * Get all currently liked object IDs for an actor.
	 *
	 * Queries all Like and Undo activities from the outbox, ordered
	 * newest first. For each unique object ID, the most recent
	 * activity determines whether the object is still liked.
	 *
	 * @since 8.1.0
	 *
	 * @param int $user_id The actor user ID.
	 * @return string[] Array of liked object URLs.
	 */
	private function get_liked_object_ids( $user_id ) {
		$args = array(
			// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page' => 1000,
			'post_type'      => Outbox::POST_TYPE,
			'post_status'    => 'any',
			'orderby'        => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => '_activitypub_activity_actor',
					'value' => Actors::get_type_by_id( $user_id ),
				),
				array(
					'key'     => '_activitypub_activity_type',
					'value'   => array( 'Like', 'Undo' ),
					'compare' => 'IN',
				),
			),
		);

		if ( $user_id > 0 ) {
			$args['author'] = $user_id;
		}

		$posts = \get_posts( $args );

		\update_postmeta_cache( \wp_list_pluck( $posts, 'ID' ) );

		/*
		 * Walk newest-first. For each unique object ID, the first
		 * occurrence determines the current state: if it is a Like
		 * the object is still liked, if it is an Undo the like was
		 * revoked. Skip any object ID we have already seen.
		 */
		$seen  = array();
		$liked = array();

		foreach ( $posts as $post ) {
			$object_id     = \get_post_meta( $post->ID, '_activitypub_object_id', true );
			$activity_type = \get_post_meta( $post->ID, '_activitypub_activity_type', true );

			if ( ! $object_id || isset( $seen[ $object_id ] ) ) {
				continue;
			}

			$seen[ $object_id ] = true;

			if ( 'Like' === $activity_type ) {
				$liked[] = $object_id;
			}
		}

		return $liked;
	}

	/**
	 * Retrieves the schema for the liked endpoint.
	 *
	 * @since 8.1.0
	 *
	 * @return array Schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$item_schema = array(
			'type'   => 'string',
			'format' => 'uri',
		);

		$schema = $this->get_collection_schema( $item_schema );

		$schema['title']                   = 'liked';
		$schema['properties']['actor']     = array(
			'description' => 'The actor who owns this liked collection.',
			'type'        => 'string',
			'format'      => 'uri',
			'required'    => true,
		);
		$schema['properties']['generator'] = array(
			'description' => 'The software used to generate the collection.',
			'type'        => 'string',
			'format'      => 'uri',
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
