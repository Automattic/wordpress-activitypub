<?php
/**
 * Test file for Verification Trait.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\OAuth\Server as OAuth_Server;
use Activitypub\Rest\Verification;

/**
 * Test class for Verification Trait.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Verification
 */
class Test_Trait_Verification extends \WP_UnitTestCase {

	/**
	 * The stub instance that uses the Verification trait.
	 *
	 * @var object
	 */
	protected $instance;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->instance = new class() {
			use Verification;
		};
		$this->user_id  = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\wp_set_current_user( 0 );
		\remove_all_filters( 'activitypub_defer_signature_verification' );
		\remove_all_filters( 'activitypub_oauth_check_permission' );

		// Reset OAuth token state.
		$reflection = new \ReflectionClass( OAuth_Server::class );
		$property   = $reflection->getProperty( 'current_token' );
		$property->setAccessible( true );
		$property->setValue( null, null );

		parent::tear_down();
	}

	/**
	 * Test HEAD request always returns true.
	 *
	 * @covers ::verify_signature
	 */
	public function test_verify_signature_head_returns_true() {
		$request = new \WP_REST_Request( 'HEAD', '/activitypub/1.0/users/1' );

		$this->assertTrue( $this->instance->verify_signature( $request ) );
	}

	/**
	 * Test GET request without authorized fetch returns true.
	 *
	 * @covers ::verify_signature
	 */
	public function test_verify_signature_get_without_authorized_fetch() {
		\delete_option( 'activitypub_authorized_fetch' );

		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/users/1' );

		$this->assertTrue( $this->instance->verify_signature( $request ) );
	}

	/**
	 * Test GET request with authorized fetch enabled requires signature.
	 *
	 * @covers ::verify_signature
	 */
	public function test_verify_signature_get_with_authorized_fetch() {
		\update_option( 'activitypub_authorized_fetch', '1' );

		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/users/1' );

		// Without a valid signature, this should return WP_Error.
		$result = $this->instance->verify_signature( $request );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_signature_verification', $result->get_error_code() );
		$this->assertEquals( 401, $result->get_error_data()['status'] );

		\delete_option( 'activitypub_authorized_fetch' );
	}

	/**
	 * Test POST request requires signature.
	 *
	 * @covers ::verify_signature
	 */
	public function test_verify_signature_post_requires_signature() {
		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );

		$result = $this->instance->verify_signature( $request );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_signature_verification', $result->get_error_code() );
		$this->assertEquals( 401, $result->get_error_data()['status'] );
	}

	/**
	 * Test defer filter bypasses signature verification.
	 *
	 * @covers ::verify_signature
	 */
	public function test_verify_signature_defer_filter() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );

		$this->assertTrue( $this->instance->verify_signature( $request ) );
	}

	/**
	 * Test GET request uses read scope.
	 *
	 * @covers ::verify_authentication
	 */
	public function test_verify_authentication_get_uses_read_scope() {
		$captured_scope = null;
		\add_filter(
			'activitypub_oauth_check_permission',
			function ( $result, $request, $scope ) use ( &$captured_scope ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
				$captured_scope = $scope;
				return true;
			},
			10,
			3
		);

		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/users/1/outbox' );

		$this->instance->verify_authentication( $request );

		$this->assertEquals( 'read', $captured_scope );
	}

	/**
	 * Test POST request uses write scope.
	 *
	 * @covers ::verify_authentication
	 */
	public function test_verify_authentication_post_uses_write_scope() {
		$captured_scope = null;
		\add_filter(
			'activitypub_oauth_check_permission',
			function ( $result, $request, $scope ) use ( &$captured_scope ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
				$captured_scope = $scope;
				return true;
			},
			10,
			3
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/outbox' );

		$this->instance->verify_authentication( $request );

		$this->assertEquals( 'write', $captured_scope );
	}

	/**
	 * Test OAuth success proceeds to owner verification.
	 *
	 * @covers ::verify_authentication
	 */
	public function test_verify_authentication_oauth_success_without_user_id() {
		\add_filter(
			'activitypub_oauth_check_permission',
			function () {
				return true;
			}
		);

		// Request without user_id param skips owner verification.
		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/outbox' );

		$result = $this->instance->verify_authentication( $request );

		$this->assertTrue( $result );
	}

	/**
	 * Test OAuth failure with Bearer token does not fall back to App Passwords.
	 *
	 * @covers ::verify_authentication
	 */
	public function test_verify_authentication_oauth_failure_no_fallback() {
		// Simulate OAuth returning error (scope check fails).
		$oauth_error = new \WP_Error(
			'activitypub_insufficient_scope',
			'Insufficient scope.',
			array( 'status' => 403 )
		);
		\add_filter(
			'activitypub_oauth_check_permission',
			function () use ( $oauth_error ) {
				return $oauth_error;
			}
		);

		// Log in a user (would pass Application Passwords if fallback occurred).
		\wp_set_current_user( $this->user_id );

		// Simulate an OAuth request by setting a current token via reflection.
		$this->set_oauth_token( $this->create_mock_token( 0, false ) );

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/outbox' );

		$result = $this->instance->verify_authentication( $request );

		// Should return the OAuth error, NOT fall back to Application Passwords.
		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_insufficient_scope', $result->get_error_code() );
	}

	/**
	 * Test no OAuth token returns error (Application Passwords not accepted directly).
	 *
	 * @covers ::verify_authentication
	 */
	public function test_verify_authentication_requires_oauth() {
		// OAuth returns error — no token present.
		\add_filter(
			'activitypub_oauth_check_permission',
			function () {
				return new \WP_Error( 'activitypub_oauth_required', 'OAuth required.' );
			}
		);

		// User is logged in via Application Passwords, but that's not enough.
		\wp_set_current_user( $this->user_id );

		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/outbox' );

		$result = $this->instance->verify_authentication( $request );

		// Should NOT fall back — OAuth is required.
		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_oauth_required', $result->get_error_code() );
	}

	/**
	 * Test request without user_id param skips owner verification.
	 *
	 * @covers ::verify_authentication
	 */
	public function test_verify_authentication_skips_owner_without_user_id() {
		\add_filter(
			'activitypub_oauth_check_permission',
			function () {
				return true;
			}
		);

		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/outbox' );
		// No user_id param set.

		$result = $this->instance->verify_authentication( $request );

		$this->assertTrue( $result );
	}

	/**
	 * Test WordPress authenticated user matches user_id.
	 *
	 * @covers ::verify_owner
	 */
	public function test_verify_owner_wp_user_matches() {
		\wp_set_current_user( $this->user_id );

		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/users/' . $this->user_id . '/outbox' );
		$request->set_param( 'user_id', $this->user_id );

		$result = $this->instance->verify_owner( $request );

		$this->assertTrue( $result );
	}

	/**
	 * Test mismatched user returns WP_Error with 403.
	 *
	 * @covers ::verify_owner
	 */
	public function test_verify_owner_mismatch() {
		$other_user = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		\wp_set_current_user( $other_user );

		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/users/' . $this->user_id . '/outbox' );
		$request->set_param( 'user_id', $this->user_id );

		$result = $this->instance->verify_owner( $request );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_forbidden', $result->get_error_code() );
		$this->assertEquals( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Test invalid user_id returns WP_Error.
	 *
	 * @covers ::verify_owner
	 */
	public function test_verify_owner_invalid_user_id() {
		\wp_set_current_user( $this->user_id );

		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/users/99999/outbox' );
		$request->set_param( 'user_id', 99999 );

		$result = $this->instance->verify_owner( $request );

		$this->assertWPError( $result );
	}

	/**
	 * Test OAuth token user matches user_id.
	 *
	 * @covers ::verify_owner
	 */
	public function test_verify_owner_oauth_token_matches() {
		$this->set_oauth_token( $this->create_mock_token( $this->user_id, true ) );
		// OAuth Server sets current user during authentication.
		\wp_set_current_user( $this->user_id );

		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/users/' . $this->user_id . '/outbox' );
		$request->set_param( 'user_id', $this->user_id );

		$result = $this->instance->verify_owner( $request );

		$this->assertTrue( $result );
	}

	/**
	 * Data provider for verify_key_id tests.
	 *
	 * @return array[] Test cases: [ signature_header, actor, expected_pass ].
	 */
	public function data_verify_key_id() {
		return array(
			'matching hosts'          => array(
				'keyId="https://remote.example/users/alice#main-key",algorithm="rsa-sha256",signature="abc"',
				'https://remote.example/users/alice',
				true,
			),
			'mismatched hosts'        => array(
				'keyId="https://evil.example/users/alice#main-key",algorithm="rsa-sha256",signature="abc"',
				'https://remote.example/users/alice',
				false,
			),
			'no actor in body'        => array(
				'keyId="https://remote.example/users/alice#main-key",algorithm="rsa-sha256",signature="abc"',
				null,
				true,
			),
			'no signature header'     => array(
				null,
				'https://remote.example/users/alice',
				true,
			),
			'actor as object with id' => array(
				'keyId="https://remote.example/users/alice#main-key",algorithm="rsa-sha256",signature="abc"',
				array( 'id' => 'https://remote.example/users/alice' ),
				true,
			),
			'actor object mismatch'   => array(
				'keyId="https://evil.example/users/alice#main-key",algorithm="rsa-sha256",signature="abc"',
				array( 'id' => 'https://remote.example/users/alice' ),
				false,
			),
		);
	}

	/**
	 * Test that verify_key_id checks keyId host against actor host.
	 *
	 * @dataProvider data_verify_key_id
	 * @covers ::verify_key_id
	 *
	 * @param string|null       $signature The Signature header value.
	 * @param string|array|null $actor     The actor value in the JSON body.
	 * @param bool              $should_pass Whether the check should pass.
	 */
	public function test_verify_key_id( $signature, $actor, $should_pass ) {
		// Defer actual signature crypto so we only test the keyId-actor check.
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/inbox' );

		if ( null !== $signature ) {
			$request->set_header( 'Signature', $signature );
		}

		$body = array(
			'type' => 'Like',
			'id'   => 'https://remote.example/activity/1',
		);
		if ( null !== $actor ) {
			$body['actor'] = $actor;
		}
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( \wp_json_encode( $body ) );

		/*
		 * verify_key_id is private, so call it via reflection.
		 * This avoids coupling the test to the full verify_signature flow.
		 */
		$method = new \ReflectionMethod( $this->instance, 'verify_key_id' );
		$method->setAccessible( true );
		$result = $method->invoke( $this->instance, $request );

		if ( $should_pass ) {
			$this->assertTrue( $result );
		} else {
			$this->assertWPError( $result );
			$this->assertEquals( 'activitypub_key_actor_mismatch', $result->get_error_code() );
			$this->assertEquals( 403, $result->get_error_data()['status'] );
		}
	}

	/**
	 * Create a mock OAuth token object.
	 *
	 * @param int  $user_id   The user ID the token belongs to.
	 * @param bool $has_scope Whether the token has any scope.
	 * @return object Mock token with get_user_id() and has_scope() methods.
	 */
	private function create_mock_token( $user_id, $has_scope ) {
		return new class( $user_id, $has_scope ) {
			/**
			 * User ID.
			 *
			 * @var int
			 */
			private $user_id;

			/**
			 * Whether the token has scope.
			 *
			 * @var bool
			 */
			private $has_scope;

			/**
			 * Constructor.
			 *
			 * @param int  $user_id   User ID.
			 * @param bool $has_scope Has scope.
			 */
			public function __construct( $user_id, $has_scope ) {
				$this->user_id   = $user_id;
				$this->has_scope = $has_scope;
			}

			/**
			 * Get user ID.
			 *
			 * @return int
			 */
			public function get_user_id() {
				return $this->user_id;
			}

			/**
			 * Check scope.
			 *
			 * @param string $scope Scope to check.
			 * @return bool
			 */
			public function has_scope( $scope ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
				return $this->has_scope;
			}
		};
	}

	/**
	 * Set the OAuth Server's current token via reflection.
	 *
	 * @param object|null $token The token to set.
	 */
	private function set_oauth_token( $token ) {
		$reflection = new \ReflectionClass( OAuth_Server::class );
		$property   = $reflection->getProperty( 'current_token' );
		$property->setAccessible( true );
		$property->setValue( null, $token );
	}
}
