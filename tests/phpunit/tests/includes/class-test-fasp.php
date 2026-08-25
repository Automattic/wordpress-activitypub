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
class Test_Fasp extends Fasp_TestCase {

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
				'publicKey' => $this->fasp_public_key_base64(),
			),
			$overrides
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/fasp/registration' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( \wp_json_encode( $params ) );

		return $request;
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
				'fasp_public_key' => $this->fasp_public_key_base64(),
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

		Registrations::set_capability_enabled( $fasp_id, 'trends', '1.0', true );
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
		$fasp_id = $this->create_fasp_registration( 'approved' )['fasp_id'];

		// Capability state only exists for a real registration.
		$this->assertFalse( Registrations::set_capability_enabled( 'missing-id', 'trends', '1.0', true ) );
		$this->assertFalse( Registrations::is_capability_enabled( 'missing-id', 'trends', '1.0' ) );

		$this->assertFalse( Registrations::is_capability_enabled( $fasp_id, 'trends', '1.0' ) );

		Registrations::set_capability_enabled( $fasp_id, 'trends', '1.0', true );
		$this->assertTrue( Registrations::is_capability_enabled( $fasp_id, 'trends', '1.0' ) );
		$this->assertFalse( Registrations::is_capability_enabled( $fasp_id, 'trends', '2.0' ), 'Capability state is per version.' );

		Registrations::set_capability_enabled( $fasp_id, 'trends', '1.0', false );
		$this->assertFalse( Registrations::is_capability_enabled( $fasp_id, 'trends', '1.0' ) );
	}

	/**
	 * Test the public key fingerprint.
	 *
	 * @covers \Activitypub\Fasp\Registrations::get_public_key_fingerprint
	 */
	public function test_public_key_fingerprint() {
		$public_key  = $this->fasp_public_key_base64();
		$fingerprint = Registrations::get_public_key_fingerprint( $public_key );

		// The fingerprint is the base64 encoded SHA-256 hash of the raw key.
		$this->assertSame( \base64_encode( \hash( 'sha256', self::$fasp_keys['public'], true ) ), $fingerprint );
	}

	/**
	 * The client fetches and verifies provider info on every call.
	 *
	 * @covers \Activitypub\Fasp\Client::fetch_provider_info
	 */
	public function test_client_fetch_provider_info() {
		$registration  = $this->create_fasp_registration();
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

		$result = Client::fetch_provider_info( $registration );
		$this->assertSame( $provider_info, $result );
		$this->assertSame( 1, $http_calls );

		// Each call fetches fresh; there is no request-level cache to serve a stale copy.
		Client::fetch_provider_info( $registration );
		$this->assertSame( 2, $http_calls );

		\remove_filter( 'pre_http_request', $mock );
	}

	/**
	 * The client rejects unsigned and tampered provider responses.
	 *
	 * @covers \Activitypub\Fasp\Client::fetch_provider_info
	 */
	public function test_client_rejects_invalid_responses() {
		$registration = $this->create_fasp_registration();
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
		$this->assertWPError( Client::fetch_provider_info( $registration ), 'An unsigned response should be rejected.' );
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
		$this->assertWPError( Client::fetch_provider_info( $registration ), 'A tampered response should be rejected.' );
		\remove_filter( 'pre_http_request', $mock_tampered );
	}

	/**
	 * Capability activation calls the FASP and reports the outcome.
	 *
	 * @covers \Activitypub\Fasp\Client::activate_capability
	 * @covers \Activitypub\Fasp\Client::deactivate_capability
	 */
	public function test_client_capability_activation() {
		$registration = $this->create_fasp_registration();

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

	/**
	 * Fill the pending queue to the cap with backdated entries.
	 *
	 * @param string $prefix    A server-id prefix that makes each entry unique.
	 * @param int    $age       How far in the past to backdate `requested_at`, in seconds.
	 * @return string[] The created FASP IDs, oldest first.
	 */
	private function fill_pending_queue( $prefix, $age = 0 ) {
		$ids = array();
		for ( $i = 0; $i < Registrations::MAX_PENDING; $i++ ) {
			$registration = Registrations::create(
				array(
					'name'            => 'Pending FASP ' . $i,
					'base_url'        => 'https://fasp.example.com',
					'server_id'       => $prefix . '-' . $i,
					'fasp_public_key' => $this->fasp_public_key_base64(),
				)
			);
			$ids[]        = $registration['fasp_id'];
		}

		if ( $age ) {
			$date          = \gmdate( 'Y-m-d H:i:s', \time() - $age );
			$registrations = \get_option( Registrations::OPTION_REGISTRATIONS );
			foreach ( $ids as $fasp_id ) {
				$registrations[ $fasp_id ]['requested_at'] = $date;
			}
			\update_option( Registrations::OPTION_REGISTRATIONS, $registrations, false );
		}

		return $ids;
	}

	/**
	 * A full pending queue evicts its oldest entry rather than locking out new registrations.
	 *
	 * @covers ::handle_registration
	 */
	public function test_registration_full_queue_evicts_oldest() {
		$ids = $this->fill_pending_queue( 'pending-server' );

		// Stagger the timestamps so the oldest entry is unambiguous (in production
		// requests arrive seconds apart; the test loop creates them in one second).
		$registrations = \get_option( Registrations::OPTION_REGISTRATIONS );
		foreach ( $ids as $index => $fasp_id ) {
			$registrations[ $fasp_id ]['requested_at'] = \gmdate( 'Y-m-d H:i:s', \time() - ( \count( $ids ) - $index ) * MINUTE_IN_SECONDS );
		}
		\update_option( Registrations::OPTION_REGISTRATIONS, $registrations, false );

		$response = \rest_get_server()->dispatch( $this->build_registration_request() );

		$this->assertEquals( 201, $response->get_status(), 'A full queue must never lock out a legitimate registration.' );
		$this->assertCount( Registrations::MAX_PENDING, Registrations::get_by_status( 'pending' ), 'The queue stays bounded at the cap.' );
		$this->assertNull( Registrations::get( $ids[0] ), 'The oldest pending entry is evicted to make room.' );
		$this->assertNotNull( Registrations::get( $ids[1] ), 'Newer pending entries are retained.' );
	}

	/**
	 * Stale pending and rejected registrations are pruned; approved ones survive.
	 *
	 * @covers \Activitypub\Fasp\Registrations::prune_stale
	 */
	public function test_prune_stale() {
		$stale_pending  = $this->create_fasp_registration( 'pending', array( 'server_id' => 'stale-pending' ) );
		$stale_rejected = $this->create_fasp_registration( 'pending', array( 'server_id' => 'stale-rejected' ) );
		Registrations::reject( $stale_rejected['fasp_id'], 0 );
		$stale_approved = $this->create_fasp_registration( 'approved', array( 'server_id' => 'stale-approved' ) );
		$fresh_pending  = $this->create_fasp_registration( 'pending', array( 'server_id' => 'fresh-pending' ) );

		// Backdate everything except the fresh pending entry past the TTL.
		$stale_date    = \gmdate( 'Y-m-d H:i:s', \time() - Registrations::PENDING_TTL - DAY_IN_SECONDS );
		$registrations = \get_option( Registrations::OPTION_REGISTRATIONS );
		foreach ( array( $stale_pending, $stale_rejected, $stale_approved ) as $registration ) {
			$registrations[ $registration['fasp_id'] ]['requested_at'] = $stale_date;
		}
		\update_option( Registrations::OPTION_REGISTRATIONS, $registrations, false );

		Registrations::prune_stale();

		$this->assertNull( Registrations::get( $stale_pending['fasp_id'] ), 'A stale pending registration is pruned.' );
		$this->assertNull( Registrations::get( $stale_rejected['fasp_id'] ), 'A stale rejected registration is pruned.' );
		$this->assertNotNull( Registrations::get( $stale_approved['fasp_id'] ), 'An approved registration is never pruned.' );
		$this->assertNotNull( Registrations::get( $fresh_pending['fasp_id'] ), 'A fresh pending registration is not pruned.' );
	}

	/**
	 * A pending record with a missing timestamp is pruned rather than occupying a slot forever.
	 *
	 * @covers \Activitypub\Fasp\Registrations::prune_stale
	 */
	public function test_prune_stale_removes_records_without_timestamp() {
		$registration = $this->create_fasp_registration( 'pending' );

		$registrations = \get_option( Registrations::OPTION_REGISTRATIONS );
		$registrations[ $registration['fasp_id'] ]['requested_at'] = '';
		\update_option( Registrations::OPTION_REGISTRATIONS, $registrations, false );

		Registrations::prune_stale();

		$this->assertNull( Registrations::get( $registration['fasp_id'] ), 'A pending record with no timestamp must be pruned.' );
	}

	/**
	 * The registration endpoint prunes stale entries, so they never count toward the cap.
	 *
	 * @covers ::handle_registration
	 */
	public function test_registration_prunes_stale_before_cap() {
		$this->fill_pending_queue( 'stale-server', Registrations::PENDING_TTL + DAY_IN_SECONDS );

		$response = \rest_get_server()->dispatch( $this->build_registration_request() );

		$this->assertEquals( 201, $response->get_status(), 'Stale pending entries are pruned before the cap is measured.' );
		$this->assertCount( 1, Registrations::get_by_status( 'pending' ), 'Only the new registration remains.' );
	}
}
