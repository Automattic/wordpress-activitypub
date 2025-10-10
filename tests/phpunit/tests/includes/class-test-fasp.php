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
		$this->assertCount( 1, $route );
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
	 * Test FASP base URL in nodeinfo metadata.
	 *
	 * @covers ::add_fasp_base_url
	 */
	public function test_add_fasp_base_url() {
		$metadata = array( 'existing' => 'data' );
		$result   = Fasp::add_fasp_base_url( $metadata );

		$this->assertArrayHasKey( 'faspBaseUrl', $result );
		$this->assertArrayHasKey( 'existing', $result );
		$this->assertEquals( 'data', $result['existing'] );

		$expected_base_url = rest_url( 'activitypub/1.0/fasp' );
		$this->assertEquals( $expected_base_url, $result['faspBaseUrl'] );
	}

	/**
	 * Test authentication uses proper signature verification.
	 *
	 * @covers ::authenticate_request
	 */
	public function test_authenticate_request() {
		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/fasp/provider_info' );
		$result  = $this->controller->authenticate_request( $request );

		// Should use the same signature verification as other ActivityPub endpoints.
		// For GET requests without authorized fetch, this should return true.
		$this->assertTrue( $result );
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
}
