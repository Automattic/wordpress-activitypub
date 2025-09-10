<?php
/**
 * ActivityPub URL Validator Controller.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Embed;
use Activitypub\Http;

/**
 * URL Validator Controller Class.
 */
class URL_Validator_Controller extends \WP_REST_Controller {
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
	protected $rest_base = 'url/validate';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'url' => array(
							'type'     => 'string',
							'format'   => 'uri',
							'required' => true,
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Check if a given request has access to validate URLs.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return bool True if the request has access to validate URLs, false otherwise.
	 */
	public function get_items_permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if URL is a valid ActivityPub endpoint.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_items( $request ) {
		$url    = $request->get_param( 'url' );
		$object = Http::get_remote_object( $url );

		if ( is_wp_error( $object ) ) {
			return new \WP_Error(
				'activitypub_invalid_url',
				__( 'Invalid URL.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		$response = array(
			'is_activitypub' => ! empty( $object['type'] ),
			'is_real_oembed' => Embed::has_real_oembed( $url ),
			'html'           => false,
		);

		if ( $response['is_activitypub'] ) {
			$response['html'] = wp_oembed_get( $url );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Get the URL validation schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'validated-url',
			'type'       => 'object',
			'properties' => array(
				'is_activitypub' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'is_real_oembed' => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'html'           => array(
					'type'    => 'string',
					'default' => false,
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
