<?php
/**
 * FASP controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

/**
 * ActivityPub FASP Controller.
 *
 * Implements the Fediverse Auxiliary Service Provider (FASP) specification v0.1.
 *
 * @see https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/tree/main/general/v0.1
 */
class Fasp_Controller extends \WP_REST_Controller {
	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = 'activitypub/1.0';

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
			'/' . $this->rest_base . '/provider_info',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_provider_info' ),
					'permission_callback' => array( $this, 'authenticate_request' ),
				),
				'schema' => array( $this, 'get_provider_info_schema' ),
			)
		);
	}

	/**
	 * Get provider info.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response|\WP_Error The response or error.
	 */
	public function get_provider_info( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$provider_info = array(
			'name'          => $this->get_provider_name(),
			'privacyPolicy' => $this->get_privacy_policy(),
			'capabilities'  => $this->get_capabilities(),
		);

		// Add optional fields if configured.
		$sign_in_url = $this->get_sign_in_url();
		if ( $sign_in_url ) {
			$provider_info['signInUrl'] = $sign_in_url;
		}

		$contact_email = $this->get_contact_email();
		if ( $contact_email ) {
			$provider_info['contactEmail'] = $contact_email;
		}

		$fediverse_account = $this->get_fediverse_account();
		if ( $fediverse_account ) {
			$provider_info['fediverseAccount'] = $fediverse_account;
		}

		$response = new \WP_REST_Response( $provider_info );

		// Add content-digest header as required by specification.
		$content = wp_json_encode( $provider_info );
		$digest  = 'sha-256=:' . base64_encode( hash( 'sha256', $content, true ) ) . ':'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$response->header( 'Content-Digest', $digest );

		// Sign the response.
		$this->sign_response( $response, $content );

		return $response;
	}

	/**
	 * Authenticate incoming requests using HTTP Message Signatures.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return bool|\WP_Error True if authenticated, WP_Error otherwise.
	 */
	public function authenticate_request( $request ) {
		// Use the same signature verification as other ActivityPub endpoints.
		return \Activitypub\Rest\Server::verify_signature( $request );
	}

	/**
	 * Sign the response using HTTP Message Signatures.
	 *
	 * @param \WP_REST_Response $response The response to sign.
	 * @param string            $content  The response content.
	 */
	private function sign_response( $response, $content ) {
		// Skip signing if RFC-9421 signatures are not enabled.
		if ( '1' !== \get_option( 'activitypub_rfc9421_signature' ) ) {
			return;
		}

		try {
			// Use the blog/application actor for signing FASP responses.
			$blog_user_id = \Activitypub\Collection\Actors::APPLICATION_USER_ID;
			$private_key  = \Activitypub\Collection\Actors::get_private_key( $blog_user_id );
			$actor        = \Activitypub\Collection\Actors::get_by_id( $blog_user_id );

			if ( ! $private_key || ! $actor ) {
				return;
			}

			// Create signature components for response.
			$components = array(
				'"@status"'        => (string) $response->get_status(),
				'"content-digest"' => $response->get_headers()['Content-Digest'] ?? '',
			);

			$params = array(
				'created' => \time(),
				'keyid'   => $actor->get_id() . '#main-key',
				'alg'     => 'rsa-v1_5-sha256',
			);

			// Build signature base string.
			$signature_base = $this->build_signature_base( $components, $params );

			// Sign the base string.
			$signature = null;
			\openssl_sign( $signature_base, $signature, $private_key, \OPENSSL_ALGO_SHA256 );
			$signature_b64 = \base64_encode( $signature ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

			// Add signature headers.
			$identifiers = \array_keys( $components );
			$params_str  = $this->build_params_string( $params );

			$response->header( 'Signature-Input', 'fasp=(' . \implode( ' ', $identifiers ) . ')' . $params_str );
			$response->header( 'Signature', 'fasp=:' . $signature_b64 . ':' );

		} catch ( \Exception $e ) {
			// Silently fail - don't break the response if signing fails.
			// In production, this could be logged to a debug log if needed.
			unset( $e );
		}
	}

	/**
	 * Build signature base string according to RFC-9421.
	 *
	 * @param array $components Signature components.
	 * @param array $params     Signature parameters.
	 * @return string Signature base string.
	 */
	private function build_signature_base( $components, $params ) {
		$lines = array();

		foreach ( $components as $identifier => $value ) {
			$lines[] = $identifier . ': ' . $value;
		}

		$lines[] = '"@signature-params": ' . $this->build_signature_params( \array_keys( $components ), $params );

		return \implode( "\n", $lines );
	}

	/**
	 * Build signature parameters string.
	 *
	 * @param array $identifiers Component identifiers.
	 * @param array $params      Signature parameters.
	 * @return string Signature parameters.
	 */
	private function build_signature_params( $identifiers, $params ) {
		$params_parts = array();
		foreach ( $params as $key => $value ) {
			$params_parts[] = $key . '=' . $value;
		}

		return '(' . \implode( ' ', $identifiers ) . ');' . \implode( ';', $params_parts );
	}

	/**
	 * Build parameters string for signature input header.
	 *
	 * @param array $params Signature parameters.
	 * @return string Parameters string.
	 */
	private function build_params_string( $params ) {
		$parts = array();
		foreach ( $params as $key => $value ) {
			if ( 'keyid' === $key ) {
				$parts[] = $key . '="' . $value . '"';
			} else {
				$parts[] = $key . '=' . $value;
			}
		}

		return ';' . \implode( ';', $parts );
	}

	/**
	 * Get the provider name.
	 *
	 * @return string The provider name.
	 */
	private function get_provider_name() {
		$site_name = \get_bloginfo( 'name' );
		return $site_name ? $site_name . ' ActivityPub FASP' : 'WordPress ActivityPub FASP';
	}

	/**
	 * Get privacy policy information.
	 *
	 * @return array Privacy policy array.
	 */
	private function get_privacy_policy() {
		$privacy_policy_url = \get_privacy_policy_url();
		if ( ! $privacy_policy_url ) {
			return array();
		}

		return array(
			array(
				'url'      => $privacy_policy_url,
				'language' => \get_locale(),
			),
		);
	}

	/**
	 * Get supported capabilities.
	 *
	 * @return array Capabilities array.
	 */
	private function get_capabilities() {
		// Basic capabilities - can be extended by filters or settings.
		$capabilities = array();

		/**
		 * Filter the FASP capabilities.
		 *
		 * @param array $capabilities Current capabilities.
		 */
		return \apply_filters( 'activitypub_fasp_capabilities', $capabilities );
	}

	/**
	 * Get sign-in URL.
	 *
	 * @return string|null Sign-in URL or null if not configured.
	 */
	private function get_sign_in_url() {
		// Return WordPress admin URL as sign-in URL.
		return \admin_url();
	}

	/**
	 * Get contact email.
	 *
	 * @return string|null Contact email or null if not configured.
	 */
	private function get_contact_email() {
		return \get_option( 'admin_email' );
	}

	/**
	 * Get fediverse account.
	 *
	 * @return string|null Fediverse account or null if not configured.
	 */
	private function get_fediverse_account() {
		// This could be made configurable via settings.
		return null;
	}

	/**
	 * Get the schema for provider info endpoint.
	 *
	 * @return array The schema.
	 */
	public function get_provider_info_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'FASP Provider Info',
			'type'       => 'object',
			'properties' => array(
				'name'             => array(
					'type'        => 'string',
					'description' => 'The name of the FASP provider.',
				),
				'privacyPolicy'    => array(
					'type'        => 'array',
					'description' => 'Privacy policy information.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'url'      => array(
								'type'   => 'string',
								'format' => 'uri',
							),
							'language' => array(
								'type' => 'string',
							),
						),
					),
				),
				'capabilities'     => array(
					'type'        => 'array',
					'description' => 'Supported capabilities.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'      => array(
								'type' => 'string',
							),
							'version' => array(
								'type' => 'string',
							),
						),
					),
				),
				'signInUrl'        => array(
					'type'        => 'string',
					'format'      => 'uri',
					'description' => 'URL where administrators can sign in.',
				),
				'contactEmail'     => array(
					'type'        => 'string',
					'format'      => 'email',
					'description' => 'Contact email address.',
				),
				'fediverseAccount' => array(
					'type'        => 'string',
					'description' => 'Fediverse account for updates.',
				),
			),
			'required'   => array( 'name', 'privacyPolicy', 'capabilities' ),
		);
	}
}
