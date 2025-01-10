<?php
/**
 * WebFinger REST-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

/**
 * ActivityPub WebFinger REST-Class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://webfinger.net/
 */
class Webfinger_Controller extends \WP_REST_Controller {
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
	protected $rest_base = 'webfinger';

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
					'args'                => array(
						'resource' => array(
							'description' => 'The WebFinger resource.',
							'type'        => 'string',
							'required'    => true,
							'pattern'     => '^(acct:)|^(https?://)(.+)$',
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Retrieves the WebFinger profile.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response Response object.
	 */
	public function get_item( $request ) {
		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 */
		\do_action( 'activitypub_rest_webfinger_pre' );

		$resource = $request->get_param( 'resource' );
		$response = $this->get_profile( $resource );
		$code     = 200;

		if ( \is_wp_error( $response ) ) {
			$code       = 400;
			$error_data = $response->get_error_data();

			if ( isset( $error_data['status'] ) ) {
				$code = $error_data['status'];
			}
		}

		return new \WP_REST_Response(
			$response,
			$code,
			array(
				'Access-Control-Allow-Origin' => '*',
				'Content-Type'                => 'application/jrd+json; charset=' . \get_option( 'blog_charset' ),
			)
		);
	}

	/**
	 * Get the WebFinger profile.
	 *
	 * @param string $webfinger The WebFinger resource.
	 *
	 * @return array|\WP_Error The WebFinger profile or WP_Error if not found.
	 */
	public function get_profile( $webfinger ) {
		/**
		 * Filter the WebFinger data.
		 *
		 * @param array  $data      The WebFinger data.
		 * @param string $webfinger The WebFinger resource.
		 */
		return \apply_filters( 'webfinger_data', array(), $webfinger );
	}

	/**
	 * Retrieves the schema for the WebFinger endpoint.
	 *
	 * @return array Schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'webfinger',
			'type'       => 'object',
			'required'   => array( 'subject', 'links' ),
			'properties' => array(
				'subject' => array(
					'description' => 'The subject of this WebFinger record.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'aliases' => array(
					'description' => 'Alternative identifiers for the subject.',
					'type'        => 'array',
					'items'       => array(
						'type'   => 'string',
						'format' => 'uri',
					),
				),
				'links'   => array(
					'description' => 'Links associated with the subject.',
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'rel'      => array(
								'description' => 'The relation type of the link.',
								'type'        => 'string',
								'required'    => true,
							),
							'type'     => array(
								'description' => 'The content type of the link.',
								'type'        => 'string',
							),
							'href'     => array(
								'description' => 'The target URL of the link.',
								'type'        => 'string',
								'format'      => 'uri',
							),
							'template' => array(
								'description' => 'A URI template for the link.',
								'type'        => 'string',
								'format'      => 'uri',
							),
						),
					),
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
