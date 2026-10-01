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

		\update_option( 'activitypub_api', '1' );

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
	 * Test that the 11th registration request within a minute is rate-limited.
	 *
	 * @covers ::register_client
	 */
	public function test_register_client_rate_limited() {
		// An allowance of nothing refuses the first request, so no bucket has to be primed.
		\add_filter( 'activitypub_rate_limit', '__return_zero' );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/clients' );
		$request->set_param( 'client_name', 'Rate Limited App' );
		$request->set_param( 'redirect_uris', array( 'https://limited.example.com/callback' ) );

		// `rest_post_dispatch` runs in `serve_request()`, so the headers are applied here.
		$response = \apply_filters( 'rest_post_dispatch', \rest_get_server()->dispatch( $request ), \rest_get_server(), $request );
		$data     = $response->get_data();
		$headers  = $response->get_headers();

		\remove_filter( 'activitypub_rate_limit', '__return_zero' );

		$this->assertEquals( 429, $response->get_status() );
		$this->assertEquals( 'activitypub_rate_limited', $data['code'] );
		$this->assertArrayHasKey( 'Retry-After', $headers, 'A refusal says when to come back.' );
		$this->assertSame( '0', $headers['RateLimit-Remaining'] );
	}

	/**
	 * Test that registration fails closed when no client IP can be determined.
	 *
	 * The endpoint must reject the request rather than share a single
	 * rate-limit bucket across every unidentifiable caller.
	 *
	 * @covers ::register_client
	 */
	public function test_register_client_fails_closed_without_client_ip() {
		// Snapshot every $_SERVER key get_client_ip walks: any leftover proxy header from another
		// test would otherwise let the endpoint find a valid IP and skip the fail-closed branch.
		$server_keys = array(
			'REMOTE_ADDR',
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
		);
		$snapshot    = array();
		foreach ( $server_keys as $key ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Capturing existing test fixture values for restore.
			$snapshot[ $key ] = \array_key_exists( $key, $_SERVER ) ? $_SERVER[ $key ] : null;
		}

		try {
			foreach ( $server_keys as $key ) {
				unset( $_SERVER[ $key ] );
			}

			$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/clients' );
			$request->set_param( 'client_name', 'No-IP App' );
			$request->set_param( 'redirect_uris', array( 'https://no-ip.example.com/callback' ) );

			$response = \apply_filters( 'rest_post_dispatch', \rest_get_server()->dispatch( $request ), \rest_get_server(), $request );
			$data     = $response->get_data();
			$headers  = $response->get_headers();

			$this->assertEquals( 429, $response->get_status() );
			$this->assertEquals( 'activitypub_rate_limited', $data['code'] );
			$this->assertArrayHasKey( 'Retry-After', $headers, 'A refusal says when to come back.' );
		} finally {
			foreach ( $snapshot as $key => $value ) {
				if ( null === $value ) {
					unset( $_SERVER[ $key ] );
				} else {
					$_SERVER[ $key ] = $value;
				}
			}
		}
	}

	/**
	 * Test that registrations below the limit succeed and increment the counter.
	 *
	 * @covers ::register_client
	 */
	public function test_register_client_spends_the_allowance() {
		// An allowance of one: the first registration succeeds, the second is refused.
		$allowance = function () {
			return 1;
		};
		\add_filter( 'activitypub_rate_limit', $allowance );

		$register = function ( $name ) {
			$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/clients' );
			$request->set_param( 'client_name', $name );
			$request->set_param( 'redirect_uris', array( 'https://counter.example.com/callback' ) );

			return \apply_filters( 'rest_post_dispatch', \rest_get_server()->dispatch( $request ), \rest_get_server(), $request );
		};

		$first  = $register( 'Counter App' );
		$second = $register( 'Counter App Again' );

		\remove_filter( 'activitypub_rate_limit', $allowance );

		$this->assertEquals( 201, $first->get_status() );
		$this->assertSame( '0', $first->get_headers()['RateLimit-Remaining'], 'The first one spends the allowance.' );
		$this->assertEquals( 429, $second->get_status(), 'The second one is refused.' );
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

		// Advertise SWICG ActivityPub API Basic Profile canonical scope aliases.
		$this->assertContains( 'activitypub:read:all', $data['scopes_supported'] );
		$this->assertContains( 'activitypub:write:all', $data['scopes_supported'] );
	}
}
