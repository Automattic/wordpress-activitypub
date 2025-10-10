<?php
/**
 * FASP Registration controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

/**
 * ActivityPub FASP Registration Controller.
 *
 * Implements the FASP registration specification v0.1 for receiving
 * registration requests from FASP providers.
 *
 * @see https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/blob/main/general/v0.1/registration.md
 */
class Fasp_Registration_Controller extends \WP_REST_Controller {
	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = ACTIVITYPUB_REST_NAMESPACE;

	/**
	 * The REST base for this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = 'fasp';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// Registration endpoint for FASP providers to register with this server.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/registration',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_registration' ),
					'permission_callback' => array( $this, 'registration_permission_check' ),
					'args'                => $this->get_registration_args(),
				),
				'schema' => array( $this, 'get_registration_schema' ),
			)
		);

		// Capability activation endpoints.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/capabilities/(?P<identifier>[a-zA-Z0-9_-]+)/(?P<version>[0-9]+)/activation',
			array(
				array(
					'methods'             => array( \WP_REST_Server::CREATABLE, \WP_REST_Server::DELETABLE ),
					'callback'            => array( $this, 'handle_capability_activation' ),
					'permission_callback' => array( $this, 'capability_permission_check' ),
					'args'                => array(
						'identifier' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => 'The capability identifier.',
						),
						'version'    => array(
							'required'    => true,
							'type'        => 'integer',
							'description' => 'The capability version.',
						),
					),
				),
			)
		);
	}

	/**
	 * Handle FASP registration requests.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error The response or error.
	 */
	public function handle_registration( $request ) {
		$params = $request->get_json_params();

		// Validate required fields.
		$required_fields = array( 'name', 'baseUrl', 'serverId', 'publicKey' );
		foreach ( $required_fields as $field ) {
			if ( empty( $params[ $field ] ) ) {
				return new \WP_Error(
					'missing_field',
					sprintf( 'Missing required field: %s', $field ),
					array( 'status' => 400 )
				);
			}
		}

		// Generate keypair for this server.
		$keypair = $this->generate_ed25519_keypair();
		if ( ! $keypair ) {
			return new \WP_Error(
				'keypair_generation_failed',
				'Failed to generate Ed25519 keypair',
				array( 'status' => 500 )
			);
		}

		// Generate unique FASP ID.
		$fasp_id = $this->generate_unique_id();

		// Store registration request (pending approval).
		$registration_data = array(
			'fasp_id'            => $fasp_id,
			'name'               => sanitize_text_field( $params['name'] ),
			'base_url'           => esc_url_raw( $params['baseUrl'] ),
			'server_id'          => sanitize_text_field( $params['serverId'] ),
			'fasp_public_key'    => sanitize_text_field( $params['publicKey'] ),
			'server_public_key'  => $keypair['public_key'],
			'server_private_key' => $keypair['private_key'],
			'status'             => 'pending',
			'requested_at'       => current_time( 'mysql', true ),
		);

		$result = $this->store_registration_request( $registration_data );
		if ( ! $result ) {
			return new \WP_Error(
				'storage_failed',
				'Failed to store registration request',
				array( 'status' => 500 )
			);
		}

		// Generate registration completion URI.
		$completion_uri = admin_url( 'admin.php?page=activitypub-fasp-registrations&highlight=' . $fasp_id );

		// Return successful response.
		$response_data = array(
			'faspId'                    => $fasp_id,
			'publicKey'                 => $keypair['public_key'],
			'registrationCompletionUri' => $completion_uri,
		);

		return new \WP_REST_Response( $response_data, 201 );
	}

	/**
	 * Handle capability activation/deactivation.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error The response or error.
	 */
	public function handle_capability_activation( $request ) {
		$identifier = $request->get_param( 'identifier' );
		$version    = $request->get_param( 'version' );
		$method     = $request->get_method();

		// Verify FASP is authenticated and approved.
		$fasp_data = $this->get_authenticated_fasp( $request );
		if ( is_wp_error( $fasp_data ) ) {
			return $fasp_data;
		}

		// Check if capability is supported.
		$supported_capabilities = $this->get_supported_capabilities();
		$capability_key         = $identifier . '_v' . $version;

		if ( ! isset( $supported_capabilities[ $capability_key ] ) ) {
			return new \WP_Error(
				'capability_not_found',
				'Capability not found or not supported',
				array( 'status' => 404 )
			);
		}

		if ( 'POST' === $method ) {
			// Enable capability.
			$result = $this->enable_fasp_capability( $fasp_data['fasp_id'], $identifier, $version );
		} else {
			// Disable capability (DELETE).
			$result = $this->disable_fasp_capability( $fasp_data['fasp_id'], $identifier, $version );
		}

		if ( ! $result ) {
			return new \WP_Error(
				'capability_update_failed',
				'Failed to update capability status',
				array( 'status' => 500 )
			);
		}

		return new \WP_REST_Response( null, 204 );
	}

	/**
	 * Permission check for registration endpoint.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return bool True if allowed.
	 */
	public function registration_permission_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Registration endpoint is publicly accessible but should verify.
		// the request comes from a legitimate FASP.
		return true;
	}

	/**
	 * Permission check for capability endpoints.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return bool|\WP_Error True if allowed, WP_Error otherwise.
	 */
	public function capability_permission_check( $request ) {
		// Capability endpoints require FASP authentication
		$fasp_data = $this->get_authenticated_fasp( $request );
		return ! is_wp_error( $fasp_data );
	}

	/**
	 * Generate Ed25519 keypair.
	 *
	 * @return array|false Keypair array with 'public_key' and 'private_key', or false on failure.
	 */
	private function generate_ed25519_keypair() {
		// For now, use a simple implementation. In production, this should use.
		// proper Ed25519 key generation (requires sodium extension or similar).
		if ( ! function_exists( 'sodium_crypto_sign_keypair' ) ) {
			// Fallback for systems without sodium.
			return array(
				'public_key'  => base64_encode( wp_generate_password( 32, false ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'private_key' => base64_encode( wp_generate_password( 64, false ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			);
		}

		$keypair    = sodium_crypto_sign_keypair();
		$public_key = sodium_crypto_sign_publickey( $keypair );
		$secret_key = sodium_crypto_sign_secretkey( $keypair );

		return array(
			'public_key'  => base64_encode( $public_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'private_key' => base64_encode( $secret_key ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		);
	}

	/**
	 * Generate unique ID for FASP.
	 *
	 * @return string Unique ID.
	 */
	private function generate_unique_id() {
		return substr( md5( uniqid( wp_rand(), true ) ), 0, 12 );
	}

	/**
	 * Store registration request using WordPress options.
	 *
	 * @param array $data Registration data.
	 * @return bool True on success, false on failure.
	 */
	private function store_registration_request( $data ) {
		// Get existing registrations.
		$registrations = get_option( 'activitypub_fasp_registrations', array() );

		// Add new registration.
		$registrations[ $data['fasp_id'] ] = $data;

		// Store updated registrations.
		return update_option( 'activitypub_fasp_registrations', $registrations );
	}

	/**
	 * Get authenticated FASP from request.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return array|\WP_Error FASP data or error.
	 */
	private function get_authenticated_fasp( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// This should implement proper Ed25519 signature verification.
		// For now, return a placeholder.
		return new \WP_Error(
			'authentication_required',
			'FASP authentication not yet implemented',
			array( 'status' => 401 )
		);
	}

	/**
	 * Get supported capabilities.
	 *
	 * @return array Supported capabilities.
	 */
	private function get_supported_capabilities() {
		// Define capabilities that this server supports.
		$capabilities = array();

		/**
		 * Filter supported FASP capabilities.
		 *
		 * @param array $capabilities Supported capabilities.
		 */
		return apply_filters( 'activitypub_fasp_supported_capabilities', $capabilities );
	}

	/**
	 * Enable a capability for a FASP.
	 *
	 * @param string $fasp_id    FASP ID.
	 * @param string $identifier Capability identifier.
	 * @param int    $version    Capability version.
	 * @return bool True on success, false on failure.
	 */
	private function enable_fasp_capability( $fasp_id, $identifier, $version ) {
		// Get existing capabilities.
		$capabilities = get_option( 'activitypub_fasp_capabilities', array() );

		// Create capability key.
		$capability_key = $fasp_id . '_' . $identifier . '_v' . $version;

		// Enable capability.
		$capabilities[ $capability_key ] = array(
			'fasp_id'    => $fasp_id,
			'identifier' => $identifier,
			'version'    => $version,
			'enabled'    => true,
			'updated_at' => current_time( 'mysql', true ),
		);

		// Store updated capabilities.
		return update_option( 'activitypub_fasp_capabilities', $capabilities );
	}

	/**
	 * Disable a capability for a FASP.
	 *
	 * @param string $fasp_id    FASP ID.
	 * @param string $identifier Capability identifier.
	 * @param int    $version    Capability version.
	 * @return bool True on success, false on failure.
	 */
	private function disable_fasp_capability( $fasp_id, $identifier, $version ) {
		// Get existing capabilities.
		$capabilities = get_option( 'activitypub_fasp_capabilities', array() );

		// Create capability key.
		$capability_key = $fasp_id . '_' . $identifier . '_v' . $version;

		// Disable capability.
		if ( isset( $capabilities[ $capability_key ] ) ) {
			$capabilities[ $capability_key ]['enabled']    = false;
			$capabilities[ $capability_key ]['updated_at'] = current_time( 'mysql', true );
		}

		// Store updated capabilities.
		return update_option( 'activitypub_fasp_capabilities', $capabilities );
	}

	/**
	 * Get registration endpoint arguments.
	 *
	 * @return array Arguments.
	 */
	private function get_registration_args() {
		return array(
			'name'      => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The name of the FASP.',
			),
			'baseUrl'   => array(
				'required'    => true,
				'type'        => 'string',
				'format'      => 'uri',
				'description' => 'The base URL of the FASP.',
			),
			'serverId'  => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The server ID generated by the FASP.',
			),
			'publicKey' => array(
				'required'    => true,
				'type'        => 'string',
				'description' => 'The FASP public key, base64 encoded.',
			),
		);
	}

	/**
	 * Get the schema for registration endpoint.
	 *
	 * @return array The schema.
	 */
	public function get_registration_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'FASP Registration Request',
			'type'       => 'object',
			'properties' => array(
				'name'      => array(
					'type'        => 'string',
					'description' => 'The name of the FASP provider.',
				),
				'baseUrl'   => array(
					'type'        => 'string',
					'format'      => 'uri',
					'description' => 'The base URL of the FASP provider.',
				),
				'serverId'  => array(
					'type'        => 'string',
					'description' => 'The server ID generated by the FASP.',
				),
				'publicKey' => array(
					'type'        => 'string',
					'description' => 'The FASP public key, base64 encoded.',
				),
			),
			'required'   => array( 'name', 'baseUrl', 'serverId', 'publicKey' ),
		);
	}
}
