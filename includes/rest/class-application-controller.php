<?php
/**
 * Application Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Model\Application;

/**
 * ActivityPub Application Controller.
 */
class Application_Controller extends \WP_REST_Controller {
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
	protected $rest_base = 'application';

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
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieves the application actor profile.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_item( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$json = ( new Application() )->to_array();

		$rest_response = new \WP_REST_Response( $json, 200 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $rest_response;
	}

	/**
	 * Retrieves the schema for the application endpoint.
	 *
	 * @return array Schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'application',
			'type'       => 'object',
			'properties' => array(
				'@context'                  => array(
					'type'  => 'array',
					'items' => array(
						'type' => array( 'string', 'object' ),
					),
				),
				'id'                        => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'type'                      => array(
					'type' => 'string',
					'enum' => array( 'Application' ),
				),
				'name'                      => array(
					'type' => 'string',
				),
				'icon'                      => array(
					'type'       => 'object',
					'properties' => array(
						'type' => array(
							'type' => 'string',
						),
						'url'  => array(
							'type'   => 'string',
							'format' => 'uri',
						),
					),
				),
				'published'                 => array(
					'type'   => 'string',
					'format' => 'date-time',
				),
				'summary'                   => array(
					'type' => 'string',
				),
				'url'                       => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'inbox'                     => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'outbox'                    => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'streams'                   => array(
					'type'  => 'array',
					'items' => array(
						'type' => 'string',
					),
				),
				'preferredUsername'         => array(
					'type' => 'string',
				),
				'publicKey'                 => array(
					'type'       => 'object',
					'properties' => array(
						'id'           => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'owner'        => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'publicKeyPem' => array(
							'type' => 'string',
						),
					),
				),
				'manuallyApprovesFollowers' => array(
					'type' => 'boolean',
				),
				'discoverable'              => array(
					'type' => 'boolean',
				),
				'indexable'                 => array(
					'type' => 'boolean',
				),
				'webfinger'                 => array(
					'type' => 'string',
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
