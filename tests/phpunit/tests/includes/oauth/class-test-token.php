<?php
/**
 * Test file for OAuth Token class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\OAuth;

use Activitypub\OAuth\Client;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Token;
use Activitypub\Post_Types;

/**
 * Test class for OAuth Token.
 *
 * @coversDefaultClass \Activitypub\OAuth\Token
 *
 * @group activitypub
 * @group oauth
 */
class Test_Token extends \WP_UnitTestCase {

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
				'redirect_uris' => array( 'https://example.com/callback' ),
			)
		);
		$this->client_id = $client_result['client_id'];
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		// Clean up clients and tokens.
		if ( $this->client_id ) {
			Client::delete( $this->client_id );
		}

		parent::tear_down();
	}

	/**
	 * Test generate_token produces a hex string.
	 *
	 * @covers ::generate_token
	 */
	public function test_generate_token() {
		$token = Token::generate_token();

		// Default length is 32 bytes = 64 hex chars.
		$this->assertEquals( 64, strlen( $token ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]+$/', $token );
	}

	/**
	 * Test generate_token with custom length.
	 *
	 * @covers ::generate_token
	 */
	public function test_generate_token_custom_length() {
		$token = Token::generate_token( 16 );
		$this->assertEquals( 32, strlen( $token ) );
	}

	/**
	 * Test hash_token produces SHA-256 hash.
	 *
	 * @covers ::hash_token
	 */
	public function test_hash_token() {
		$token = 'test_token_value';
		$hash  = Token::hash_token( $token );

		// SHA-256 produces 64 hex chars.
		$this->assertEquals( 64, strlen( $hash ) );
		$this->assertEquals( hash( 'sha256', $token ), $hash );
	}

	/**
	 * Test create method returns token data.
	 *
	 * @covers ::create
	 */
	public function test_create() {
		$scopes = array( Scope::READ, Scope::WRITE );
		$result = Token::create( $this->user_id, $this->client_id, $scopes );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'access_token', $result );
		$this->assertArrayHasKey( 'token_type', $result );
		$this->assertArrayHasKey( 'expires_in', $result );
		$this->assertArrayHasKey( 'refresh_token', $result );
		$this->assertArrayHasKey( 'scope', $result );

		$this->assertEquals( 'Bearer', $result['token_type'] );
		$this->assertEquals( Token::DEFAULT_EXPIRATION, $result['expires_in'] );
		$this->assertEquals( 'read write', $result['scope'] );
	}

	/**
	 * Test validate method with valid token.
	 *
	 * @covers ::validate
	 */
	public function test_validate_valid_token() {
		$result = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );
		$token  = Token::validate( $result['access_token'] );

		$this->assertInstanceOf( Token::class, $token );
		$this->assertEquals( $this->user_id, $token->get_user_id() );
		$this->assertEquals( $this->client_id, $token->get_client_id() );
	}

	/**
	 * Test validate method with invalid token.
	 *
	 * @covers ::validate
	 */
	public function test_validate_invalid_token() {
		$result = Token::validate( 'invalid_token_value' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_invalid_token', $result->get_error_code() );
	}

	/**
	 * Test validate method with expired token.
	 *
	 * @covers ::validate
	 */
	public function test_validate_expired_token() {
		// Create a token that expires immediately.
		$result = Token::create( $this->user_id, $this->client_id, array( Scope::READ ), 0 );

		// Wait a moment for expiration.
		sleep( 1 );

		$validation = Token::validate( $result['access_token'] );

		$this->assertInstanceOf( \WP_Error::class, $validation );
		$this->assertEquals( 'activitypub_token_expired', $validation->get_error_code() );
	}

	/**
	 * Test token has_scope method.
	 *
	 * @covers ::has_scope
	 */
	public function test_has_scope() {
		$result = Token::create( $this->user_id, $this->client_id, array( Scope::READ, Scope::WRITE ) );
		$token  = Token::validate( $result['access_token'] );

		$this->assertTrue( $token->has_scope( Scope::READ ) );
		$this->assertTrue( $token->has_scope( Scope::WRITE ) );
		$this->assertFalse( $token->has_scope( Scope::FOLLOW ) );
	}

	/**
	 * Test token get_scopes method.
	 *
	 * @covers ::get_scopes
	 */
	public function test_get_scopes() {
		$scopes = array( Scope::READ, Scope::WRITE );
		$result = Token::create( $this->user_id, $this->client_id, $scopes );
		$token  = Token::validate( $result['access_token'] );

		$this->assertEquals( $scopes, $token->get_scopes() );
	}

	/**
	 * Test token get_expires_at method.
	 *
	 * @covers ::get_expires_at
	 */
	public function test_get_expires_at() {
		$result = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );
		$token  = Token::validate( $result['access_token'] );

		$expires_at = $token->get_expires_at();
		$this->assertIsInt( $expires_at );
		$this->assertGreaterThan( time(), $expires_at );
	}

	/**
	 * Test token is_expired method.
	 *
	 * @covers ::is_expired
	 */
	public function test_is_expired() {
		$result = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );
		$token  = Token::validate( $result['access_token'] );

		$this->assertFalse( $token->is_expired() );
	}

	/**
	 * Test refresh method.
	 *
	 * @covers ::refresh
	 */
	public function test_refresh() {
		$original = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );
		$result   = Token::refresh( $original['refresh_token'], $this->client_id );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'access_token', $result );
		$this->assertArrayHasKey( 'refresh_token', $result );

		// New tokens should be different.
		$this->assertNotEquals( $original['access_token'], $result['access_token'] );
		$this->assertNotEquals( $original['refresh_token'], $result['refresh_token'] );

		// Old token should be revoked.
		$old_validation = Token::validate( $original['access_token'] );
		$this->assertInstanceOf( \WP_Error::class, $old_validation );
	}

	/**
	 * Test refresh method with invalid refresh token.
	 *
	 * @covers ::refresh
	 */
	public function test_refresh_invalid_token() {
		$result = Token::refresh( 'invalid_refresh_token', $this->client_id );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_invalid_refresh_token', $result->get_error_code() );
	}

	/**
	 * Test refresh method with wrong client ID.
	 *
	 * @covers ::refresh
	 */
	public function test_refresh_wrong_client() {
		$original = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );
		$result   = Token::refresh( $original['refresh_token'], 'wrong_client_id' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'activitypub_client_mismatch', $result->get_error_code() );
	}

	/**
	 * Test revoke method with access token.
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_access_token() {
		$result = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		$revoke_result = Token::revoke( $result['access_token'] );
		$this->assertTrue( $revoke_result );

		// Token should no longer validate.
		$validation = Token::validate( $result['access_token'] );
		$this->assertInstanceOf( \WP_Error::class, $validation );
	}

	/**
	 * Test revoke method with refresh token.
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_refresh_token() {
		$result = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		$revoke_result = Token::revoke( $result['refresh_token'] );
		$this->assertTrue( $revoke_result );

		// Refresh should fail.
		$refresh_result = Token::refresh( $result['refresh_token'], $this->client_id );
		$this->assertInstanceOf( \WP_Error::class, $refresh_result );
	}

	/**
	 * Test revoke method with non-existent token.
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_nonexistent_token() {
		// Per RFC 7009, revoking a non-existent token should succeed.
		$result = Token::revoke( 'nonexistent_token' );
		$this->assertTrue( $result );
	}

	/**
	 * A mismatched caller user must not be able to revoke another user's token.
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_rejects_mismatched_caller_user() {
		$other_user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$token         = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		// Different user, no client context — must not delete.
		$result = Token::revoke( $token['access_token'], $other_user_id );
		$this->assertTrue( $result, 'Revoke must return true per RFC 7009 even when it skipped the delete.' );

		$validation = Token::validate( $token['access_token'] );
		$this->assertNotWPError( $validation, 'Token must survive a cross-user revoke attempt.' );
	}

	/**
	 * A matching caller user may revoke.
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_allows_matching_caller_user() {
		$token = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		$result = Token::revoke( $token['access_token'], $this->user_id );
		$this->assertTrue( $result );

		$this->assertInstanceOf( \WP_Error::class, Token::validate( $token['access_token'] ) );
	}

	/**
	 * A matching caller client may revoke even when the user differs (same client, other user's token).
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_allows_matching_caller_client() {
		$other_user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$token         = Token::create( $other_user_id, $this->client_id, array( Scope::READ ) );

		// Caller is a different user, but presents the same client_id.
		$result = Token::revoke( $token['access_token'], $this->user_id, $this->client_id );
		$this->assertTrue( $result );

		$this->assertInstanceOf( \WP_Error::class, Token::validate( $token['access_token'] ) );
	}

	/**
	 * A mismatched caller client must not revoke a token issued to a different client.
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_rejects_mismatched_caller_client() {
		$other_client_result = Client::register(
			array(
				'name'          => 'Another Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
			)
		);
		$other_client_id     = $other_client_result['client_id'];

		try {
			$token = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

			// Different user AND different client — both checks must fail.
			$other_user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
			$result        = Token::revoke( $token['access_token'], $other_user_id, $other_client_id );
			$this->assertTrue( $result );

			$validation = Token::validate( $token['access_token'] );
			$this->assertNotWPError( $validation, 'Cross-client revoke must not delete.' );
		} finally {
			Client::delete( $other_client_id );
		}
	}

	/**
	 * Refresh-token revocation must also honour the caller ownership check.
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_refresh_token_honours_caller_check() {
		$other_user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$token         = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		$result = Token::revoke( $token['refresh_token'], $other_user_id );
		$this->assertTrue( $result );

		// Refresh must still work because revocation should have been skipped.
		$refresh = Token::refresh( $token['refresh_token'], $this->client_id );
		$this->assertIsArray( $refresh, 'Refresh token must survive a cross-user revoke attempt.' );
	}

	/**
	 * Internal callers that pass neither caller user nor caller client
	 * retain the pre-check behavior and delete the token regardless.
	 *
	 * @covers ::revoke
	 */
	public function test_revoke_without_caller_args_preserves_internal_behavior() {
		$token = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );

		$result = Token::revoke( $token['access_token'] );
		$this->assertTrue( $result );

		$this->assertInstanceOf( \WP_Error::class, Token::validate( $token['access_token'] ) );
	}

	/**
	 * Test revoke_all_for_user method.
	 *
	 * @covers ::revoke_all_for_user
	 */
	public function test_revoke_all_for_user() {
		// Create multiple tokens.
		$token1 = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );
		$token2 = Token::create( $this->user_id, $this->client_id, array( Scope::WRITE ) );
		$token3 = Token::create( $this->user_id, $this->client_id, array( Scope::FOLLOW ) );

		$count = Token::revoke_all_for_user( $this->user_id );
		$this->assertEquals( 3, $count );

		// All tokens should be revoked.
		$this->assertInstanceOf( \WP_Error::class, Token::validate( $token1['access_token'] ) );
		$this->assertInstanceOf( \WP_Error::class, Token::validate( $token2['access_token'] ) );
		$this->assertInstanceOf( \WP_Error::class, Token::validate( $token3['access_token'] ) );
	}

	/**
	 * Test per-user token limit enforcement.
	 *
	 * @covers ::create
	 */
	public function test_enforce_token_limit() {
		$scopes = array( Scope::READ );

		// Create tokens up to the limit + 5.
		$tokens = array();
		for ( $i = 0; $i < Token::MAX_TOKENS_PER_USER + 5; $i++ ) {
			$tokens[] = Token::create( $this->user_id, $this->client_id, $scopes );
		}

		// Count remaining tokens in user meta.
		$all_meta = \get_user_meta( $this->user_id );
		$count    = 0;
		foreach ( $all_meta as $meta_key => $meta_values ) {
			if ( 0 === strpos( $meta_key, Token::META_PREFIX ) ) {
				++$count;
			}
		}

		$this->assertLessThanOrEqual( Token::MAX_TOKENS_PER_USER, $count );

		// The most recently created token should still be valid.
		$latest = end( $tokens );
		$this->assertNotInstanceOf( \WP_Error::class, Token::validate( $latest['access_token'] ) );

		// The earliest tokens should have been revoked.
		$earliest = $tokens[0];
		$this->assertInstanceOf( \WP_Error::class, Token::validate( $earliest['access_token'] ) );
	}

	/**
	 * Test cleanup_expired method.
	 *
	 * @covers ::cleanup_expired
	 */
	public function test_cleanup_expired() {
		// Create a token that expires immediately.
		Token::create( $this->user_id, $this->client_id, array( Scope::READ ), 0 );

		/*
		 * Wait for expiration plus grace period buffer (normally 1 day, but we can't wait that long).
		 * For this test, we'll just verify the method runs without error.
		 */
		$count = Token::cleanup_expired();
		$this->assertIsInt( $count );
	}
}
