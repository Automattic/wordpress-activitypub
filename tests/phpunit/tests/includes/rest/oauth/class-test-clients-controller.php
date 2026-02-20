<?php
/**
 * Test file for OAuth Clients REST Controller.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest\OAuth;

use Activitypub\Post_Types;

/**
 * Test class for the OAuth Clients Controller.
 *
 * @coversDefaultClass \Activitypub\Rest\OAuth\Clients_Controller
 *
 * @group activitypub
 * @group oauth
 */
class Test_Clients_Controller extends \WP_UnitTestCase {

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		Post_Types::register_oauth_post_types();

		global $wp_rest_server;
		$wp_rest_server = new \Spy_REST_Server();
		\do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Test that client routes are registered.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = \rest_get_server()->get_routes();
		$base   = '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth';

		$this->assertArrayHasKey( $base . '/clients', $routes );
		$this->assertArrayHasKey( $base . '/authorization-server-metadata', $routes );
	}

	/**
	 * Test successful client registration.
	 *
	 * @covers ::register_client
	 */
	public function test_register_client_success() {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/clients' );
		$request->set_param( 'client_name', 'My Test App' );
		$request->set_param( 'redirect_uris', array( 'https://myapp.example.com/callback' ) );

		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 201, $response->get_status() );
		$this->assertArrayHasKey( 'client_id', $data );
		$this->assertEquals( 'My Test App', $data['client_name'] );
		$this->assertEquals( array( 'https://myapp.example.com/callback' ), $data['redirect_uris'] );
		$this->assertEquals( 'none', $data['token_endpoint_auth_method'] );
	}

	/**
	 * Test client registration without client_name.
	 *
	 * @covers ::register_client
	 */
	public function test_register_client_missing_name() {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/clients' );
		$request->set_param( 'redirect_uris', array( 'https://myapp.example.com/callback' ) );

		$response = \rest_get_server()->dispatch( $request );

		// client_name is required — should fail validation.
		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	/**
	 * Test client registration without redirect_uris.
	 *
	 * @covers ::register_client
	 */
	public function test_register_client_missing_redirect_uris() {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/clients' );
		$request->set_param( 'client_name', 'My Test App' );

		$response = \rest_get_server()->dispatch( $request );

		// redirect_uris is required — should fail validation.
		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	/**
	 * Test client registration when disabled via filter.
	 *
	 * @covers ::register_client
	 */
	public function test_register_client_disabled() {
		\add_filter( 'activitypub_allow_dynamic_client_registration', '__return_false' );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/clients' );
		$request->set_param( 'client_name', 'Blocked App' );
		$request->set_param( 'redirect_uris', array( 'https://blocked.example.com/callback' ) );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );

		\remove_filter( 'activitypub_allow_dynamic_client_registration', '__return_false' );
	}

	/**
	 * Test getting authorization server metadata.
	 *
	 * @covers ::get_metadata
	 */
	public function test_get_metadata() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/authorization-server-metadata' );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		$this->assertArrayHasKey( 'issuer', $data );
		$this->assertArrayHasKey( 'authorization_endpoint', $data );
		$this->assertArrayHasKey( 'token_endpoint', $data );
		$this->assertArrayHasKey( 'revocation_endpoint', $data );
		$this->assertArrayHasKey( 'introspection_endpoint', $data );
		$this->assertArrayHasKey( 'registration_endpoint', $data );
		$this->assertArrayHasKey( 'scopes_supported', $data );
		$this->assertArrayHasKey( 'response_types_supported', $data );
		$this->assertArrayHasKey( 'grant_types_supported', $data );
		$this->assertArrayHasKey( 'code_challenge_methods_supported', $data );

		$this->assertEquals( \home_url(), $data['issuer'] );
		$this->assertContains( 'code', $data['response_types_supported'] );
		$this->assertContains( 'authorization_code', $data['grant_types_supported'] );
		$this->assertContains( 'refresh_token', $data['grant_types_supported'] );
	}
}
