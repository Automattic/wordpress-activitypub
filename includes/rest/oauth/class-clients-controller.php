<?php
/**
 * OAuth 2.0 Client Registration and Metadata REST Controller.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest\OAuth;

use Activitypub\OAuth\Client;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server as OAuth_Server;

use function Activitypub\get_client_ip;

/**
 * Clients_Controller class for handling OAuth 2.0 client and metadata endpoints.
 *
 * Implements:
 * - Dynamic client registration (POST /oauth/clients)
 * - Authorization Server Metadata (GET /oauth/authorization-server-metadata)
 *
 * @since 8.1.0
 */
class Clients_Controller extends \WP_REST_Controller {
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
	protected $rest_base = 'oauth';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// Dynamic client registration (RFC 7591).
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/clients',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register_client' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'client_name'   => array(
							'description' => 'Human-readable name of the client.',
							'type'        => 'string',
							'required'    => true,
						),
						'redirect_uris' => array(
							'description' => 'Array of redirect URIs. Supports custom URI schemes for native apps.',
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
							),
							'required'    => true,
						),
						'client_uri'    => array(
							'description' => 'URL of the client homepage.',
							'type'        => 'string',
							'format'      => 'uri',
						),
						'scope'         => array(
							'description' => 'Space-separated list of requested scopes.',
							'type'        => 'string',
						),
					),
				),
			)
		);

		// Authorization Server Metadata (RFC 8414).
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/authorization-server-metadata',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_metadata' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Handle dynamic client registration (POST /oauth/clients).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function register_client( \WP_REST_Request $request ) {
		/**
		 * Filters whether RFC 7591 dynamic client registration is allowed.
		 *
		 * Enabled by default so C2S clients can register on the fly.
		 * Return false to restrict registration to pre-configured clients only.
		 *
		 * @param bool $allowed Whether dynamic registration is allowed. Default true.
		 */
		if ( ! \apply_filters( 'activitypub_allow_dynamic_client_registration', true ) ) {
			return new \WP_Error(
				'activitypub_registration_disabled',
				\__( 'Dynamic client registration is not allowed.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		// Rate-limit registrations to prevent DB spam (max 10 per minute per IP).
		$ip            = get_client_ip();
		$transient_key = 'ap_oauth_reg_' . \md5( $ip );
		$count         = (int) \get_transient( $transient_key );

		if ( $count >= 10 ) {
			return new \WP_Error(
				'activitypub_rate_limited',
				\__( 'Too many client registration requests. Please try again later.', 'activitypub' ),
				array( 'status' => 429 )
			);
		}

		\set_transient( $transient_key, $count + 1, MINUTE_IN_SECONDS );

		$client_name   = $request->get_param( 'client_name' );
		$redirect_uris = $request->get_param( 'redirect_uris' );
		$client_uri    = $request->get_param( 'client_uri' );
		$scope         = $request->get_param( 'scope' );

		$result = Client::register(
			array(
				'name'          => $client_name,
				'redirect_uris' => $redirect_uris,
				'description'   => $client_uri ?? '',
				'is_public'     => true, // Dynamic clients are always public.
				'scopes'        => $scope ? Scope::parse( $scope ) : Scope::ALL,
			)
		);

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		// RFC 7591 response format.
		$response = array(
			'client_id'                  => $result['client_id'],
			'client_name'                => $client_name,
			'redirect_uris'              => $redirect_uris,
			'token_endpoint_auth_method' => 'none',
		);

		if ( isset( $result['client_secret'] ) ) {
			$response['client_secret'] = $result['client_secret'];
		}

		return new \WP_REST_Response( $response, 201 );
	}

	/**
	 * Get OAuth server metadata.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_metadata() {
		return new \WP_REST_Response(
			OAuth_Server::get_metadata(),
			200,
			array( 'Content-Type' => 'application/json' )
		);
	}
}
