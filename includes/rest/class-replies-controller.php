<?php
/**
 * Replies_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Interactions;
use Activitypub\Collection\Replies;

use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub Replies_Controller class.
 */
class Replies_Controller extends \WP_REST_Controller {

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
	protected $rest_base = '(?P<object_type>[\w\-\.]+)s/(?P<id>[\w\-\.]+)/(?P<type>[\w\-\.]+)';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'args'   => array(
					'object_type' => array(
						'description' => 'The type of object to get replies for.',
						'type'        => 'string',
						'enum'        => array( 'post', 'comment' ),
						'required'    => true,
					),
					'id'          => array(
						'description' => 'The ID of the object.',
						'type'        => 'string',
						'required'    => true,
					),
					'type'        => array(
						'description' => 'The type of collection to query.',
						'type'        => 'string',
						'enum'        => array( 'replies', 'likes', 'shares' ),
						'required'    => true,
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'page' => array(
							'description' => 'Current page of the collection.',
							'type'        => 'integer',
							'minimum'     => 1,
							// No default so we can differentiate between Collection and CollectionPage requests.
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieves a collection of replies.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error object.
	 */
	public function get_items( $request ) {
		$object_type = $request->get_param( 'object_type' );
		$id          = (int) $request->get_param( 'id' );

		if ( 'comment' === $object_type ) {
			$wp_object = \get_comment( $id );
		} else {
			$wp_object = \get_post( $id );
		}

		if ( ! isset( $wp_object ) || \is_wp_error( $wp_object ) ) {
			return new \WP_Error(
				'activitypub_replies_collection_does_not_exist',
				\sprintf(
					// translators: %s: The type (post, comment, etc.) for which no replies collection exists.
					\__( 'No reply collection exists for the type %s.', 'activitypub' ),
					$object_type
				),
				array( 'status' => 404 )
			);
		}

		switch ( $request->get_param( 'type' ) ) {
			case 'replies':
				$response = $this->get_replies( $request, $wp_object );
				break;

			case 'likes':
				$response = $this->get_likes( $request, $wp_object );
				break;

			case 'shares':
				$response = $this->get_shares( $request, $wp_object );
				break;

			default:
				$response = new \WP_Error( 'rest_unknown_collection_type', 'Unknown collection type.', array( 'status' => 404 ) );
		}

		// Prepend ActivityPub Context.
		$response = array_merge( array( '@context' => Base_Object::JSON_LD_CONTEXT ), $response );
		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Retrieves a collection of replies.
	 *
	 * @param \WP_REST_Request     $request   The request object.
	 * @param \WP_Post|\WP_Comment $wp_object The WordPress object.
	 *
	 * @return array Response collection of replies.
	 */
	public function get_replies( $request, $wp_object ) {
		$page = $request->get_param( 'page' );

		// If the request parameter page is present get the CollectionPage otherwise the Replies collection.
		if ( null === $page ) {
			$response = Replies::get_collection( $wp_object );
		} else {
			$response = Replies::get_collection_page( $wp_object, $page );
		}

		return $response;
	}

	/**
	 * Retrieves a collection of likes.
	 *
	 * @param \WP_REST_Request     $request   The request object.
	 * @param \WP_Post|\WP_Comment $wp_object The WordPress object.
	 *
	 * @return array Response collection of likes.
	 */
	public function get_likes( $request, $wp_object ) {
		if ( $wp_object instanceof \WP_Post ) {
			$likes = Interactions::count_by_type( $wp_object->ID, 'like' );
		} else {
			$likes = 0;
		}

		return array(
			'id'         => get_rest_url_by_path( sprintf( 'posts/%d/likes', $wp_object->ID ) ),
			'type'       => 'Collection',
			'totalItems' => $likes,
		);
	}

	/**
	 * Retrieves a collection of shares.
	 *
	 * @param \WP_REST_Request     $request   The request object.
	 * @param \WP_Post|\WP_Comment $wp_object The WordPress object.
	 *
	 * @return array Response collection of shares.
	 */
	public function get_shares( $request, $wp_object ) {
		if ( $wp_object instanceof \WP_Post ) {
			$shares = Interactions::count_by_type( $wp_object->ID, 'repost' );
		} else {
			$shares = 0;
		}

		return array(
			'id'         => get_rest_url_by_path( sprintf( 'posts/%d/shares', $wp_object->ID ) ),
			'type'       => 'Collection',
			'totalItems' => $shares,
		);
	}

	/**
	 * Retrieves the schema for the Replies endpoint.
	 *
	 * @return array Schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'replies',
			'type'       => 'object',
			'properties' => array(
				'@context' => array(
					'type'     => 'array',
					'items'    => array(
						'type' => 'string',
					),
					'required' => true,
				),
				'id'       => array(
					'type'     => 'string',
					'format'   => 'uri',
					'required' => true,
				),
				'type'     => array(
					'type'     => 'string',
					'enum'     => array( 'Collection', 'OrderedCollection', 'CollectionPage', 'OrderedCollectionPage' ),
					'required' => true,
				),
				'first'    => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'last'     => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'items'    => array(
					'type'  => 'array',
					'items' => array(
						'type' => 'object',
					),
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
