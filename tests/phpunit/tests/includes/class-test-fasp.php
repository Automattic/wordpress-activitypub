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
	}

	/**
	 * Test registration with missing fields.
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

		$response = $this->controller->handle_registration( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'missing_field', $response->get_error_code() );
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
			'fasp_id'            => 'test-fasp-123',
			'name'               => 'Test FASP',
			'base_url'           => 'https://fasp.example.com',
			'server_id'          => 'test-server-123',
			'fasp_public_key'    => 'dGVzdC1wdWJsaWMta2V5',
			'server_public_key'  => 'c2VydmVyLXB1YmxpYy1rZXk=',
			'server_private_key' => 'c2VydmVyLXByaXZhdGUta2V5',
			'status'             => 'pending',
			'requested_at'       => current_time( 'mysql', true ),
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
}
