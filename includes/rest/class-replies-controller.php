<?php
/**
 * Replies_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Replies;

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
	protected $rest_base = '(?P<type>[\w\-\.]+)s/(?P<id>[\w\-\.]+)/replies';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'args'   => array(
					'type' => array(
						'description' => 'The type of object to get replies for.',
						'type'        => 'string',
						'enum'        => array( 'post', 'comment' ),
						'required'    => true,
					),
					'id'   => array(
						'description' => 'The ID of the object.',
						'type'        => 'string',
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
							'default'     => 1,
							'minimum'     => 1,
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
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error object.
	 */
	public function get_items( $request ) {
		$type = $request->get_param( 'type' );
		$id   = (int) $request->get_param( 'id' );

		if ( 'comment' === $type ) {
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
					$type
				),
				array( 'status' => 404 )
			);
		}

		$page = $request->get_param( 'page' );

		// If the request parameter page is present get the CollectionPage otherwise the Replies collection.
		if ( $page ) {
			$response = Replies::get_collection_page( $wp_object, $page );
		} else {
			$response = Replies::get_collection( $wp_object );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Prepend ActivityPub Context.
		$response = array_merge( array( '@context' => Base_Object::JSON_LD_CONTEXT ), $response );

		return \rest_ensure_response( $response );
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
