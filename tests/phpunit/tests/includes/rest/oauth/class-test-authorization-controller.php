<?php
/**
 * Test file for OAuth Authorization REST Controller.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest\OAuth;

use Activitypub\OAuth\Client;
use Activitypub\Post_Types;

/**
 * Test class for the OAuth Authorization Controller.
 *
 * @coversDefaultClass \Activitypub\Rest\OAuth\Authorization_Controller
 */
class Test_Authorization_Controller extends \WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Test client ID.
	 *
	 * @var string
	 */
	protected $client_id;

	/**
	 * Test redirect URI.
	 *
	 * @var string
	 */
	protected $redirect_uri = 'https://example.com/callback';

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		Post_Types::register_oauth_post_types();

		global $wp_rest_server;
		$wp_rest_server = new \Spy_REST_Server();
		\do_action( 'rest_api_init', $wp_rest_server );

		$this->user_id = $this->factory->user->create(
			array(
				'role' => 'editor',
			)
		);

		$client_result   = Client::register(
			array(
				'name'          => 'Test Auth Client',
				'redirect_uris' => array( $this->redirect_uri ),
			)
		);
		$this->client_id = $client_result['client_id'];
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		global $wp_rest_server;
		$wp_rest_server = null;

		if ( $this->client_id ) {
			Client::delete( $this->client_id );
		}

		parent::tear_down();
	}

	/**
	 * Test that authorization routes are registered.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = \rest_get_server()->get_routes();
		$route  = '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/authorize';

		$this->assertArrayHasKey( $route, $routes );

		// Should have GET and POST endpoints.
		$methods = array();
		foreach ( $routes[ $route ] as $endpoint ) {
			$methods = array_merge( $methods, array_keys( $endpoint['methods'] ) );
		}
		$this->assertContains( 'GET', $methods );
		$this->assertContains( 'POST', $methods );
	}

	/**
	 * Test that GET authorize redirects to wp-login.php for valid client.
	 *
	 * @covers ::authorize
	 */
	public function test_authorize_redirects_to_login() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/authorize' );
		$request->set_param( 'response_type', 'code' );
		$request->set_param( 'client_id', $this->client_id );
		$request->set_param( 'redirect_uri', $this->redirect_uri );
		$request->set_param( 'scope', 'read' );
		$request->set_param( 'state', 'test_state_123' );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 302, $response->get_status() );

		$headers  = $response->get_headers();
		$location = $headers['Location'];

		$this->assertStringContainsString( 'wp-login.php', $location );
		$this->assertStringContainsString( 'action=activitypub_authorize', $location );
		$this->assertStringContainsString( 'client_id=' . $this->client_id, $location );
		$this->assertStringContainsString( 'state=test_state_123', $location );
	}

	/**
	 * Test that GET authorize returns error for unknown client.
	 *
	 * @covers ::authorize
	 */
	public function test_authorize_invalid_client() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/authorize' );
		$request->set_param( 'response_type', 'code' );
		$request->set_param( 'client_id', 'nonexistent-client-id' );
		$request->set_param( 'redirect_uri', $this->redirect_uri );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	/**
	 * Test that GET authorize returns error for mismatched redirect URI.
	 *
	 * @covers ::authorize
	 */
	public function test_authorize_invalid_redirect_uri() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/authorize' );
		$request->set_param( 'response_type', 'code' );
		$request->set_param( 'client_id', $this->client_id );
		$request->set_param( 'redirect_uri', 'https://evil.example.com/steal' );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test that POST authorize requires login.
	 *
	 * @covers ::authorize_submit_permissions_check
	 */
	public function test_authorize_submit_requires_login() {
		// Ensure no user is logged in.
		\wp_set_current_user( 0 );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/authorize' );
		$request->set_param( 'response_type', 'code' );
		$request->set_param( 'client_id', $this->client_id );
		$request->set_param( 'redirect_uri', $this->redirect_uri );
		$request->set_param( 'approve', true );
		$request->set_param( '_wpnonce', 'invalid' );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test that POST authorize requires valid nonce.
	 *
	 * @covers ::authorize_submit_permissions_check
	 */
	public function test_authorize_submit_requires_nonce() {
		\wp_set_current_user( $this->user_id );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/authorize' );
		$request->set_param( 'response_type', 'code' );
		$request->set_param( 'client_id', $this->client_id );
		$request->set_param( 'redirect_uri', $this->redirect_uri );
		$request->set_param( 'approve', true );
		$request->set_param( '_wpnonce', 'bad_nonce' );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test that POST authorize with deny redirects with access_denied.
	 *
	 * @covers ::authorize_submit
	 */
	public function test_authorize_submit_denied() {
		\wp_set_current_user( $this->user_id );
		$nonce = \wp_create_nonce( 'activitypub_oauth_authorize' );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/authorize' );
		$request->set_param( 'response_type', 'code' );
		$request->set_param( 'client_id', $this->client_id );
		$request->set_param( 'redirect_uri', $this->redirect_uri );
		$request->set_param( 'approve', false );
		$request->set_param( '_wpnonce', $nonce );
		$request->set_param( 'state', 'deny_state' );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 302, $response->get_status() );

		$headers  = $response->get_headers();
		$location = $headers['Location'];

		$this->assertStringContainsString( 'error=access_denied', $location );
		$this->assertStringContainsString( 'state=deny_state', $location );
	}

	/**
	 * Test that POST authorize with approval redirects with code and state.
	 *
	 * @covers ::authorize_submit
	 */
	public function test_authorize_submit_success() {
		\wp_set_current_user( $this->user_id );
		$nonce = \wp_create_nonce( 'activitypub_oauth_authorize' );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/authorize' );
		$request->set_param( 'response_type', 'code' );
		$request->set_param( 'client_id', $this->client_id );
		$request->set_param( 'redirect_uri', $this->redirect_uri );
		$request->set_param( 'scope', 'read write' );
		$request->set_param( 'approve', true );
		$request->set_param( '_wpnonce', $nonce );
		$request->set_param( 'state', 'success_state' );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 302, $response->get_status() );

		$headers  = $response->get_headers();
		$location = $headers['Location'];

		$this->assertStringContainsString( 'code=', $location );
		$this->assertStringContainsString( 'state=success_state', $location );
		$this->assertStringNotContainsString( 'error', $location );
	}
}
