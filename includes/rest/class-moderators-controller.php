<?php
/**
 * Moderators_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Actors;

use function Activitypub\get_context;
use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub Moderators_Controller class.
 */
class Moderators_Controller extends \WP_REST_Controller {

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
	protected $rest_base = 'collections/moderators';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieves a collection of moderators.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error object.
	 */
	public function get_items( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$actors = array();

		foreach ( Actors::get_collection() as $user ) {
			$actors[] = $user->get_id();
		}

		/**
		 * Filter the list of moderators.
		 *
		 * @param array $actors The list of moderators.
		 */
		$actors = apply_filters( 'activitypub_rest_moderators', $actors );

		$response = array(
			'@context'     => get_context(),
			'id'           => get_rest_url_by_path( 'collections/moderators' ),
			'type'         => 'OrderedCollection',
			'orderedItems' => $actors,
		);

		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Retrieves the schema for the Moderators endpoint.
	 *
	 * @return array Schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'moderators',
			'type'       => 'object',
			'properties' => array(
				'@context'     => array(
					'type'     => 'array',
					'items'    => array(
						'type' => array( 'string', 'object' ),
					),
					'required' => true,
				),
				'id'           => array(
					'type'     => 'string',
					'format'   => 'uri',
					'required' => true,
				),
				'type'         => array(
					'type'     => 'string',
					'enum'     => array( 'OrderedCollection' ),
					'required' => true,
				),
				'orderedItems' => array(
					'type'     => 'array',
					'items'    => array(
						'type'   => 'string',
						'format' => 'uri',
					),
					'required' => true,
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
