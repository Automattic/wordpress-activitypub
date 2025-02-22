<?php
/**
 * ActivityPub URL Validator Controller.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Activitypub\Http;

use function Activitypub\get_embed_html;
use function Activitypub\is_activity;

/**
 * URL Validator Controller Class.
 */
class URL_Validator_Controller extends WP_REST_Controller {
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
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'validate' ),
					'args'                => array(
						'url' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'esc_url_raw',
						),
					),
					'permission_callback' => array( $this, 'validate_url_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Check if a given request has access to validate URLs.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return true|WP_Error True if the request has access, WP_Error object otherwise.
	 */
	public function validate_url_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check if URL is a valid ActivityPub endpoint.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function validate( $request ) {
		$url = $request->get_param( 'url' );

		if ( ! $url ) {
			return new \WP_Error(
				'activitypub_no_url',
				__( 'No URL provided.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// sanity check: parse it, see if its a good URL at least.
		$parts = \wp_parse_url( $url );
		if ( ! $parts || ! \array_key_exists( 'scheme', $parts ) ) {
			return new \WP_Error(
				'activitypub_invalid_url',
				__( 'Invalid URL.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

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
			'html' => false,
		);

		if ( $response['is_activitypub'] ) {
			$response['html'] = get_embed_html( $url, false );
		}

		return rest_ensure_response( $response );
	}
}
