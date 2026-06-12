<?php
/**
 * Test file for FASP support.
 *
 * @package Activitypub
 */

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions -- base64 is the FASP wire format, not obfuscation.

namespace Activitypub\Tests;

use Activitypub\Fasp\Client;
use Activitypub\Fasp\Registrations;
use Activitypub\Signature\Http_Message_Signature;

/**
 * Test class for FASP support.
 *
 * @group fasp
 *
 * @coversDefaultClass \Activitypub\Rest\Fasp_Controller
 */
class Test_Fasp extends \WP_UnitTestCase {

	/**
	 * The FASP-side Ed25519 keypair used in tests.
	 *
	 * @var array
	 */
	private static $fasp_keys;

	/**
	 * Set up test resources.
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
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		\update_option( 'activitypub_enable_fasp', '1' );

		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_enable_fasp' );
		\delete_option( Registrations::OPTION_REGISTRATIONS );
		\delete_option( Registrations::OPTION_CAPABILITIES );

		global $wp_rest_server;
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		parent::tear_down();
	}

	/**
	 * Build a valid registration request.
	 *
	 * @param array $overrides Parameter overrides.
	 * @return \WP_REST_Request The request.
	 */
	private function build_registration_request( $overrides = array() ) {
		$params = \array_merge(
			array(
				'name'      => 'Test FASP',
				'baseUrl'   => 'https://fasp.example.com',
				'serverId'  => 'test-server-id',
				'publicKey' => \base64_encode( self::$fasp_keys['public'] ),
			),
			$overrides
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/fasp/registration' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( \wp_json_encode( $params ) );

		return $request;
	}

	/**
	 * Create an approved registration directly in the store.
	 *
	 * @param array $overrides Field overrides.
	 * @return array The registration record.
	 */
	private function create_approved_registration( $overrides = array() ) {
		$registration = Registrations::create(
			\array_merge(
				array(
					'name'            => 'Test FASP',
					'base_url'        => 'https://fasp.example.com',
					'server_id'       => 'test-server-id',
					'fasp_public_key' => \base64_encode( self::$fasp_keys['public'] ),
				),
				$overrides
			)
		);

		Registrations::approve( $registration['fasp_id'], 0 );

		return Registrations::get( $registration['fasp_id'] );
	}

	/**
	 * Build a signed FASP response for `pre_http_request` mocks.
	 *
	 * @param int    $status The response status.
	 * @param string $body   The response body.
	 * @return array The HTTP response array.
	 */
	private function build_signed_fasp_response( $status, $body ) {
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
	 * Test that only the registration route is registered.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = \rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/fasp/registration', $routes );
		$this->assertArrayNotHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/fasp/provider_info', $routes, 'provider_info lives on the FASP, not on this server.' );
	}

	/**
	 * Test a successful registration.
	 *
	 * @covers ::handle_registration
	 */
	public function test_registration() {
		$response = \rest_get_server()->dispatch( $this->build_registration_request() );

		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'faspId', $data );
		$this->assertArrayHasKey( 'publicKey', $data );
		$this->assertArrayHasKey( 'registrationCompletionUri', $data );

		// The returned public key is a valid Ed25519 key.
		$public_key = \base64_decode( $data['publicKey'] );
		$this->assertEquals( SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, \strlen( $public_key ) );

		// The registration is stored as pending with a per-registration keypair.
		$registration = Registrations::get( $data['faspId'] );
		$this->assertSame( 'pending', $registration['status'] );
		$this->assertSame( 'test-server-id', $registration['server_id'] );
		$this->assertSame( $data['publicKey'], $registration['server_public_key'] );
		$this->assertEquals( SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, \strlen( \base64_decode( $registration['server_private_key'] ) ) );
	}

	/**
	 * The registration response is signed over @status and content-digest.
	 *
	 * @covers ::handle_registration
	 */
	public function test_registration_response_is_signed() {
		$response = \rest_get_server()->dispatch( $this->build_registration_request() );
		$headers  = $response->get_headers();

		$this->assertArrayHasKey( 'Content-Digest', $headers );
		$this->assertArrayHasKey( 'Signature-Input', $headers );
		$this->assertArrayHasKey( 'Signature', $headers );

		// The signature verifies against the public key returned in the body, under the serverId.
		$signature_helper = new Http_Message_Signature();
		$verified         = $signature_helper->verify_response(
			201,
			array(
				'Content-Digest'  => $headers['Content-Digest'],
				'Signature-Input' => $headers['Signature-Input'],
				'Signature'       => $headers['Signature'],
			),
			\wp_json_encode( $response->get_data() ),
			\base64_decode( $response->get_data()['publicKey'] )
		);

		$this->assertSame( 'test-server-id', $verified );
	}

	/**
	 * Registration rejects missing fields, invalid keys, and plain-HTTP base URLs.
	 *
	 * @covers ::handle_registration
	 * @covers ::validate_https_url
	 *
	 * @dataProvider invalid_registration_provider
	 *
	 * @param array $overrides Parameter overrides.
	 */
	public function test_registration_validation( $overrides ) {
		$response = \rest_get_server()->dispatch( $this->build_registration_request( $overrides ) );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Data provider for invalid registrations.
	 *
	 * @return array[]
	 */
	public function invalid_registration_provider() {
		return array(
			'missing name'      => array( array( 'name' => null ) ),
			'plain-http URL'    => array( array( 'baseUrl' => 'http://fasp.example.com' ) ),
			'invalid base64'    => array( array( 'publicKey' => '!!not-base64!!' ) ),
			'wrong key length'  => array( array( 'publicKey' => \base64_encode( 'too-short' ) ) ),
			'missing publicKey' => array( array( 'publicKey' => null ) ),
		);
	}

	/**
	 * Duplicate serverIds are rejected with 409.
	 *
	 * @covers ::handle_registration
	 */
	public function test_registration_duplicate_server_id() {
		$response = \rest_get_server()->dispatch( $this->build_registration_request() );
		$this->assertEquals( 201, $response->get_status() );

		$response = \rest_get_server()->dispatch( $this->build_registration_request() );
		$this->assertEquals( 409, $response->get_status() );
	}

	/**
	 * Registrations are rate limited per IP.
	 *
	 * @covers ::handle_registration
	 */
	public function test_registration_rate_limit() {
		$ip = \Activitypub\get_client_ip();
		\set_transient( 'ap_fasp_reg_' . \md5( $ip ), 10, MINUTE_IN_SECONDS );

		$response = \rest_get_server()->dispatch( $this->build_registration_request() );

		$this->assertEquals( 429, $response->get_status() );

		\delete_transient( 'ap_fasp_reg_' . \md5( $ip ) );
	}

	/**
	 * Test the registration lifecycle: pending, approved, rejected, deleted.
	 *
	 * @covers \Activitypub\Fasp\Registrations
	 */
	public function test_registration_lifecycle() {
		$registration = Registrations::create(
			array(
				'name'            => 'Lifecycle FASP',
				'base_url'        => 'https://fasp.example.com',
				'server_id'       => 'lifecycle-server-id',
				'fasp_public_key' => \base64_encode( self::$fasp_keys['public'] ),
			)
		);

		$fasp_id = $registration['fasp_id'];
		$this->assertNotEmpty( $fasp_id );
		$this->assertSame( $registration, Registrations::get( $fasp_id ) );
		$this->assertSame( $fasp_id, Registrations::get_by_server_id( 'lifecycle-server-id' )['fasp_id'] );
		$this->assertCount( 1, Registrations::get_by_status( 'pending' ) );

		$this->assertTrue( Registrations::approve( $fasp_id, 42 ) );
		$approved = Registrations::get( $fasp_id );
		$this->assertSame( 'approved', $approved['status'] );
		$this->assertSame( 42, $approved['approved_by'] );
		$this->assertCount( 0, Registrations::get_by_status( 'pending' ) );
		$this->assertCount( 1, Registrations::get_by_status( 'approved' ) );

		$this->assertTrue( Registrations::reject( $fasp_id, 42 ) );
		$this->assertSame( 'rejected', Registrations::get( $fasp_id )['status'] );

		Registrations::enable_capability( $fasp_id, 'trends', '1.0' );
		$this->assertTrue( Registrations::delete( $fasp_id ) );
		$this->assertNull( Registrations::get( $fasp_id ) );
		$this->assertFalse( Registrations::is_capability_enabled( $fasp_id, 'trends', '1.0' ), 'Deleting a registration removes its capability state.' );

		$this->assertFalse( Registrations::approve( 'missing-id', 1 ) );
		$this->assertFalse( Registrations::delete( 'missing-id' ) );
	}

	/**
	 * Test capability state management.
	 *
	 * @covers \Activitypub\Fasp\Registrations
	 */
	public function test_capability_state() {
		$this->assertFalse( Registrations::is_capability_enabled( 'some-fasp', 'trends', '1.0' ) );

		Registrations::enable_capability( 'some-fasp', 'trends', '1.0' );
		$this->assertTrue( Registrations::is_capability_enabled( 'some-fasp', 'trends', '1.0' ) );
		$this->assertFalse( Registrations::is_capability_enabled( 'some-fasp', 'trends', '2.0' ), 'Capability state is per version.' );

		$enabled = Registrations::get_enabled_capabilities( 'some-fasp' );
		$this->assertCount( 1, $enabled );
		$this->assertSame( 'trends', $enabled[0]['identifier'] );

		Registrations::disable_capability( 'some-fasp', 'trends', '1.0' );
		$this->assertFalse( Registrations::is_capability_enabled( 'some-fasp', 'trends', '1.0' ) );
		$this->assertCount( 0, Registrations::get_enabled_capabilities( 'some-fasp' ) );
	}

	/**
	 * Test the public key fingerprint.
	 *
	 * @covers \Activitypub\Fasp\Registrations::get_public_key_fingerprint
	 */
	public function test_public_key_fingerprint() {
		$public_key  = \base64_encode( self::$fasp_keys['public'] );
		$fingerprint = Registrations::get_public_key_fingerprint( $public_key );

		// The fingerprint is the base64 encoded SHA-256 hash of the raw key.
		$this->assertSame( \base64_encode( \hash( 'sha256', self::$fasp_keys['public'], true ) ), $fingerprint );
	}

	/**
	 * The client fetches, verifies and caches provider info.
	 *
	 * @covers \Activitypub\Fasp\Client::get_provider_info
	 */
	public function test_client_get_provider_info() {
		$registration  = $this->create_approved_registration();
		$provider_info = array(
			'name'          => 'Test FASP',
			'privacyPolicy' => array(),
			'capabilities'  => array(
				array(
					'id'      => 'trends',
					'version' => '1.0',
				),
			),
		);

		$http_calls = 0;
		$mock       = function ( $response, $args, $url ) use ( &$http_calls, $provider_info ) {
			if ( 'https://fasp.example.com/provider_info' !== $url ) {
				return $response;
			}

			++$http_calls;

			$this->assertStringContainsString( 'keyid="test-server-id"', $args['headers']['Signature-Input'], 'Outbound requests sign with the serverId.' );
			$this->assertArrayHasKey( 'Content-Digest', $args['headers'] );

			return $this->build_signed_fasp_response( 200, \wp_json_encode( $provider_info ) );
		};
		\add_filter( 'pre_http_request', $mock, 10, 3 );

		$result = Client::get_provider_info( $registration );
		$this->assertSame( $provider_info, $result );
		$this->assertSame( 1, $http_calls );

		// A second call is served from the cache.
		Client::get_provider_info( $registration );
		$this->assertSame( 1, $http_calls );

		// A forced refresh bypasses the cache.
		Client::get_provider_info( $registration, true );
		$this->assertSame( 2, $http_calls );

		\remove_filter( 'pre_http_request', $mock );
		\delete_transient( Client::PROVIDER_INFO_TRANSIENT . $registration['fasp_id'] );
	}

	/**
	 * The client rejects unsigned and tampered provider responses.
	 *
	 * @covers \Activitypub\Fasp\Client::get_provider_info
	 */
	public function test_client_rejects_invalid_responses() {
		$registration = $this->create_approved_registration();
		$body         = \wp_json_encode( array( 'capabilities' => array( array( 'id' => 'trends', 'version' => '1.0' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

		// Unsigned response.
		$mock_unsigned = function ( $response, $args, $url ) use ( $body ) {
			if ( 'https://fasp.example.com/provider_info' !== $url ) {
				return $response;
			}

			return array(
				'headers'  => array(),
				'body'     => $body,
				'response' => array(
					'code'    => 200,
					'message' => '',
				),
			);
		};
		\add_filter( 'pre_http_request', $mock_unsigned, 10, 3 );
		$this->assertWPError( Client::get_provider_info( $registration, true ), 'An unsigned response should be rejected.' );
		\remove_filter( 'pre_http_request', $mock_unsigned );

		// Signed response with tampered body.
		$mock_tampered = function ( $response, $args, $url ) use ( $body ) {
			if ( 'https://fasp.example.com/provider_info' !== $url ) {
				return $response;
			}

			$signed         = $this->build_signed_fasp_response( 200, $body );
			$signed['body'] = \wp_json_encode( array( 'capabilities' => array( array( 'id' => 'evil', 'version' => '666' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

			return $signed;
		};
		\add_filter( 'pre_http_request', $mock_tampered, 10, 3 );
		$this->assertWPError( Client::get_provider_info( $registration, true ), 'A tampered response should be rejected.' );
		\remove_filter( 'pre_http_request', $mock_tampered );
	}

	/**
	 * Capability activation calls the FASP and reports the outcome.
	 *
	 * @covers \Activitypub\Fasp\Client::activate_capability
	 * @covers \Activitypub\Fasp\Client::deactivate_capability
	 */
	public function test_client_capability_activation() {
		$registration = $this->create_approved_registration();

		$requests = array();
		$mock     = function ( $response, $args, $url ) use ( &$requests ) {
			if ( 'https://fasp.example.com/capabilities/trends/1.0/activation' !== $url ) {
				return $response;
			}

			$requests[] = $args['method'];

			return $this->build_signed_fasp_response( 204, '' );
		};
		\add_filter( 'pre_http_request', $mock, 10, 3 );

		$this->assertTrue( Client::activate_capability( $registration, 'trends', '1.0' ) );
		$this->assertTrue( Client::deactivate_capability( $registration, 'trends', '1.0' ) );
		$this->assertSame( array( 'POST', 'DELETE' ), $requests );

		\remove_filter( 'pre_http_request', $mock );

		// A FASP that does not know the capability responds with 404.
		$mock_404 = function ( $response, $args, $url ) {
			if ( 'https://fasp.example.com/capabilities/unknown/1.0/activation' !== $url ) {
				return $response;
			}

			return $this->build_signed_fasp_response( 404, '' );
		};
		\add_filter( 'pre_http_request', $mock_404, 10, 3 );

		$this->assertWPError( Client::activate_capability( $registration, 'unknown', '1.0' ) );

		\remove_filter( 'pre_http_request', $mock_404 );
	}
}
