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
	use \Activitypub\Tests\OAuth_Token_Stub;


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
	 * PKCE verifier generated alongside the authorization code.
	 *
	 * @var string
	 */
	protected $code_verifier = '';

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		\update_option( 'activitypub_api', '1' );

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

		// Reset the OAuth current_token static so Bearer-auth tests do not leak state.
		$this->set_oauth_current_token( null );

		if ( $this->client_id ) {
			Client::delete( $this->client_id );
		}

		parent::tear_down();
	}

	/**
	 * Helper: create an authorization code for the test user/client.
	 *
	 * Generates a PKCE verifier + challenge by default so the helper works
	 * with the PKCE-required default. The verifier is stored on the test
	 * case as `$this->code_verifier` so callers can pass it when exchanging.
	 *
	 * @param array       $scopes                Scopes for the code.
	 * @param string|null $code_challenge        Optional explicit PKCE code challenge. Null auto-generates.
	 * @param string      $code_challenge_method Optional PKCE method.
	 * @return string The authorization code.
	 */
	protected function create_auth_code( $scopes = null, $code_challenge = null, $code_challenge_method = 'S256' ) {
		if ( null === $scopes ) {
			$scopes = array( Scope::READ, Scope::WRITE );
		}

		if ( null === $code_challenge ) {
			$this->code_verifier = \bin2hex( \random_bytes( 32 ) );
			$code_challenge      = Authorization_Code::compute_code_challenge( $this->code_verifier );
		} else {
			// Caller supplied an explicit challenge; they are responsible for the verifier.
			$this->code_verifier = '';
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
	 * $_SERVER keys get_client_ip() walks. Tests that exercise specific
	 * rate-limit branches need to control all of them so a stray header
	 * from another test or the runner can't change which branch fires.
	 *
	 * @var string[]
	 */
	private static $client_ip_server_keys = array(
		'REMOTE_ADDR',
		'HTTP_CF_CONNECTING_IP',
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
	);

	/**
	 * Capture every $_SERVER key get_client_ip() touches so each test in
	 * this group can restore them in its own try/finally.
	 *
	 * @return array<string, mixed>
	 */
	private function snapshot_client_ip_server() {
		$snapshot = array();
		foreach ( self::$client_ip_server_keys as $key ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Capturing existing test fixture values for restore.
			$snapshot[ $key ] = \array_key_exists( $key, $_SERVER ) ? $_SERVER[ $key ] : null;
		}
		return $snapshot;
	}

	/**
	 * Restore $_SERVER values captured by snapshot_client_ip_server().
	 *
	 * @param array<string, mixed> $snapshot Snapshot returned by snapshot_client_ip_server().
	 */
	private function restore_client_ip_server( $snapshot ) {
		foreach ( $snapshot as $key => $value ) {
			if ( null === $value ) {
				unset( $_SERVER[ $key ] );
			} else {
				$_SERVER[ $key ] = $value;
			}
		}
	}

	/**
	 * Test that the token endpoint returns 429 when the per-IP rate limit
	 * is hit, rather than the previous 400 / `invalid_request` shape.
	 *
	 * @covers ::token
	 */
	public function test_token_rate_limited_returns_429() {
		// Snapshot every IP-bearing header so a stray HTTP_X_FORWARDED_FOR from
		// the runner or another test can't change which branch fires.
		$snapshot = $this->snapshot_client_ip_server();
		foreach ( self::$client_ip_server_keys as $key ) {
			unset( $_SERVER[ $key ] );
		}
		$_SERVER['REMOTE_ADDR'] = '198.51.100.42';

		$ip            = \Activitypub\get_client_ip();
		$transient_key = 'ap_oauth_tok_' . \md5( $ip );

		\set_transient( $transient_key, 20, MINUTE_IN_SECONDS );

		try {
			$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/token' );
			$request->set_param( 'grant_type', 'authorization_code' );
			$request->set_param( 'client_id', $this->client_id );
			$request->set_param( 'code', 'irrelevant' );
			$request->set_param( 'redirect_uri', $this->redirect_uri );

			$response = \rest_get_server()->dispatch( $request );
			$data     = $response->get_data();
			$headers  = $response->get_headers();

			$this->assertEquals( 429, $response->get_status() );
			$this->assertEquals( 'rate_limited', $data['error'] );
			$this->assertSame( 'no-store', $headers['Cache-Control'] ?? null, 'Token error responses must set Cache-Control: no-store per RFC 6749 §5.1.' );
			$this->assertSame( 'no-cache', $headers['Pragma'] ?? null, 'Token error responses must set Pragma: no-cache per RFC 6749 §5.1.' );
			$this->assertSame( (string) MINUTE_IN_SECONDS, $headers['Retry-After'] ?? null, 'Rate-limit responses must include Retry-After per RFC 6585 §4.' );
		} finally {
			\delete_transient( $transient_key );
			$this->restore_client_ip_server( $snapshot );
		}
	}

	/**
	 * Test that the token endpoint fails closed with 429 when no client IP
	 * can be determined — the empty-IP branch in token() must behave the
	 * same as the >=20 cap branch.
	 *
	 * @covers ::token
	 */
	public function test_token_rate_limited_returns_429_without_client_ip() {
		$snapshot = $this->snapshot_client_ip_server();
		foreach ( self::$client_ip_server_keys as $key ) {
			unset( $_SERVER[ $key ] );
		}

		$empty_ip_transient = 'ap_oauth_tok_' . \md5( '' );
		\delete_transient( $empty_ip_transient );

		try {
			$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/token' );
			$request->set_param( 'grant_type', 'authorization_code' );
			$request->set_param( 'client_id', $this->client_id );
			$request->set_param( 'code', 'irrelevant' );
			$request->set_param( 'redirect_uri', $this->redirect_uri );

			$response = \rest_get_server()->dispatch( $request );
			$data     = $response->get_data();
			$headers  = $response->get_headers();

			$this->assertEquals( 429, $response->get_status() );
			$this->assertEquals( 'rate_limited', $data['error'] );
			$this->assertSame( (string) MINUTE_IN_SECONDS, $headers['Retry-After'] ?? null, 'Rate-limit responses must include Retry-After per RFC 6585 §4.' );
			// The fail-closed branch must not write a shared empty-IP transient.
			$this->assertFalse( \get_transient( $empty_ip_transient ) );
		} finally {
			$this->restore_client_ip_server( $snapshot );
		}
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
		$request->set_param( 'code_verifier', $this->code_verifier );

		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'access_token', $data );
		$this->assertArrayHasKey( 'token_type', $data );
		$this->assertArrayHasKey( 'expires_in', $data );
		$this->assertArrayHasKey( 'refresh_token', $data );
		$this->assertEquals( 'Bearer', $data['token_type'] );

		// IndieAuth `me` and SWICG Basic Profile `activitypub_actor_id` must both be present and equal.
		$this->assertArrayHasKey( 'me', $data );
		$this->assertArrayHasKey( 'activitypub_actor_id', $data );
		$this->assertNotEmpty( $data['me'] );
		$this->assertSame( $data['me'], $data['activitypub_actor_id'] );
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
		$request->set_param( 'code_verifier', $this->code_verifier );

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
	 * A non-admin user must not be able to revoke a token belonging to another user.
	 *
	 * Per RFC 7009 the endpoint returns 200 either way so the caller cannot
	 * probe for token existence, but the victim's token must still verify.
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_cross_user_is_silently_skipped() {
		$other_user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$token_data    = Token::create( $other_user_id, $this->client_id, array( Scope::READ ) );

		\wp_set_current_user( $this->user_id );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/revoke' );
		$request->set_param( 'token', $token_data['access_token'] );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status(), 'Revoke must respond 200 regardless of ownership.' );

		$validation = Token::validate( $token_data['access_token'] );
		$this->assertNotWPError( $validation, 'Another user must not be able to revoke this token.' );
		$this->assertEquals( $other_user_id, $validation->get_user_id() );
	}

	/**
	 * A site admin may revoke any token.
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_allows_admin_to_revoke_any_token() {
		$admin_id   = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$token_data = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		\wp_set_current_user( $admin_id );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/revoke' );
		$request->set_param( 'token', $token_data['access_token'] );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertInstanceOf( \WP_Error::class, Token::validate( $token_data['access_token'] ) );
	}

	/**
	 * A bearer-authenticated client must not be able to revoke a token its
	 * user granted to a different OAuth client.
	 *
	 * Per RFC 7009 §2.1, when the caller authenticated as a client, the
	 * token being revoked must have been issued to that same client.
	 * User-based ownership must not override the client scoping.
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_cross_client_bearer_is_silently_skipped() {
		$other_client_result = Client::register(
			array(
				'name'          => 'Other Client',
				'redirect_uris' => array( $this->redirect_uri ),
			)
		);
		$other_client_id     = $other_client_result['client_id'];

		$caller_token_data = Token::create( $this->user_id, $other_client_id, array( Scope::READ ) );
		$target_token_data = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		$caller_token = Token::validate( $caller_token_data['access_token'] );
		$this->assertInstanceOf( Token::class, $caller_token );

		$this->set_oauth_current_token( $caller_token );
		\wp_set_current_user( $this->user_id );

		try {
			$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/revoke' );
			$request->set_param( 'token', $target_token_data['access_token'] );

			$response = \rest_get_server()->dispatch( $request );

			$this->assertEquals( 200, $response->get_status(), 'Revoke must respond 200 regardless of ownership.' );

			$validation = Token::validate( $target_token_data['access_token'] );
			$this->assertNotWPError( $validation, 'A bearer token from a different client must not revoke the target.' );
			$this->assertEquals( $this->client_id, $validation->get_client_id() );
		} finally {
			Client::delete( $other_client_id );
		}
	}

	/**
	 * A bearer-authenticated client must be able to revoke a token it
	 * issued, even when the token belongs to a different user than the
	 * one currently authenticated via the bearer.
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_same_client_bearer_succeeds() {
		$other_user_id = $this->factory->user->create( array( 'role' => 'editor' ) );

		$caller_token_data = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );
		$target_token_data = Token::create( $other_user_id, $this->client_id, array( Scope::READ ) );

		$caller_token = Token::validate( $caller_token_data['access_token'] );
		$this->assertInstanceOf( Token::class, $caller_token );

		$this->set_oauth_current_token( $caller_token );
		\wp_set_current_user( $this->user_id );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/revoke' );
		$request->set_param( 'token', $target_token_data['access_token'] );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertInstanceOf( \WP_Error::class, Token::validate( $target_token_data['access_token'] ) );
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

		// IndieAuth `me` and SWICG Basic Profile `activitypub_actor_id` must both be present and equal.
		$this->assertArrayHasKey( 'me', $data );
		$this->assertArrayHasKey( 'activitypub_actor_id', $data );
		$this->assertNotEmpty( $data['me'] );
		$this->assertSame( $data['me'], $data['activitypub_actor_id'] );
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

	/**
	 * Test that a logged-in user cannot introspect another user's token.
	 *
	 * Regression: cookie-authenticated callers bypassed the scoping check (which only ran
	 * for OAuth-authenticated callers), so any logged-in user could read metadata for any
	 * token string.
	 *
	 * @covers ::introspect
	 */
	public function test_introspect_hides_other_users_token_from_cookie_user() {
		$token_data = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		// A different, non-admin user authenticated via cookie (no OAuth token).
		$other = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $other );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/introspect' );
		$request->set_param( 'token', $token_data['access_token'] );

		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertFalse( $data['active'], 'A logged-in user must not introspect another user\'s token.' );
		$this->assertArrayNotHasKey( 'username', $data, 'No token metadata should leak to a non-owner.' );
	}

	/**
	 * Test that a user can still introspect their own token via a cookie session.
	 *
	 * @covers ::introspect
	 */
	public function test_introspect_allows_own_token_for_cookie_user() {
		$token_data = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		\wp_set_current_user( $this->user_id );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/introspect' );
		$request->set_param( 'token', $token_data['access_token'] );

		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['active'], 'A user must be able to introspect their own token.' );
	}

	/**
	 * Test that an administrator can introspect any user's token.
	 *
	 * @covers ::introspect
	 */
	public function test_introspect_allows_admin_to_view_any_token() {
		$token_data = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		$admin = $this->factory->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $admin );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/introspect' );
		$request->set_param( 'token', $token_data['access_token'] );

		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['active'], 'An administrator must be able to introspect any token.' );
		$this->assertEquals( $this->client_id, $data['client_id'] );
	}
}
