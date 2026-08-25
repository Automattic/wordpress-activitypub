<?php
/**
 * Test file for OAuth Authorization_Code class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\OAuth;

use Activitypub\OAuth\Authorization_Code;
use Activitypub\OAuth\Client;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Token;
use Activitypub\Post_Types;

/**
 * Test class for OAuth Authorization_Code.
 *
 * @coversDefaultClass \Activitypub\OAuth\Authorization_Code
 *
 * @group activitypub
 * @group oauth
 */
class Test_Authorization_Code extends \WP_UnitTestCase {

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

		// Register OAuth post types.
		Post_Types::register_oauth_post_types();

		// Create a test user.
		$this->user_id = $this->factory->user->create(
			array(
				'role' => 'editor',
			)
		);

		// Create a test client.
		$client_result   = Client::register(
			array(
				'name'          => 'Test Client',
				'redirect_uris' => array( $this->redirect_uri ),
			)
		);
		$this->client_id = $client_result['client_id'];
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		// Clean up client.
		if ( $this->client_id ) {
			Client::delete( $this->client_id );
		}

		parent::tear_down();
	}

	/**
	 * Generate a PKCE code verifier.
	 *
	 * @return string
	 */
	protected function generate_code_verifier() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Test compute_code_challenge method.
	 *
	 * @covers ::compute_code_challenge
	 */
	public function test_compute_code_challenge() {
		$verifier  = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
		$challenge = Authorization_Code::compute_code_challenge( $verifier );

		// Verify BASE64URL encoding (no +, /, or = characters).
		$this->assertDoesNotMatchRegularExpression( '/[+\/=]/', $challenge );

		// Should be a valid S256 challenge.
		$this->assertNotEmpty( $challenge );
	}

	/**
	 * Test verify_pkce method with S256.
	 *
	 * @covers ::verify_pkce
	 */
	public function test_verify_pkce_s256() {
		$verifier  = $this->generate_code_verifier();
		$challenge = Authorization_Code::compute_code_challenge( $verifier );

		$this->assertTrue( Authorization_Code::verify_pkce( $verifier, $challenge, 'S256' ) );
		$this->assertFalse( Authorization_Code::verify_pkce( 'wrong_verifier', $challenge, 'S256' ) );
	}

	/**
	 * Test verify_pkce method rejects plain method.
	 *
	 * @covers ::verify_pkce
	 */
	public function test_verify_pkce_plain_rejected() {
		$verifier = $this->generate_code_verifier();

		// Plain PKCE is no longer supported; both cases should fail.
		$this->assertFalse( Authorization_Code::verify_pkce( $verifier, $verifier, 'plain' ) );
		$this->assertFalse( Authorization_Code::verify_pkce( 'wrong_verifier', $verifier, 'plain' ) );
	}

	/**
	 * Test verify_pkce method with empty values.
	 *
	 * PKCE is optional: if no code_challenge was stored during authorization,
	 * verification is skipped (returns true). Only fail when a challenge exists
	 * but no verifier is provided.
	 *
	 * @covers ::verify_pkce
	 */
	public function test_verify_pkce_empty() {
		// Challenge exists but verifier missing: should fail.
		$this->assertFalse( Authorization_Code::verify_pkce( '', 'challenge', 'S256' ) );

		// No challenge stored (PKCE not used): should pass (skip verification).
		$this->assertTrue( Authorization_Code::verify_pkce( 'verifier', '', 'S256' ) );
		$this->assertTrue( Authorization_Code::verify_pkce( '', '', 'S256' ) );
	}

	/**
	 * Test generate_code method.
	 *
	 * @covers ::generate_code
	 */
	public function test_generate_code() {
		$code = Authorization_Code::generate_code();

		// Should be 64 hex characters (32 bytes).
		$this->assertEquals( 64, strlen( $code ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]+$/', $code );
	}

	/**
	 * Test create method.
	 *
	 * @covers ::create
	 */
	public function test_create() {
		$verifier  = $this->generate_code_verifier();
		$challenge = Authorization_Code::compute_code_challenge( $verifier );

		$code = Authorization_Code::create(
			$this->user_id,
			$this->client_id,
			$this->redirect_uri,
			array( Scope::READ, Scope::WRITE ),
			$challenge,
			'S256'
		);

		$this->assertIsString( $code );
		$this->assertEquals( 64, strlen( $code ) );
	}

	/**
	 * Test create method with invalid client.
	 *
	 * @covers ::create
	 */
	public function test_create_invalid_client() {
		$result = Authorization_Code::create(
			$this->user_id,
			'invalid-client-id',
			$this->redirect_uri,
			array( Scope::READ ),
			'challenge',
			'S256'
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_client_not_found', $result->get_error_code() );
	}

	/**
	 * Test create method with invalid redirect URI.
	 *
	 * @covers ::create
	 */
	public function test_create_invalid_redirect_uri() {
		$result = Authorization_Code::create(
			$this->user_id,
			$this->client_id,
			'https://other.com/callback',
			array( Scope::READ ),
			'challenge',
			'S256'
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_invalid_redirect_uri', $result->get_error_code() );
	}

	/**
	 * Test exchange method.
	 *
	 * @covers ::exchange
	 */
	public function test_exchange() {
		$verifier  = $this->generate_code_verifier();
		$challenge = Authorization_Code::compute_code_challenge( $verifier );

		$code = Authorization_Code::create(
			$this->user_id,
			$this->client_id,
			$this->redirect_uri,
			array( Scope::READ, Scope::WRITE ),
			$challenge,
			'S256'
		);

		$result = Authorization_Code::exchange(
			$code,
			$this->client_id,
			$this->redirect_uri,
			$verifier
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'access_token', $result );
		$this->assertArrayHasKey( 'refresh_token', $result );
		$this->assertArrayHasKey( 'token_type', $result );
		$this->assertArrayHasKey( 'expires_in', $result );
		$this->assertArrayHasKey( 'scope', $result );

		$this->assertEquals( 'Bearer', $result['token_type'] );
	}

	/**
	 * Test exchange method prevents code reuse.
	 *
	 * @covers ::exchange
	 */
	public function test_exchange_prevents_reuse() {
		$verifier  = $this->generate_code_verifier();
		$challenge = Authorization_Code::compute_code_challenge( $verifier );

		$code = Authorization_Code::create(
			$this->user_id,
			$this->client_id,
			$this->redirect_uri,
			array( Scope::READ ),
			$challenge,
			'S256'
		);

		// First exchange should succeed.
		$result1 = Authorization_Code::exchange(
			$code,
			$this->client_id,
			$this->redirect_uri,
			$verifier
		);
		$this->assertIsArray( $result1 );

		// Second exchange with same code should fail.
		$result2 = Authorization_Code::exchange(
			$code,
			$this->client_id,
			$this->redirect_uri,
			$verifier
		);
		$this->assertInstanceOf( \WP_Error::class, $result2 );
		$this->assertEquals( 'activitypub_invalid_code', $result2->get_error_code() );
	}

	/**
	 * Test exchange method with invalid code.
	 *
	 * @covers ::exchange
	 */
	public function test_exchange_invalid_code() {
		$result = Authorization_Code::exchange(
			'invalid_code',
			$this->client_id,
			$this->redirect_uri,
			'verifier'
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_invalid_code', $result->get_error_code() );
	}

	/**
	 * Test exchange method with wrong redirect URI.
	 *
	 * @covers ::exchange
	 */
	public function test_exchange_redirect_uri_mismatch() {
		$verifier  = $this->generate_code_verifier();
		$challenge = Authorization_Code::compute_code_challenge( $verifier );

		$code = Authorization_Code::create(
			$this->user_id,
			$this->client_id,
			$this->redirect_uri,
			array( Scope::READ ),
			$challenge,
			'S256'
		);

		$result = Authorization_Code::exchange(
			$code,
			$this->client_id,
			'https://other.com/callback', // Wrong redirect URI.
			$verifier
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_redirect_uri_mismatch', $result->get_error_code() );
	}

	/**
	 * Test exchange works with custom scheme redirect URI.
	 *
	 * @covers ::create
	 * @covers ::exchange
	 */
	public function test_exchange_custom_scheme_redirect_uri() {
		$custom_uri = 'activitypress://oauth/callback';

		// Register a client with the custom scheme.
		$client_result    = Client::register(
			array(
				'name'          => 'Native App',
				'redirect_uris' => array( $custom_uri ),
			)
		);
		$custom_client_id = $client_result['client_id'];

		$verifier  = $this->generate_code_verifier();
		$challenge = Authorization_Code::compute_code_challenge( $verifier );

		$code = Authorization_Code::create(
			$this->user_id,
			$custom_client_id,
			$custom_uri,
			array( Scope::READ ),
			$challenge,
			'S256'
		);

		$this->assertNotInstanceOf( \WP_Error::class, $code );

		$result = Authorization_Code::exchange(
			$code,
			$custom_client_id,
			$custom_uri,
			$verifier
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'access_token', $result );

		Client::delete( $custom_client_id );
	}

	/**
	 * Test exchange method with wrong PKCE verifier.
	 *
	 * @covers ::exchange
	 */
	public function test_exchange_invalid_pkce() {
		$verifier  = $this->generate_code_verifier();
		$challenge = Authorization_Code::compute_code_challenge( $verifier );

		$code = Authorization_Code::create(
			$this->user_id,
			$this->client_id,
			$this->redirect_uri,
			array( Scope::READ ),
			$challenge,
			'S256'
		);

		$result = Authorization_Code::exchange(
			$code,
			$this->client_id,
			$this->redirect_uri,
			'wrong_verifier' // Wrong PKCE verifier.
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_invalid_pkce', $result->get_error_code() );
	}

	/**
	 * Test exchange method with wrong client ID.
	 *
	 * @covers ::exchange
	 */
	public function test_exchange_wrong_client() {
		$verifier  = $this->generate_code_verifier();
		$challenge = Authorization_Code::compute_code_challenge( $verifier );

		$code = Authorization_Code::create(
			$this->user_id,
			$this->client_id,
			$this->redirect_uri,
			array( Scope::READ ),
			$challenge,
			'S256'
		);

		$result = Authorization_Code::exchange(
			$code,
			'wrong-client-id',
			$this->redirect_uri,
			$verifier
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_client_mismatch', $result->get_error_code() );
	}

	/**
	 * Test exchange method filters scopes to client allowed scopes.
	 *
	 * @covers ::exchange
	 * @covers ::create
	 */
	public function test_exchange_filters_scopes() {
		// Create a client with limited scopes.
		$limited_client = Client::register(
			array(
				'name'          => 'Limited Client',
				'redirect_uris' => array( 'https://limited.com/callback' ),
				'scopes'        => array( Scope::READ ),
			)
		);

		$verifier  = $this->generate_code_verifier();
		$challenge = Authorization_Code::compute_code_challenge( $verifier );

		// Request more scopes than allowed.
		$code = Authorization_Code::create(
			$this->user_id,
			$limited_client['client_id'],
			'https://limited.com/callback',
			array( Scope::READ, Scope::WRITE, Scope::PUSH ),
			$challenge,
			'S256'
		);

		$result = Authorization_Code::exchange(
			$code,
			$limited_client['client_id'],
			'https://limited.com/callback',
			$verifier
		);

		// Should only have the allowed scope.
		$this->assertIsArray( $result );
		$this->assertEquals( 'read', $result['scope'] );

		// Clean up.
		Client::delete( $limited_client['client_id'] );
	}

	/**
	 * Public clients must supply a PKCE challenge by default.
	 *
	 * @covers ::create
	 */
	public function test_create_without_pkce_blocked_by_default() {
		$result = Authorization_Code::create(
			$this->user_id,
			$this->client_id,
			$this->redirect_uri,
			array( Scope::READ ),
			'', // No PKCE.
			'S256'
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_pkce_required', $result->get_error_code() );
	}

	/**
	 * Operators can relax the PKCE requirement via the filter.
	 *
	 * @covers ::create
	 */
	public function test_create_without_pkce_allowed_when_filter_returns_false() {
		\add_filter( 'activitypub_oauth_require_pkce', '__return_false' );

		$code = Authorization_Code::create(
			$this->user_id,
			$this->client_id,
			$this->redirect_uri,
			array( Scope::READ ),
			'', // No PKCE.
			'S256'
		);

		\remove_filter( 'activitypub_oauth_require_pkce', '__return_false' );

		$this->assertIsString( $code );
	}

	/**
	 * Test that the PKCE filter does not affect requests that include PKCE.
	 *
	 * @covers ::create
	 */
	public function test_create_with_pkce_passes_regardless_of_filter() {
		$verifier  = $this->generate_code_verifier();
		$challenge = Authorization_Code::compute_code_challenge( $verifier );

		$code = Authorization_Code::create(
			$this->user_id,
			$this->client_id,
			$this->redirect_uri,
			array( Scope::READ ),
			$challenge,
			'S256'
		);

		$this->assertIsString( $code );
	}

	/**
	 * Test cleanup method.
	 *
	 * @covers ::cleanup
	 */
	public function test_cleanup() {
		// Cleanup should run without errors.
		$count = Authorization_Code::cleanup();
		$this->assertIsInt( $count );
	}

	/**
	 * Test complete PKCE flow.
	 *
	 * @covers ::create
	 * @covers ::exchange
	 * @covers ::verify_pkce
	 * @covers ::compute_code_challenge
	 */
	public function test_complete_pkce_flow() {
		// Step 1: Client generates code verifier and challenge.
		$code_verifier  = $this->generate_code_verifier();
		$code_challenge = Authorization_Code::compute_code_challenge( $code_verifier );

		// Step 2: Server creates authorization code.
		$code = Authorization_Code::create(
			$this->user_id,
			$this->client_id,
			$this->redirect_uri,
			array( Scope::READ, Scope::WRITE ),
			$code_challenge,
			'S256'
		);

		$this->assertIsString( $code );

		// Step 3: Client exchanges code for tokens.
		$tokens = Authorization_Code::exchange(
			$code,
			$this->client_id,
			$this->redirect_uri,
			$code_verifier
		);

		$this->assertIsArray( $tokens );
		$this->assertArrayHasKey( 'access_token', $tokens );
		$this->assertArrayHasKey( 'refresh_token', $tokens );

		// Step 4: Verify the access token works.
		$token = Token::validate( $tokens['access_token'] );
		$this->assertInstanceOf( Token::class, $token );
		$this->assertEquals( $this->user_id, $token->get_user_id() );
		$this->assertEquals( $this->client_id, $token->get_client_id() );
		$this->assertTrue( $token->has_scope( Scope::READ ) );
		$this->assertTrue( $token->has_scope( Scope::WRITE ) );
	}
}
