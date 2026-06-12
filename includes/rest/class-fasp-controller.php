<?php
/**
 * FASP controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Fasp\Registrations;
use Activitypub\Signature\Http_Message_Signature;

use function Activitypub\get_client_ip;

/**
 * ActivityPub FASP Controller.
 *
 * Implements the fediverse-server side of the Fediverse Auxiliary Service
 * Provider (FASP) specification v0.1: the `/registration` endpoint that
 * providers call to request access. Capability discovery and activation are
 * outbound calls to the provider, see {@see \Activitypub\Fasp\Client}.
 *
 * @see https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/blob/main/general/v0.1/registration.md
 */
class Fasp_Controller extends \WP_REST_Controller {

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
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/registration',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_registration' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'name'      => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => 'The name of the FASP.',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'baseUrl'   => array(
							'required'          => true,
							'type'              => 'string',
							'format'            => 'uri',
							'description'       => 'The base URL of the FASP (must be HTTPS).',
							'sanitize_callback' => 'esc_url_raw',
							'validate_callback' => array( $this, 'validate_https_url' ),
						),
						'serverId'  => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => 'The identifier the FASP generated for this server.',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'publicKey' => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => 'The FASP public key, base64 encoded.',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				'schema' => array( $this, 'get_registration_schema' ),
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
		// Rate-limit registrations to prevent DB spam (max 10 per minute per IP).
		$rate_limit = $this->check_rate_limit();
		if ( \is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$fasp_public_key = $request->get_param( 'publicKey' );
		$server_id       = $request->get_param( 'serverId' );

		// Validate Ed25519 public key format (must be valid base64, 32 bytes when decoded).
		$validation = $this->validate_ed25519_public_key( $fasp_public_key );
		if ( \is_wp_error( $validation ) ) {
			return $validation;
		}

		// Enforce serverId uniqueness.
		if ( Registrations::get_by_server_id( $server_id ) ) {
			return new \WP_Error(
				'server_id_exists',
				'A FASP with this serverId is already registered',
				array( 'status' => 409 )
			);
		}

		$registration = Registrations::create(
			array(
				'name'            => $request->get_param( 'name' ),
				'base_url'        => $request->get_param( 'baseUrl' ),
				'server_id'       => $server_id,
				'fasp_public_key' => $fasp_public_key,
			)
		);

		if ( ! $registration ) {
			return new \WP_Error(
				'storage_failed',
				'Failed to store registration request',
				array( 'status' => 500 )
			);
		}

		$response_data = array(
			'faspId'                    => $registration['fasp_id'],
			'publicKey'                 => $registration['server_public_key'],
			'registrationCompletionUri' => \admin_url( 'options-general.php?page=activitypub&tab=fasp-registrations&highlight=' . \rawurlencode( $registration['fasp_id'] ) ),
		);

		$response = new \WP_REST_Response( $response_data, 201 );

		/*
		 * The FASP spec requires all responses to be signed over `@status` and
		 * `content-digest`, using the keypair generated for this registration
		 * under the serverId the provider allocated for this site.
		 */
		$signature = new Http_Message_Signature();
		$response->header( 'Content-Digest', $signature->generate_digest( \wp_json_encode( $response_data ) ) );
		$signature->sign_response_ed25519(
			$response,
			\base64_decode( $registration['server_private_key'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$registration['server_id']
		);

		return $response;
	}

	/**
	 * Rate-limit registration requests per IP.
	 *
	 * @return true|\WP_Error True if the request may proceed, WP_Error (429) otherwise.
	 */
	private function check_rate_limit() {
		$ip = get_client_ip();
		if ( '' === $ip ) {
			return $this->rate_limit_error();
		}

		$transient_key = 'ap_fasp_reg_' . \md5( $ip );
		$count         = (int) \get_transient( $transient_key );

		if ( $count >= 10 ) {
			return $this->rate_limit_error();
		}

		\set_transient( $transient_key, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Build the rate-limit error.
	 *
	 * @return \WP_Error The 429 error.
	 */
	private function rate_limit_error() {
		return new \WP_Error(
			'activitypub_rate_limited',
			\__( 'Too many registration requests. Please try again later.', 'activitypub' ),
			array( 'status' => 429 )
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

	/**
	 * Validate an Ed25519 public key format.
	 *
	 * @param string $public_key The base64-encoded public key.
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_ed25519_public_key( $public_key ) {
		// Check if valid base64.
		$decoded = \base64_decode( $public_key, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded ) {
			return new \WP_Error(
				'invalid_public_key',
				'Public key is not valid base64',
				array( 'status' => 400 )
			);
		}

		// Ed25519 public keys must be exactly 32 bytes.
		if ( \strlen( $decoded ) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ) {
			return new \WP_Error(
				'invalid_public_key_length',
				\sprintf(
					'Invalid Ed25519 public key length: expected %d bytes, got %d',
					SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES,
					\strlen( $decoded )
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validate that a URL uses HTTPS scheme.
	 *
	 * @param string $url The URL to validate.
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_https_url( $url ) {
		$scheme = \wp_parse_url( $url, \PHP_URL_SCHEME );

		if ( 'https' !== $scheme ) {
			return new \WP_Error(
				'invalid_url_scheme',
				\__( 'The base URL must use HTTPS.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}
}
