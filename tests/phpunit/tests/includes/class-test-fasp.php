<?php
/**
 * Test file for FASP classes.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Fasp;
use Activitypub\Rest\Fasp_Controller;

/**
 * Test class for FASP.
 *
 * @coversDefaultClass \Activitypub\Rest\Fasp_Controller
 */
class Test_Fasp extends \WP_UnitTestCase {

	/**
	 * FASP Controller instance.
	 *
	 * @var Fasp_Controller
	 */
	private $controller;

	/**
	 * Set up the test environment.
	 */
	public function set_up() {
		parent::set_up();

		// Initialize REST API.
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );

		$this->controller = new Fasp_Controller();

		// Clean up options.
		delete_option( 'activitypub_fasp_registrations' );
		delete_option( 'activitypub_fasp_capabilities' );
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down() {
		parent::tear_down();

		// Clean up options.
		delete_option( 'activitypub_fasp_registrations' );
		delete_option( 'activitypub_fasp_capabilities' );
	}

	/**
	 * Test provider info endpoint registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		global $wp_rest_server;

		$this->controller->register_routes();

		$routes = $wp_rest_server->get_routes();
		$this->assertArrayHasKey( '/activitypub/1.0/fasp/provider_info', $routes );

		$route = $routes['/activitypub/1.0/fasp/provider_info'];
		$this->assertIsArray( $route );
		$this->assertEquals( 'GET', $route[0]['methods']['GET'] );
	}

	/**
	 * Test provider info endpoint response.
	 *
	 * @covers ::get_provider_info
	 */
	public function test_provider_info() {
		$request  = new \WP_REST_Request( 'GET', '/activitypub/1.0/fasp/provider_info' );
		$response = $this->controller->get_provider_info( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'name', $data );
		$this->assertArrayHasKey( 'privacyPolicy', $data );
		$this->assertArrayHasKey( 'capabilities', $data );

		// Test required fields are present and properly typed.
		$this->assertIsString( $data['name'] );
		$this->assertIsArray( $data['privacyPolicy'] );
		$this->assertIsArray( $data['capabilities'] );

		// Test Content-Digest header is present.
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Content-Digest', $headers );
		$this->assertStringStartsWith( 'sha-256=:', $headers['Content-Digest'] );
	}

	/**
	 * Test provider info with privacy policy.
	 *
	 * @covers ::get_provider_info
	 */
	public function test_provider_info_with_privacy_policy() {
		// Create a privacy policy page.
		$privacy_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'Privacy Policy',
				'post_status' => 'publish',
			)
		);
		update_option( 'wp_page_for_privacy_policy', $privacy_page_id );

		$request  = new \WP_REST_Request( 'GET', '/activitypub/1.0/fasp/provider_info' );
		$response = $this->controller->get_provider_info( $request );

		$data = $response->get_data();

		$this->assertNotEmpty( $data['privacyPolicy'] );
		$this->assertArrayHasKey( 'url', $data['privacyPolicy'][0] );
		$this->assertArrayHasKey( 'language', $data['privacyPolicy'][0] );

		// Clean up.
		wp_delete_post( $privacy_page_id, true );
		delete_option( 'wp_page_for_privacy_policy' );
	}

	/**
	 * Test provider info optional fields.
	 *
	 * @covers ::get_provider_info
	 */
	public function test_provider_info_optional_fields() {
		$request  = new \WP_REST_Request( 'GET', '/activitypub/1.0/fasp/provider_info' );
		$response = $this->controller->get_provider_info( $request );

		$data = $response->get_data();

		// signInUrl should be present (WordPress admin).
		$this->assertArrayHasKey( 'signInUrl', $data );
		$this->assertStringContainsString( 'wp-admin', $data['signInUrl'] );

		// contactEmail should be present (admin email).
		$this->assertArrayHasKey( 'contactEmail', $data );
		$this->assertIsString( $data['contactEmail'] );

		// fediverseAccount should not be present by default.
		$this->assertArrayNotHasKey( 'fediverseAccount', $data );
	}

	/**
	 * Test capabilities filter.
	 *
	 * @covers ::get_provider_info
	 */
	public function test_capabilities_filter() {
		// Add a test capability via filter.
		add_filter(
			'activitypub_fasp_capabilities',
			function ( $capabilities ) {
				$capabilities[] = array(
					'id'      => 'test_capability',
					'version' => '1.0',
				);
				return $capabilities;
			}
		);

		$request  = new \WP_REST_Request( 'GET', '/activitypub/1.0/fasp/provider_info' );
		$response = $this->controller->get_provider_info( $request );

		$data = $response->get_data();

		$this->assertCount( 1, $data['capabilities'] );
		$this->assertEquals( 'test_capability', $data['capabilities'][0]['id'] );
		$this->assertEquals( '1.0', $data['capabilities'][0]['version'] );

		// Clean up.
		remove_all_filters( 'activitypub_fasp_capabilities' );
	}

	/**
	 * Test provider name generation.
	 *
	 * @covers ::get_provider_info
	 */
	public function test_provider_name() {
		// Test with custom site name.
		update_option( 'blogname', 'Test Site' );

		$request  = new \WP_REST_Request( 'GET', '/activitypub/1.0/fasp/provider_info' );
		$response = $this->controller->get_provider_info( $request );

		$data = $response->get_data();
		$this->assertEquals( 'Test Site ActivityPub FASP', $data['name'] );

		// Test with empty site name.
		update_option( 'blogname', '' );

		$response = $this->controller->get_provider_info( $request );
		$data     = $response->get_data();
		$this->assertEquals( 'WordPress ActivityPub FASP', $data['name'] );
	}

	/**
	 * Test registration endpoint registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_registration_route_registered() {
		global $wp_rest_server;

		$this->controller->register_routes();

		$routes = $wp_rest_server->get_routes();

		$this->assertArrayHasKey( '/activitypub/1.0/fasp/registration', $routes );

		$route = $routes['/activitypub/1.0/fasp/registration'];
		$this->assertArrayHasKey( 0, $route );
		$this->assertEquals( 'POST', $route[0]['methods']['POST'] );
	}

	/**
	 * Test registration endpoint response.
	 *
	 * @covers ::handle_registration
	 */
	public function test_registration() {
		$request_data = array(
			'name'      => 'Test FASP Provider',
			'baseUrl'   => 'https://fasp.example.com',
			'serverId'  => 'test-server-123',
			'publicKey' => 'dGVzdC1wdWJsaWMta2V5',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/fasp/registration' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $request_data ) );

		$response = $this->controller->handle_registration( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'faspId', $data );
		$this->assertArrayHasKey( 'publicKey', $data );
		$this->assertArrayHasKey( 'registrationCompletionUri', $data );

		// Verify data was stored.
		$registrations = get_option( 'activitypub_fasp_registrations', array() );
		$this->assertNotEmpty( $registrations );
		$this->assertArrayHasKey( $data['faspId'], $registrations );

		$stored_registration = $registrations[ $data['faspId'] ];
		$this->assertEquals( 'Test FASP Provider', $stored_registration['name'] );
		$this->assertEquals( 'https://fasp.example.com', $stored_registration['base_url'] );
		$this->assertEquals( 'test-server-123', $stored_registration['server_id'] );
		$this->assertEquals( 'pending', $stored_registration['status'] );
		$this->assertArrayHasKey( 'fasp_public_key_fingerprint', $stored_registration );
	}

	/**
	 * Test registration with missing fields returns error via REST API.
	 *
	 * Validation is handled by REST API args with required => true.
	 *
	 * @covers ::handle_registration
	 */
	public function test_registration_missing_fields() {
		$request_data = array(
			'name'    => 'Test FASP Provider',
			'baseUrl' => 'https://fasp.example.com',
			// Missing serverId and publicKey.
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/fasp/registration' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $request_data ) );

		// Dispatch through REST API to trigger validation.
		$response = rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertEquals( 'rest_missing_callback_param', $data['code'] );
	}

	/**
	 * Test FASP registration management methods.
	 *
	 * @covers Activitypub\Fasp::get_pending_registrations
	 * @covers Activitypub\Fasp::approve_registration
	 * @covers Activitypub\Fasp::get_approved_registrations
	 */
	public function test_registration_management() {
		// Create a test registration.
		$registration_data = array(
			'fasp_id'                     => 'test-fasp-123',
			'name'                        => 'Test FASP',
			'base_url'                    => 'https://fasp.example.com',
			'server_id'                   => 'test-server-123',
			'fasp_public_key'             => 'dGVzdC1wdWJsaWMta2V5',
			'fasp_public_key_fingerprint' => Fasp::get_public_key_fingerprint( 'dGVzdC1wdWJsaWMta2V5' ),
			'server_public_key'           => 'c2VydmVyLXB1YmxpYy1rZXk=',
			'status'                      => 'pending',
			'requested_at'                => current_time( 'mysql', true ),
		);

		$registrations = array( 'test-fasp-123' => $registration_data );
		update_option( 'activitypub_fasp_registrations', $registrations );

		// Test getting pending registrations.
		$pending = Fasp::get_pending_registrations();
		$this->assertCount( 1, $pending );
		$this->assertEquals( 'Test FASP', $pending[0]['name'] );
		$this->assertEquals( 'pending', $pending[0]['status'] );

		// Test approving registration.
		$result = Fasp::approve_registration( 'test-fasp-123', 1 );
		$this->assertTrue( $result );

		// Test getting approved registrations.
		$approved = Fasp::get_approved_registrations();
		$this->assertCount( 1, $approved );
		$this->assertEquals( 'Test FASP', $approved[0]['name'] );
		$this->assertEquals( 'approved', $approved[0]['status'] );

		// Test pending registrations is now empty.
		$pending = Fasp::get_pending_registrations();
		$this->assertCount( 0, $pending );
	}

	/**
	 * Test public key fingerprint generation.
	 *
	 * @covers Activitypub\Fasp::get_public_key_fingerprint
	 */
	public function test_public_key_fingerprint() {
		$public_key  = 'dGVzdC1wdWJsaWMta2V5'; // base64 encoded "test-public-key".
		$fingerprint = Fasp::get_public_key_fingerprint( $public_key );

		$this->assertNotEmpty( $fingerprint );
		$this->assertIsString( $fingerprint );

		// Fingerprint should be deterministic.
		$fingerprint2 = Fasp::get_public_key_fingerprint( $public_key );
		$this->assertEquals( $fingerprint, $fingerprint2 );
	}

	/**
	 * Test capability management.
	 *
	 * @covers Activitypub\Fasp::is_capability_enabled
	 */
	public function test_capability_management() {
		// Initially no capabilities should be enabled.
		$enabled = Fasp::is_capability_enabled( 'test-fasp-123', 'trends', 1 );
		$this->assertFalse( $enabled );

		// Enable a capability manually.
		$capabilities = array(
			'test-fasp-123_trends_v1' => array(
				'fasp_id'    => 'test-fasp-123',
				'identifier' => 'trends',
				'version'    => 1,
				'enabled'    => true,
				'updated_at' => current_time( 'mysql', true ),
			),
		);
		update_option( 'activitypub_fasp_capabilities', $capabilities );

		// Now it should be enabled.
		$enabled = Fasp::is_capability_enabled( 'test-fasp-123', 'trends', 1 );
		$this->assertTrue( $enabled );

		// Different capability should not be enabled.
		$enabled = Fasp::is_capability_enabled( 'test-fasp-123', 'search', 1 );
		$this->assertFalse( $enabled );
	}

	/**
	 * Test capability activation with valid serverId.
	 *
	 * Per FASP spec, keyId MUST be the serverId exchanged during registration.
	 *
	 * @covers ::enable_capability
	 */
	public function test_capability_activation_with_valid_server_id() {
		$key_base64        = 'dGVzdC1wdWJsaWMta2V5';
		$registration_data = array(
			'fasp_id'                     => 'test-fasp-123',
			'name'                        => 'Test FASP',
			'base_url'                    => 'https://fasp.example.com',
			'server_id'                   => 'test-server-123',
			'fasp_public_key'             => $key_base64,
			'fasp_public_key_fingerprint' => Fasp::get_public_key_fingerprint( $key_base64 ),
			'server_public_key'           => 'c2VydmVyLXB1YmxpYy1rZXk=',
			'status'                      => 'approved',
			'requested_at'                => current_time( 'mysql', true ),
		);

		update_option( 'activitypub_fasp_registrations', array( 'test-fasp-123' => $registration_data ) );

		add_filter(
			'activitypub_fasp_capabilities',
			function ( $capabilities ) {
				$capabilities[] = array(
					'id'      => 'trends',
					'version' => '1.0',
				);
				return $capabilities;
			}
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/fasp/capabilities/trends/1.0/activation' );
		$request->set_param( 'identifier', 'trends' );
		$request->set_param( 'version', '1.0' );
		// Per FASP spec, keyId must be the serverId exchanged during registration.
		$request->set_header( 'Signature-Input', 'sig=("@method" "@target-uri");keyid="test-server-123"' );
		$request->set_header( 'Signature', 'sig=:dummy:' );

		$response = $this->controller->enable_capability( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( 204, $response->get_status() );

		$stored_capabilities = get_option( 'activitypub_fasp_capabilities', array() );
		$this->assertArrayHasKey( 'test-fasp-123_trends_v1.0', $stored_capabilities );

		remove_all_filters( 'activitypub_fasp_capabilities' );
	}

	/**
	 * Test capability activation rejects requests from unknown FASPs.
	 *
	 * When a request comes with a serverId that doesn't match any registered FASP,
	 * it should be rejected.
	 *
	 * @covers ::enable_capability
	 */
	public function test_capability_activation_rejects_unknown_fasp() {
		$key_base64        = 'dGVzdC1wdWJsaWMta2V5';
		$registration_data = array(
			'fasp_id'                     => 'test-fasp-123',
			'name'                        => 'Test FASP',
			'base_url'                    => 'https://fasp.example.com',
			'server_id'                   => 'test-server-123',
			'fasp_public_key'             => $key_base64,
			'fasp_public_key_fingerprint' => Fasp::get_public_key_fingerprint( $key_base64 ),
			'server_public_key'           => 'c2VydmVyLXB1YmxpYy1rZXk=',
			'status'                      => 'approved',
			'requested_at'                => current_time( 'mysql', true ),
		);

		update_option( 'activitypub_fasp_registrations', array( 'test-fasp-123' => $registration_data ) );

		add_filter(
			'activitypub_fasp_capabilities',
			function ( $capabilities ) {
				$capabilities[] = array(
					'id'      => 'trends',
					'version' => '1.0',
				);
				return $capabilities;
			}
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/fasp/capabilities/trends/1.0/activation' );
		$request->set_param( 'identifier', 'trends' );
		$request->set_param( 'version', '1.0' );
		// Use a serverId from an unknown/unregistered FASP.
		$request->set_header( 'Signature-Input', 'sig=("@method" "@target-uri");keyid="unknown-server-456"' );
		$request->set_header( 'Signature', 'sig=:dummy:' );

		$response = $this->controller->enable_capability( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'fasp_not_found', $response->get_error_code() );

		remove_all_filters( 'activitypub_fasp_capabilities' );
	}

	/**
	 * Test get_registration_by_server_id returns correct registration.
	 *
	 * @covers Activitypub\Fasp::get_registration_by_server_id
	 */
	public function test_get_registration_by_server_id() {
		$registration_data = array(
			'fasp_id'         => 'test-fasp-456',
			'name'            => 'Test FASP by Server ID',
			'base_url'        => 'https://fasp.example.com',
			'server_id'       => 'unique-server-id-789',
			'fasp_public_key' => 'dGVzdC1wdWJsaWMta2V5',
			'status'          => 'approved',
			'requested_at'    => current_time( 'mysql', true ),
		);

		update_option( 'activitypub_fasp_registrations', array( 'test-fasp-456' => $registration_data ) );

		// Test finding by server_id.
		$found = Fasp::get_registration_by_server_id( 'unique-server-id-789' );
		$this->assertNotNull( $found );
		$this->assertEquals( 'test-fasp-456', $found['fasp_id'] );
		$this->assertEquals( 'Test FASP by Server ID', $found['name'] );

		// Test not finding unknown server_id.
		$not_found = Fasp::get_registration_by_server_id( 'unknown-server-id' );
		$this->assertNull( $not_found );
	}

	/**
	 * Test public key filter returns Ed25519 key for approved FASP.
	 *
	 * @covers Activitypub\Fasp::get_public_key_for_server_id
	 */
	public function test_public_key_filter_returns_ed25519_key() {
		// Generate a valid Ed25519 keypair for testing.
		$keypair    = sodium_crypto_sign_keypair();
		$public_key = sodium_crypto_sign_publickey( $keypair );
		$key_base64 = base64_encode( $public_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$registration_data = array(
			'fasp_id'         => 'ed25519-fasp',
			'name'            => 'Ed25519 Test FASP',
			'base_url'        => 'https://fasp.example.com',
			'server_id'       => 'ed25519-server-id',
			'fasp_public_key' => $key_base64,
			'status'          => 'approved',
			'requested_at'    => current_time( 'mysql', true ),
		);

		update_option( 'activitypub_fasp_registrations', array( 'ed25519-fasp' => $registration_data ) );

		// Ensure filter is registered.
		Fasp::init();

		// Call the filter directly.
		$result = Fasp::get_public_key_for_server_id( null, 'ed25519-server-id' );

		$this->assertIsArray( $result );
		$this->assertEquals( 'ed25519', $result['type'] );
		$this->assertEquals( $public_key, $result['key'] );
	}

	/**
	 * Test public key filter returns error for unapproved FASP.
	 *
	 * @covers Activitypub\Fasp::get_public_key_for_server_id
	 */
	public function test_public_key_filter_rejects_unapproved_fasp() {
		$registration_data = array(
			'fasp_id'         => 'pending-fasp',
			'name'            => 'Pending FASP',
			'base_url'        => 'https://fasp.example.com',
			'server_id'       => 'pending-server-id',
			'fasp_public_key' => 'dGVzdC1wdWJsaWMta2V5',
			'status'          => 'pending', // Not approved.
			'requested_at'    => current_time( 'mysql', true ),
		);

		update_option( 'activitypub_fasp_registrations', array( 'pending-fasp' => $registration_data ) );

		$result = Fasp::get_public_key_for_server_id( null, 'pending-server-id' );

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertEquals( 'fasp_not_approved', $result->get_error_code() );
	}

	/**
	 * Test public key filter returns null for non-FASP keyIds.
	 *
	 * @covers Activitypub\Fasp::get_public_key_for_server_id
	 */
	public function test_public_key_filter_passes_through_non_fasp_keyids() {
		// No FASP registrations.
		delete_option( 'activitypub_fasp_registrations' );

		// Should return null for unknown keyIds, allowing default lookup.
		$result = Fasp::get_public_key_for_server_id( null, 'https://example.com/users/test#main-key' );
		$this->assertNull( $result );
	}

	/**
	 * Test public key filter doesn't override existing key.
	 *
	 * @covers Activitypub\Fasp::get_public_key_for_server_id
	 */
	public function test_public_key_filter_respects_existing_key() {
		$existing_key = 'existing-key-from-another-filter';

		$result = Fasp::get_public_key_for_server_id( $existing_key, 'any-server-id' );

		$this->assertEquals( $existing_key, $result );
	}
}
