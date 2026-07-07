<?php
/**
 * Shared FASP Testcase file.
 *
 * @package Activitypub
 */

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions -- base64 is the FASP wire format, not obfuscation.

namespace Activitypub\Tests;

use Activitypub\Fasp\Registrations;
use Activitypub\Signature\Http_Message_Signature;

/**
 * Shared base testcase for FASP tests.
 *
 * Holds the Ed25519 keypair fixture, the signed-response builder, a
 * registration factory, and the redirect-capture helper so the controller
 * and admin-settings suites do not each carry their own copy.
 */
abstract class Fasp_TestCase extends \WP_UnitTestCase {

	/**
	 * The FASP-side Ed25519 keypair used in tests.
	 *
	 * @var array
	 */
	protected static $fasp_keys;

	/**
	 * Set up shared test resources.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		$keypair         = \sodium_crypto_sign_keypair();
		self::$fasp_keys = array(
			'public'  => \sodium_crypto_sign_publickey( $keypair ),
			'private' => \sodium_crypto_sign_secretkey( $keypair ),
		);
	}

	/**
	 * Tear down the shared FASP state.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_enable_fasp' );
		\delete_option( Registrations::OPTION_REGISTRATIONS );
		\delete_option( Registrations::OPTION_CAPABILITIES );

		parent::tear_down();
	}

	/**
	 * The FASP public key, base64 encoded.
	 *
	 * @return string
	 */
	protected function fasp_public_key_base64() {
		return \base64_encode( self::$fasp_keys['public'] );
	}

	/**
	 * Create a registration directly in the store.
	 *
	 * @param string $status    The status to set the registration to ('pending' or 'approved').
	 * @param array  $overrides Field overrides for the registration data.
	 * @return array The registration record.
	 */
	protected function create_fasp_registration( $status = 'approved', $overrides = array() ) {
		$registration = Registrations::create(
			\array_merge(
				array(
					'name'            => 'Test FASP',
					'base_url'        => 'https://fasp.example.com',
					'server_id'       => 'test-server-id',
					'fasp_public_key' => $this->fasp_public_key_base64(),
				),
				$overrides
			)
		);

		if ( 'approved' === $status ) {
			Registrations::approve( $registration['fasp_id'], 0 );
		}

		return Registrations::get( $registration['fasp_id'] );
	}

	/**
	 * Build a signed FASP response for `pre_http_request` mocks.
	 *
	 * @param int    $status The response status.
	 * @param string $body   The response body.
	 * @return array The HTTP response array.
	 */
	protected function build_signed_fasp_response( $status, $body ) {
		$signature_helper = new Http_Message_Signature();
		$digest           = $signature_helper->generate_digest( $body );

		$response = new \WP_REST_Response( null, $status );
		$response->header( 'Content-Digest', $digest );
		$signature_helper->sign_response_ed25519( $response, self::$fasp_keys['private'], 'fasp-id' );

		$headers = $response->get_headers();

		return array(
			'headers'  => array(
				'content-digest'  => $digest,
				'signature-input' => $headers['Signature-Input'],
				'signature'       => $headers['Signature'],
			),
			'body'     => $body,
			'response' => array(
				'code'    => $status,
				'message' => '',
			),
		);
	}

	/**
	 * Invoke an admin action, capturing the redirect that ends it.
	 *
	 * @param callable $action The admin action callback.
	 * @return string|null The redirect location, or null if none happened.
	 *
	 * @throws \Exception If a non-redirect exception is caught during the action.
	 */
	protected function invoke_capturing_redirect( $action ) {
		$location          = null;
		$redirect_callback = function ( $redirect_location ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \Exception( 'REDIRECT:' . $redirect_location );
		};

		\add_filter( 'wp_redirect', $redirect_callback );

		try {
			\call_user_func( $action );
		} catch ( \Exception $e ) {
			if ( 0 !== \strpos( $e->getMessage(), 'REDIRECT:' ) ) {
				throw $e;
			}

			$location = \substr( $e->getMessage(), \strlen( 'REDIRECT:' ) );
		} finally {
			\remove_filter( 'wp_redirect', $redirect_callback );
		}

		return $location;
	}
}
