<?php
/**
 * Test file for OAuth Token REST Controller.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest\OAuth;

use Activitypub\OAuth\Authorization_Code;
use Activitypub\OAuth\Client;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Token;
use Activitypub\Post_Types;

/**
 * Test class for the OAuth Token Controller.
 *
 * @coversDefaultClass \Activitypub\Rest\OAuth\Token_Controller
 *
 * @group activitypub
 * @group oauth
 */
class Test_Token_Controller extends \WP_UnitTestCase {

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
				'name'          => 'Test Token Client',
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
	 * Helper: create an authorization code for the test user/client.
	 *
	 * @param array  $scopes Scopes for the code.
	 * @param string $code_challenge Optional PKCE code challenge.
	 * @param string $code_challenge_method Optional PKCE method.
	 * @return string The authorization code.
	 */
	protected function create_auth_code( $scopes = null, $code_challenge = '', $code_challenge_method = 'S256' ) {
		if ( null === $scopes ) {
			$scopes = array( Scope::READ, Scope::WRITE );
		}

		return Authorization_Code::create(
			$this->user_id,
			$this->client_id,
			$this->redirect_uri,
			$scopes,
			$code_challenge,
			$code_challenge_method
		);
	}

	/**
	 * Test that token routes are registered.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = \rest_get_server()->get_routes();
		$base   = '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth';

		$this->assertArrayHasKey( $base . '/token', $routes );
		$this->assertArrayHasKey( $base . '/revoke', $routes );
		$this->assertArrayHasKey( $base . '/introspect', $routes );
	}

	/**
	 * Test token request with unknown client.
	 *
	 * @covers ::token
	 */
	public function test_token_invalid_client() {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/token' );
		$request->set_param( 'grant_type', 'authorization_code' );
		$request->set_param( 'client_id', 'nonexistent-client-id' );
		$request->set_param( 'code', 'some_code' );
		$request->set_param( 'redirect_uri', $this->redirect_uri );

		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'invalid_client', $data['error'] );
	}

	/**
	 * Test token request with unsupported grant type.
	 *
	 * @covers ::token
	 */
	public function test_token_unsupported_grant_type() {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/token' );
		$request->set_param( 'grant_type', 'password' );
		$request->set_param( 'client_id', $this->client_id );

		$response = \rest_get_server()->dispatch( $request );

		// The enum validation on grant_type will reject 'password' before reaching the controller.
		$this->assertGreaterThanOrEqual( 400, $response->get_status() );
	}

	/**
	 * Test authorization_code grant without code parameter.
	 *
	 * @covers ::token
	 */
	public function test_token_authorization_code_missing_code() {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/token' );
		$request->set_param( 'grant_type', 'authorization_code' );
		$request->set_param( 'client_id', $this->client_id );
		$request->set_param( 'redirect_uri', $this->redirect_uri );

		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'invalid_request', $data['error'] );
	}

	/**
	 * Test successful authorization code exchange for token.
	 *
	 * @covers ::token
	 */
	public function test_token_authorization_code_success() {
		$code = $this->create_auth_code();
		$this->assertIsString( $code );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/token' );
		$request->set_param( 'grant_type', 'authorization_code' );
		$request->set_param( 'client_id', $this->client_id );
		$request->set_param( 'code', $code );
		$request->set_param( 'redirect_uri', $this->redirect_uri );

		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'access_token', $data );
		$this->assertArrayHasKey( 'token_type', $data );
		$this->assertArrayHasKey( 'expires_in', $data );
		$this->assertArrayHasKey( 'refresh_token', $data );
		$this->assertEquals( 'Bearer', $data['token_type'] );
	}

	/**
	 * Test refresh token grant success.
	 *
	 * @covers ::token
	 */
	public function test_token_refresh_success() {
		// First obtain tokens via authorization code.
		$code = $this->create_auth_code();

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/token' );
		$request->set_param( 'grant_type', 'authorization_code' );
		$request->set_param( 'client_id', $this->client_id );
		$request->set_param( 'code', $code );
		$request->set_param( 'redirect_uri', $this->redirect_uri );

		$response      = \rest_get_server()->dispatch( $request );
		$token_data    = $response->get_data();
		$refresh_token = $token_data['refresh_token'];

		// Now use the refresh token.
		$refresh_request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/token' );
		$refresh_request->set_param( 'grant_type', 'refresh_token' );
		$refresh_request->set_param( 'client_id', $this->client_id );
		$refresh_request->set_param( 'refresh_token', $refresh_token );

		$refresh_response = \rest_get_server()->dispatch( $refresh_request );
		$refresh_data     = $refresh_response->get_data();

		$this->assertEquals( 200, $refresh_response->get_status() );
		$this->assertArrayHasKey( 'access_token', $refresh_data );
		$this->assertArrayHasKey( 'refresh_token', $refresh_data );
		$this->assertNotEquals( $token_data['access_token'], $refresh_data['access_token'] );
	}

	/**
	 * Test refresh token grant without refresh_token parameter.
	 *
	 * @covers ::token
	 */
	public function test_token_refresh_missing_token() {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/token' );
		$request->set_param( 'grant_type', 'refresh_token' );
		$request->set_param( 'client_id', $this->client_id );

		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'invalid_request', $data['error'] );
	}

	/**
	 * Test revoke endpoint requires authentication.
	 *
	 * @covers ::revoke_permissions_check
	 */
	public function test_revoke_requires_auth() {
		\wp_set_current_user( 0 );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/revoke' );
		$request->set_param( 'token', 'some_token' );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test successful token revocation.
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_success() {
		\wp_set_current_user( $this->user_id );

		// Create a token to revoke.
		$token_data = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/revoke' );
		$request->set_param( 'token', $token_data['access_token'] );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		// Token should no longer be valid.
		$validation = Token::validate( $token_data['access_token'] );
		$this->assertInstanceOf( \WP_Error::class, $validation );
	}

	/**
	 * Test introspect endpoint requires authentication.
	 *
	 * @covers ::introspect_permissions_check
	 */
	public function test_introspect_requires_auth() {
		\wp_set_current_user( 0 );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/introspect' );
		$request->set_param( 'token', 'some_token' );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test introspecting an active token.
	 *
	 * @covers ::introspect
	 */
	public function test_introspect_active_token() {
		\wp_set_current_user( $this->user_id );

		$token_data = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/introspect' );
		$request->set_param( 'token', $token_data['access_token'] );

		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['active'] );
		$this->assertEquals( $this->client_id, $data['client_id'] );
		$this->assertEquals( 'Bearer', $data['token_type'] );
	}

	/**
	 * Test introspecting a revoked token.
	 *
	 * @covers ::introspect
	 */
	public function test_introspect_revoked_token() {
		\wp_set_current_user( $this->user_id );

		$token_data = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );
		Token::revoke( $token_data['access_token'] );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/introspect' );
		$request->set_param( 'token', $token_data['access_token'] );

		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertFalse( $data['active'] );
	}
}
