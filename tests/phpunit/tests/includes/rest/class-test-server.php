<?php
/**
 * Test file for Server.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Rest\Server;

/**
 * Test class for Server.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Server
 */
class Test_Server extends \WP_Test_REST_TestCase {

	/**
	 * Test init method.
	 *
	 * @covers ::init
	 */
	public function test_init() {
		// Ensure hooks are not already added.
		$this->assertFalse( \has_filter( 'rest_request_before_callbacks', array( Server::class, 'validate_requests' ) ) );
		$this->assertFalse( \has_filter( 'rest_request_parameter_order', array( Server::class, 'request_parameter_order' ) ) );
		$this->assertFalse( \has_filter( 'rest_post_dispatch', array( Server::class, 'filter_output' ) ) );

		Server::init();

		// Verify hooks are added.
		$this->assertEquals( 9, \has_filter( 'rest_request_before_callbacks', array( Server::class, 'validate_requests' ) ) );
		$this->assertEquals( 10, \has_filter( 'rest_request_parameter_order', array( Server::class, 'request_parameter_order' ) ) );
		$this->assertEquals( 10, \has_filter( 'rest_post_dispatch', array( Server::class, 'filter_output' ) ) );
		$this->assertEquals( 10, \has_filter( 'rest_post_dispatch', array( Server::class, 'add_cors_headers' ) ) );
		$this->assertEquals( 10, \has_filter( 'rest_allowed_cors_headers', array( Server::class, 'allow_cors_headers' ) ) );
		$this->assertEquals( 10, \has_filter( 'rest_pre_dispatch', array( Server::class, 'maybe_add_actor_from_signature' ) ) );
		$this->assertEquals( 1, \has_filter( 'rest_pre_dispatch', array( Server::class, 'normalize_route' ) ), 'The route has to be normalized before any other callback reads it.' );
	}

	/**
	 * Build an actor-less, signed inbox request for the actor-backfill tests.
	 *
	 * @param array  $body   The activity body.
	 * @param string $key_id The keyId to advertise via the HTTP Signature header.
	 * @param string $route  Optional. The request route. Default the shared inbox.
	 * @return \WP_REST_Request The prepared request.
	 */
	private function build_signed_inbox_request( $body, $key_id, $route = null ) {
		$route   = $route ?? '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox';
		$request = new \WP_REST_Request( 'POST', $route );
		$request->set_header( 'content-type', 'application/activity+json' );
		$request->set_header( 'signature', \sprintf( 'keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="abc"', $key_id ) );
		$request->set_body( \wp_json_encode( $body ) );

		return $request;
	}

	/**
	 * An actor-less FeatureRequest gets its actor backfilled from the signature keyId,
	 * without altering the raw body (so the signed Digest still verifies).
	 *
	 * @covers ::maybe_add_actor_from_signature
	 */
	public function test_maybe_add_actor_from_signature_backfills_missing_actor() {
		Server::init(); // Inbox POSTs read JSON params first; that order makes set_param() land in JSON.

		$body    = array(
			'id'         => 'https://remote.example.com/activities/feat-1',
			'type'       => 'FeatureRequest',
			'object'     => 'https://example.org/author/1',
			'instrument' => 'https://remote.example.com/users/curator/featured',
		);
		$request = $this->build_signed_inbox_request( $body, 'https://remote.example.com/users/curator#main-key' );

		$original_body = $request->get_body();
		$result        = Server::maybe_add_actor_from_signature( null, new \WP_REST_Server(), $request );

		$this->assertNull( $result, 'The filter must not hijack the request.' );
		$this->assertSame(
			'https://remote.example.com/users/curator',
			$request->get_json_params()['actor'],
			'Actor should be derived from the keyId with the fragment stripped.'
		);
		$this->assertSame( $original_body, $request->get_body(), 'The raw body must be untouched so the signed Digest still verifies.' );
	}

	/**
	 * The actor is also derived from an RFC 9421 Signature-Input keyid.
	 *
	 * @covers ::maybe_add_actor_from_signature
	 */
	public function test_maybe_add_actor_from_signature_supports_signature_input() {
		Server::init();

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'content-type', 'application/activity+json' );
		$request->set_header( 'signature-input', 'sig1=("@method" "@target-uri");keyid="https://remote.example.com/users/curator#main-key";created=1700000000' );
		$request->set_body( \wp_json_encode( array( 'type' => 'FeatureRequest' ) ) );

		Server::maybe_add_actor_from_signature( null, new \WP_REST_Server(), $request );

		$this->assertSame( 'https://remote.example.com/users/curator', $request->get_json_params()['actor'] );
	}

	/**
	 * An activity that already carries an actor is left untouched.
	 *
	 * @covers ::maybe_add_actor_from_signature
	 */
	public function test_maybe_add_actor_from_signature_keeps_existing_actor() {
		$request = $this->build_signed_inbox_request(
			array(
				'type'  => 'FeatureRequest',
				'actor' => 'https://remote.example.com/users/someone-else',
			),
			'https://remote.example.com/users/curator#main-key'
		);

		Server::maybe_add_actor_from_signature( null, new \WP_REST_Server(), $request );

		$this->assertSame( 'https://remote.example.com/users/someone-else', $request->get_json_params()['actor'] );
	}

	/**
	 * Only FeatureRequest activities are backfilled; other actor-less types are left alone.
	 *
	 * @covers ::maybe_add_actor_from_signature
	 */
	public function test_maybe_add_actor_from_signature_skips_other_activity_types() {
		$request = $this->build_signed_inbox_request(
			array( 'type' => 'Follow' ),
			'https://remote.example.com/users/curator#main-key'
		);

		Server::maybe_add_actor_from_signature( null, new \WP_REST_Server(), $request );

		$this->assertArrayNotHasKey( 'actor', $request->get_json_params() );
	}

	/**
	 * Non-inbox routes must never be touched.
	 *
	 * @covers ::maybe_add_actor_from_signature
	 */
	public function test_maybe_add_actor_from_signature_skips_non_inbox_route() {
		$request = $this->build_signed_inbox_request(
			array( 'type' => 'FeatureRequest' ),
			'https://remote.example.com/users/curator#main-key',
			'/' . ACTIVITYPUB_REST_NAMESPACE . '/outbox'
		);

		Server::maybe_add_actor_from_signature( null, new \WP_REST_Server(), $request );

		$this->assertArrayNotHasKey( 'actor', $request->get_json_params() );
	}

	/**
	 * Without a signature there is no authoritative actor to derive, so nothing changes.
	 *
	 * @covers ::maybe_add_actor_from_signature
	 */
	public function test_maybe_add_actor_from_signature_skips_without_signature() {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'content-type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( array( 'type' => 'FeatureRequest' ) ) );

		Server::maybe_add_actor_from_signature( null, new \WP_REST_Server(), $request );

		$this->assertArrayNotHasKey( 'actor', $request->get_json_params() );
	}

	/**
	 * A non-POST request is ignored.
	 *
	 * @covers ::maybe_add_actor_from_signature
	 */
	public function test_maybe_add_actor_from_signature_skips_non_post() {
		$request = $this->build_signed_inbox_request(
			array( 'type' => 'FeatureRequest' ),
			'https://remote.example.com/users/curator#main-key'
		);
		$request->set_method( 'GET' );

		Server::maybe_add_actor_from_signature( null, new \WP_REST_Server(), $request );

		$this->assertArrayNotHasKey( 'actor', $request->get_json_params() );
	}

	/**
	 * A spoofed draft Signature header must not win over the RFC 9421 keyId the request is
	 * actually verified with. The verifier uses Signature-Input when present, so the injected
	 * actor must be derived from there, not from the (ignored) draft Signature header.
	 *
	 * @covers ::maybe_add_actor_from_signature
	 */
	public function test_maybe_add_actor_from_signature_uses_verified_keyid_over_spoofed_header() {
		Server::init();

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'content-type', 'application/activity+json' );
		$request->set_header( 'signature', 'keyId="https://victim.example/users/victim#main-key",signature="abc"' );
		$request->set_header( 'signature-input', 'sig1=("@method");keyid="https://attacker.example/users/attacker#main-key";created=1700000000' );
		$request->set_body( \wp_json_encode( array( 'type' => 'FeatureRequest' ) ) );

		Server::maybe_add_actor_from_signature( null, new \WP_REST_Server(), $request );

		$this->assertSame( 'https://attacker.example/users/attacker', $request->get_json_params()['actor'] );
	}

	/**
	 * When the signature carries more than one keyId the verifier may validate any label,
	 * so we cannot know which key will verify. The actor must not be backfilled.
	 *
	 * @covers ::maybe_add_actor_from_signature
	 */
	public function test_maybe_add_actor_from_signature_skips_ambiguous_keyids() {
		Server::init();

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'content-type', 'application/activity+json' );
		$request->set_header( 'signature-input', 'sig1=("@method");keyid="https://victim.example/users/victim#main-key", sig2=("@method");keyid="https://attacker.example/users/attacker#main-key";created=1700000000' );
		$request->set_body( \wp_json_encode( array( 'type' => 'FeatureRequest' ) ) );

		Server::maybe_add_actor_from_signature( null, new \WP_REST_Server(), $request );

		$this->assertArrayNotHasKey( 'actor', $request->get_json_params() );
	}

	/**
	 * A prior short-circuit result must be respected and left intact.
	 *
	 * @covers ::maybe_add_actor_from_signature
	 */
	public function test_maybe_add_actor_from_signature_respects_short_circuit() {
		$request = $this->build_signed_inbox_request(
			array( 'type' => 'FeatureRequest' ),
			'https://remote.example.com/users/curator#main-key'
		);

		$response = new \WP_REST_Response( array( 'handled' => true ) );
		$result   = Server::maybe_add_actor_from_signature( $response, new \WP_REST_Server(), $request );

		$this->assertSame( $response, $result );
		$this->assertArrayNotHasKey( 'actor', $request->get_json_params() );
	}

	/**
	 * The Allow-Headers filter must extend core's defaults with the headers
	 * ActivityPub clients use, scoped to ActivityPub routes only.
	 *
	 * @covers ::allow_cors_headers
	 */
	public function test_allow_cors_headers_extends_for_activitypub_routes() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$result  = Server::allow_cors_headers( array( 'Authorization', 'X-WP-Nonce', 'Content-Type' ), $request );

		$this->assertContains( 'X-WP-Nonce', $result );
		$this->assertContains( 'Accept', $result );
		$this->assertContains( 'Last-Event-ID', $result );
	}

	/**
	 * Non-ActivityPub routes must keep core's Allow-Headers untouched.
	 *
	 * @covers ::allow_cors_headers
	 */
	public function test_allow_cors_headers_skips_non_activitypub_routes() {
		$defaults = array( 'Authorization', 'X-WP-Nonce', 'Content-Type' );
		$request  = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
		$result   = Server::allow_cors_headers( $defaults, $request );

		$this->assertSame( $defaults, $result );
	}

	/**
	 * The interactive OAuth authorize endpoint must not advertise CORS.
	 *
	 * @covers ::allow_cors_headers
	 */
	public function test_allow_cors_headers_skips_oauth_authorize() {
		$defaults = array( 'Authorization', 'X-WP-Nonce', 'Content-Type' );
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/authorize' );
		$result   = Server::allow_cors_headers( $defaults, $request );

		$this->assertSame( $defaults, $result );
	}

	/**
	 * Data provider for validate_requests scenarios that return response unchanged.
	 *
	 * @return array[]
	 */
	public function validate_requests_passthrough_provider() {
		return array(
			'HEAD request'          => array(
				'HEAD',
				'/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox',
				'test_response',
				null,
				null,
			),
			'WP_Error response'     => array(
				'POST',
				'/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox',
				new \WP_Error( 'test_error', 'Test error message' ),
				null,
				null,
			),
			'non-ActivityPub route' => array(
				'POST',
				'/wp/v2/posts',
				'test_response',
				null,
				null,
			),
			'no type parameter'     => array(
				'POST',
				'/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox',
				'test_response',
				'application/json',
				array( 'actor' => 'test' ),
			),
			'allowed activity type' => array(
				'POST',
				'/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox',
				'test_response',
				'application/json',
				array( 'type' => 'Follow' ),
			),
		);
	}

	/**
	 * Test validate_requests method scenarios that return response unchanged.
	 *
	 * @dataProvider validate_requests_passthrough_provider
	 * @covers ::validate_requests
	 *
	 * @param string      $method HTTP method.
	 * @param string      $route Request route.
	 * @param mixed       $response Response to validate.
	 * @param string|null $content_type Content type header.
	 * @param array|null  $body_data Request body data.
	 */
	public function test_validate_requests_passthrough_scenarios( $method, $route, $response, $content_type, $body_data ) {
		$handler = array();
		$request = new \WP_REST_Request( $method, $route );

		if ( $content_type ) {
			$request->set_header( 'content-type', $content_type );
		}

		if ( $body_data ) {
			$request->set_body( wp_json_encode( $body_data ) );
		}

		$result = Server::validate_requests( $response, $handler, $request );
		$this->assertEquals( $response, $result );
	}

	/**
	 * Data provider for request_parameter_order scenarios.
	 *
	 * @return array
	 */
	public function request_parameter_order_provider() {
		$default_order = array( 'URL', 'JSON', 'POST', 'defaults' );
		$reordered     = array( 'JSON', 'POST', 'URL', 'defaults' );

		return array(
			'non-ActivityPub route' => array(
				'POST',
				'/wp/v2/posts',
				$default_order,
				$default_order,
			),
			'non-CREATABLE method'  => array(
				'GET',
				'/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox',
				$default_order,
				$default_order,
			),
			'ActivityPub CREATABLE' => array(
				'POST',
				'/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox',
				$default_order,
				$reordered,
			),
		);
	}

	/**
	 * Test request_parameter_order method with different scenarios.
	 *
	 * @dataProvider request_parameter_order_provider
	 * @covers ::request_parameter_order
	 *
	 * @param string $method HTTP method.
	 * @param string $route Request route.
	 * @param array  $input_order Input parameter order.
	 * @param array  $expected_order Expected output order.
	 */
	public function test_request_parameter_order( $method, $route, $input_order, $expected_order ) {
		$request = new \WP_REST_Request( $method, $route );
		$result  = Server::request_parameter_order( $input_order, $request );
		$this->assertEquals( $expected_order, $result );
	}

	/**
	 * Test filter_output method with non-ActivityPub route.
	 *
	 * @covers ::filter_output
	 */
	public function test_filter_output_non_activitypub_route() {
		$response = new \WP_REST_Response( array( 'test' => 'data' ), 200 );
		$server   = new \WP_REST_Server();
		$request  = new \WP_REST_Request( 'GET', '/wp/v2/posts' );

		$result = Server::filter_output( $response, $server, $request );
		$this->assertEquals( $response, $result );
	}

	/**
	 * Test filter_output method with success status code.
	 *
	 * @covers ::filter_output
	 */
	public function test_filter_output_success_status() {
		$response = new \WP_REST_Response( array( 'test' => 'data' ), 200 );
		$server   = new \WP_REST_Server();
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );

		$result = Server::filter_output( $response, $server, $request );
		$this->assertEquals( $response, $result );
	}

	/**
	 * Test filter_output method with error status code.
	 *
	 * @covers ::filter_output
	 */
	public function test_filter_output_error_status() {
		$response = new \WP_REST_Response(
			array(
				'code'    => 'test_error',
				'message' => 'Test error message',
				'data'    => array( 'status' => 400 ),
			),
			400
		);
		$server   = new \WP_REST_Server();
		$request  = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );

		$result = Server::filter_output( $response, $server, $request );

		$expected_data = array(
			'type'     => 'about:blank',
			'title'    => 'test_error',
			'detail'   => 'Test error message',
			'status'   => 400,
			'metadata' => array(
				'code'    => 'test_error',
				'message' => 'Test error message',
				'data'    => array( 'status' => 400 ),
			),
		);

		$this->assertEquals( $expected_data, $result->get_data() );
		$this->assertEquals( 400, $result->get_status() );
	}

	/**
	 * Test filter_output method with WP_Error in response data.
	 *
	 * @covers ::filter_output
	 */
	public function test_filter_output_wp_error_data() {
		$wp_error = new \WP_Error( 'test_error', 'Test error message', array( 'status' => 500 ) );
		$response = new \WP_REST_Response( $wp_error, 500 );
		$server   = new \WP_REST_Server();
		$request  = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );

		$result = Server::filter_output( $response, $server, $request );

		$data = $result->get_data();
		$this->assertEquals( 'about:blank', $data['type'] );
		$this->assertEquals( 'test_error', $data['title'] );
		$this->assertEquals( 'Test error message', $data['detail'] );
		$this->assertEquals( 500, $data['status'] );
		$this->assertArrayHasKey( 'metadata', $data );
		$this->assertEquals( 500, $result->get_status() );
	}

	/**
	 * Data provider for missing error data scenarios.
	 *
	 * @return array
	 */
	public function missing_error_data_provider() {
		return array(
			'missing code'    => array(
				array( 'message' => 'Test error message' ),
				'',
				'Test error message',
			),
			'missing message' => array(
				array( 'code' => 'test_error' ),
				'test_error',
				'',
			),
		);
	}

	/**
	 * Test filter_output method with missing error data.
	 *
	 * @dataProvider missing_error_data_provider
	 * @covers ::filter_output
	 *
	 * @param array  $response_data The response data.
	 * @param string $expected_title Expected title value.
	 * @param string $expected_detail Expected detail value.
	 */
	public function test_filter_output_missing_error_data( $response_data, $expected_title, $expected_detail ) {
		$response = new \WP_REST_Response( $response_data, 400 );
		$server   = new \WP_REST_Server();
		$request  = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );

		$result = Server::filter_output( $response, $server, $request );

		$data = $result->get_data();
		$this->assertEquals( $expected_title, $data['title'] );
		$this->assertEquals( $expected_detail, $data['detail'] );
	}

	/**
	 * ActivityPub data is publicly readable, so CORS uses a wildcard origin
	 * with no credentials. Reflecting an arbitrary Origin together with
	 * Allow-Credentials would let a logged-in user's browser expose
	 * authenticated responses to a third-party page; we explicitly avoid
	 * that combination on every ActivityPub REST response.
	 *
	 * @covers ::add_cors_headers
	 */
	public function test_add_cors_headers_uses_wildcard_without_credentials() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Test snapshot of raw global; restored verbatim.
		$origin_backup = $_SERVER['HTTP_ORIGIN'] ?? null;

		try {
			$_SERVER['HTTP_ORIGIN'] = 'https://evil.example';

			$response = new \WP_REST_Response( array(), 200 );
			$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );

			$result  = Server::add_cors_headers( $response, new \WP_REST_Server(), $request );
			$headers = $result->get_headers();

			$this->assertSame( '*', $headers['Access-Control-Allow-Origin'] );
			$this->assertArrayNotHasKey( 'Access-Control-Allow-Credentials', $headers );
			$this->assertArrayNotHasKey( 'Vary', $headers );
			// Allow-Headers is contributed via rest_allowed_cors_headers, not set on the response.
			$this->assertArrayNotHasKey( 'Access-Control-Allow-Headers', $headers );
		} finally {
			if ( null === $origin_backup ) {
				unset( $_SERVER['HTTP_ORIGIN'] );
			} else {
				$_SERVER['HTTP_ORIGIN'] = $origin_backup;
			}
		}
	}

	/**
	 * Non-ActivityPub routes must not receive ActivityPub CORS headers.
	 *
	 * @covers ::add_cors_headers
	 */
	public function test_add_cors_headers_skips_non_activitypub_routes() {
		$response = new \WP_REST_Response( array(), 200 );
		$request  = new \WP_REST_Request( 'GET', '/wp/v2/posts' );

		$result  = Server::add_cors_headers( $response, new \WP_REST_Server(), $request );
		$headers = $result->get_headers();

		$this->assertArrayNotHasKey( 'Access-Control-Allow-Origin', $headers );
	}

	/**
	 * The interactive OAuth authorize endpoint must not advertise CORS;
	 * it relies on top-level navigation, not cross-origin XHR.
	 *
	 * @covers ::add_cors_headers
	 */
	public function test_add_cors_headers_skips_oauth_authorize() {
		$response = new \WP_REST_Response( array(), 200 );
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/authorize' );

		$result  = Server::add_cors_headers( $response, new \WP_REST_Server(), $request );
		$headers = $result->get_headers();

		$this->assertArrayNotHasKey( 'Access-Control-Allow-Origin', $headers );
	}

	/**
	 * With Authorized Fetch off the response is the same for every caller, so it varies only by
	 * Authorization (the ActivityPub API token). Varying on the per-request signature headers would
	 * mint a unique cache variant for every signed Mastodon fetch and defeat shared caching.
	 *
	 * @covers ::add_cache_headers
	 */
	public function test_add_cache_headers_sets_vary() {
		$response = new \WP_REST_Response( array(), 200 );
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/1' );

		$headers = Server::add_cache_headers( $response, new \WP_REST_Server(), $request )->get_headers();

		$this->assertSame( 'Authorization', $headers['Vary'] );
		$this->assertArrayNotHasKey( 'Cache-Control', $headers );
	}

	/**
	 * A public route (permission_callback __return_true) returns the same response for every caller,
	 * so even under Authorized Fetch with a signed request it must stay cacheable: no no-store, and no
	 * caller-varying Vary. This keeps public thread-resolution reads (replies, context) edge-cacheable.
	 *
	 * @covers ::add_cache_headers
	 */
	public function test_add_cache_headers_leaves_public_routes_cacheable_under_authorized_fetch() {
		\add_filter( 'activitypub_use_authorized_fetch', '__return_true' );

		$response = new \WP_REST_Response( array(), 200 );
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/posts/1/replies' );
		$request->set_attributes( array( 'permission_callback' => '__return_true' ) );
		$request->set_header( 'Signature', 'keyId="https://remote.example/users/alice#main-key"' );

		$headers = Server::add_cache_headers( $response, new \WP_REST_Server(), $request )->get_headers();

		\remove_filter( 'activitypub_use_authorized_fetch', '__return_true' );

		$this->assertArrayNotHasKey( 'Cache-Control', $headers );
		$this->assertArrayNotHasKey( 'Vary', $headers );
	}

	/**
	 * With Authorized Fetch on the response depends on the signing key, so the signature headers
	 * join the Vary list.
	 *
	 * @covers ::add_cache_headers
	 */
	public function test_add_cache_headers_varies_on_signature_with_authorized_fetch() {
		\add_filter( 'activitypub_use_authorized_fetch', '__return_true' );

		$response = new \WP_REST_Response( array(), 200 );
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/1' );

		$headers = Server::add_cache_headers( $response, new \WP_REST_Server(), $request )->get_headers();

		\remove_filter( 'activitypub_use_authorized_fetch', '__return_true' );

		$this->assertSame( 'Authorization, Signature, Signature-Input', $headers['Vary'] );
	}

	/**
	 * Test that a Vary header already on the response is kept.
	 *
	 * @covers ::add_cache_headers
	 */
	public function test_add_cache_headers_preserves_existing_vary() {
		$response = new \WP_REST_Response( array(), 200 );
		$response->header( 'Vary', 'Accept' );
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/1' );

		$headers = Server::add_cache_headers( $response, new \WP_REST_Server(), $request )->get_headers();

		$this->assertSame( 'Accept, Authorization', $headers['Vary'] );
	}

	/**
	 * Test that a response built for a credentialed caller is marked private.
	 *
	 * @dataProvider credential_header_provider
	 * @covers ::add_cache_headers
	 *
	 * @param string $header The credential header name.
	 * @param string $value  The credential header value.
	 */
	public function test_add_cache_headers_marks_credentialed_responses_private( $header, $value ) {
		\add_filter( 'activitypub_use_authorized_fetch', '__return_true' );

		$response = new \WP_REST_Response( array(), 200 );
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/1/inbox' );
		$request->set_header( $header, $value );

		$headers = Server::add_cache_headers( $response, new \WP_REST_Server(), $request )->get_headers();

		\remove_filter( 'activitypub_use_authorized_fetch', '__return_true' );

		$this->assertSame( 'private, no-store, max-age=0', $headers['Cache-Control'] );
	}

	/**
	 * With Authorized Fetch off, a signed request gets the same public response as everyone else,
	 * so it must not be marked no-store, or it would needlessly drop from every CDN.
	 *
	 * @dataProvider credential_header_provider
	 * @covers ::add_cache_headers
	 *
	 * @param string $header The credential header name.
	 * @param string $value  The credential header value.
	 */
	public function test_add_cache_headers_keeps_credentialed_responses_cacheable_without_authorized_fetch( $header, $value ) {
		$response = new \WP_REST_Response( array(), 200 );
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/1/followers' );
		$request->set_header( $header, $value );

		$headers = Server::add_cache_headers( $response, new \WP_REST_Server(), $request )->get_headers();

		$this->assertArrayNotHasKey( 'Cache-Control', $headers );
	}

	/**
	 * An owner authenticated by WP session (verify_owner) can receive private items in an otherwise
	 * public collection, and the session cookie is not in Vary, so a logged-in response must never be
	 * shared, whatever the Authorized Fetch setting.
	 *
	 * @covers ::add_cache_headers
	 */
	public function test_add_cache_headers_marks_session_authenticated_responses_private() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\wp_set_current_user( $user_id );

		$response = new \WP_REST_Response( array(), 200 );
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/followers' );

		$headers = Server::add_cache_headers( $response, new \WP_REST_Server(), $request )->get_headers();

		\wp_set_current_user( 0 );

		$this->assertSame( 'private, no-store, max-age=0', $headers['Cache-Control'] );
	}

	/**
	 * A route whose permission callback is __return_true can still be personalized for a logged-in
	 * user (e.g. /interactions redirects by the current user), so a logged-in request must be marked
	 * private even there. The session check has to run before the public shortcut.
	 *
	 * @covers ::add_cache_headers
	 */
	public function test_add_cache_headers_marks_logged_in_public_route_private() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\wp_set_current_user( $user_id );

		$response = new \WP_REST_Response( array(), 200 );
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/interactions' );
		$request->set_attributes( array( 'permission_callback' => '__return_true' ) );

		$headers = Server::add_cache_headers( $response, new \WP_REST_Server(), $request )->get_headers();

		\wp_set_current_user( 0 );

		$this->assertSame( 'private, no-store, max-age=0', $headers['Cache-Control'] );
	}

	/**
	 * Data provider for credential headers.
	 *
	 * @return array[] Test parameters.
	 */
	public function credential_header_provider() {
		return array(
			'bearer token'   => array( 'Authorization', 'Bearer abc123' ),
			'draft envelope' => array( 'Signature', 'keyId="https://remote.example/users/alice#main-key"' ),
			'rfc 9421'       => array( 'Signature-Input', 'sig1=("@method");keyid="https://remote.example/users/alice#main-key"' ),
		);
	}

	/**
	 * Test that non-ActivityPub routes are left alone.
	 *
	 * @covers ::add_cache_headers
	 */
	public function test_add_cache_headers_skips_other_namespaces() {
		$response = new \WP_REST_Response( array(), 200 );
		$request  = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_header( 'Authorization', 'Bearer abc123' );

		$headers = Server::add_cache_headers( $response, new \WP_REST_Server(), $request )->get_headers();

		$this->assertArrayNotHasKey( 'Vary', $headers );
		$this->assertArrayNotHasKey( 'Cache-Control', $headers );
	}

	/*
	 * WordPress dispatches REST routes with a case-insensitive pattern and leaves
	 * `WP_REST_Request::get_route()` spelled the way the caller sent it, so `/ActivityPub/1.0/inbox`
	 * reaches the same handler as `/activitypub/1.0/inbox`. The route is normalized once before
	 * dispatch so that every route check downstream engages on both spellings.
	 */

	/**
	 * Run the pre-dispatch filters over a request, the way the REST server does before matching it.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Request The same request, after the pre-dispatch filters have seen it.
	 */
	private function pre_dispatch( $request ) {
		Server::init();
		\apply_filters( 'rest_pre_dispatch', null, \rest_get_server(), $request );

		return $request;
	}

	/**
	 * A case-varied ActivityPub route is normalized before it is matched to a handler.
	 *
	 * @covers ::normalize_route
	 */
	public function test_normalize_route_lowercases_activitypub_routes() {
		$request = $this->pre_dispatch( new \WP_REST_Request( 'GET', '/' . \strtoupper( ACTIVITYPUB_REST_NAMESPACE ) . '/Inbox' ) );

		$this->assertSame( '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox', $request->get_route() );
	}

	/**
	 * Routes outside the ActivityPub namespace belong to other endpoints, which may well treat their
	 * own path segments as case-sensitive, so they are left exactly as they arrived.
	 *
	 * @covers ::normalize_route
	 */
	public function test_normalize_route_leaves_other_namespaces_alone() {
		$request = $this->pre_dispatch( new \WP_REST_Request( 'GET', '/wp/v2/Posts' ) );

		$this->assertSame( '/wp/v2/Posts', $request->get_route() );
	}

	/**
	 * An earlier callback that hijacked the request keeps its result, and the route is left alone.
	 *
	 * @covers ::normalize_route
	 */
	public function test_normalize_route_respects_short_circuit() {
		$request  = new \WP_REST_Request( 'GET', '/' . \strtoupper( ACTIVITYPUB_REST_NAMESPACE ) . '/inbox' );
		$response = new \WP_REST_Response( array( 'hijacked' => true ), 200 );

		$result = Server::normalize_route( $response, new \WP_REST_Server(), $request );

		$this->assertSame( $response, $result );
		$this->assertSame( '/' . \strtoupper( ACTIVITYPUB_REST_NAMESPACE ) . '/inbox', $request->get_route() );
	}

	/**
	 * The filter only normalizes; it must never stand in for the response.
	 *
	 * @covers ::normalize_route
	 */
	public function test_normalize_route_does_not_hijack_the_request() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );

		$this->assertNull( Server::normalize_route( null, new \WP_REST_Server(), $request ) );
	}

	/**
	 * A case-varied authorize route is the same interactive endpoint, so the CORS exclusion applies.
	 *
	 * @covers ::normalize_route
	 * @covers ::add_cors_headers
	 */
	public function test_case_varied_oauth_authorize_gets_no_cors() {
		$request  = $this->pre_dispatch( new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/oauth/Authorize' ) );
		$response = new \WP_REST_Response( array(), 200 );

		$headers = Server::add_cors_headers( $response, \rest_get_server(), $request )->get_headers();

		$this->assertArrayNotHasKey( 'Access-Control-Allow-Origin', $headers );
		$this->assertSame( array( 'Authorization' ), Server::allow_cors_headers( array( 'Authorization' ), $request ) );
	}

	/**
	 * A case-varied namespace still reaches an ActivityPub handler, so it still gets CORS.
	 *
	 * @covers ::normalize_route
	 * @covers ::add_cors_headers
	 */
	public function test_case_varied_namespace_gets_cors() {
		$request  = $this->pre_dispatch( new \WP_REST_Request( 'GET', '/' . \strtoupper( ACTIVITYPUB_REST_NAMESPACE ) . '/inbox' ) );
		$response = new \WP_REST_Response( array(), 200 );

		$headers = Server::add_cors_headers( $response, \rest_get_server(), $request )->get_headers();

		$this->assertSame( '*', $headers['Access-Control-Allow-Origin'] );
		$this->assertContains( 'Accept', Server::allow_cors_headers( array( 'Authorization' ), $request ) );
	}

	/**
	 * A token-gated response served under a case-varied namespace must still be marked as varying by
	 * Authorization, or a shared cache could hand one caller's response to the next.
	 *
	 * @covers ::normalize_route
	 * @covers ::add_cache_headers
	 */
	public function test_case_varied_namespace_still_varies_on_authorization() {
		$request  = $this->pre_dispatch( new \WP_REST_Request( 'GET', '/' . \strtoupper( ACTIVITYPUB_REST_NAMESPACE ) . '/actors/1' ) );
		$response = new \WP_REST_Response( array(), 200 );

		$headers = Server::add_cache_headers( $response, \rest_get_server(), $request )->get_headers();

		$this->assertSame( 'Authorization', $headers['Vary'] );
	}

	/**
	 * Inbox POSTs must read their JSON body first whatever the spelling of the route, because
	 * `application/activity+json` is not in core's default parameter order.
	 *
	 * @covers ::normalize_route
	 * @covers ::request_parameter_order
	 */
	public function test_case_varied_namespace_keeps_json_parameter_order() {
		$request = $this->pre_dispatch( new \WP_REST_Request( 'POST', '/' . \strtoupper( ACTIVITYPUB_REST_NAMESPACE ) . '/inbox' ) );
		$result  = Server::request_parameter_order( array( 'URL', 'JSON', 'POST', 'defaults' ), $request );

		$this->assertSame( array( 'JSON', 'POST', 'URL', 'defaults' ), $result );
	}

	/**
	 * Errors from a case-varied ActivityPub route still get the FEP-c180 error shape, and case-varied
	 * OAuth routes still keep the RFC 6749 one.
	 *
	 * @covers ::normalize_route
	 * @covers ::filter_output
	 */
	public function test_case_varied_namespace_keeps_error_formats() {
		$activitypub = $this->pre_dispatch( new \WP_REST_Request( 'POST', '/' . \strtoupper( ACTIVITYPUB_REST_NAMESPACE ) . '/inbox' ) );
		$oauth       = $this->pre_dispatch( new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/OAuth/token' ) );

		$formatted = Server::filter_output( new \WP_REST_Response( array( 'code' => 'test_error' ), 400 ), \rest_get_server(), $activitypub );
		$untouched = Server::filter_output( new \WP_REST_Response( array( 'error' => 'invalid_grant' ), 400 ), \rest_get_server(), $oauth );

		$this->assertSame( 'about:blank', $formatted->get_data()['type'] );
		$this->assertSame( array( 'error' => 'invalid_grant' ), $untouched->get_data() );
	}

	/**
	 * The actor backfill keys off an `/inbox` suffix, which is just as case-varied as the namespace.
	 *
	 * @covers ::normalize_route
	 * @covers ::maybe_add_actor_from_signature
	 */
	public function test_case_varied_route_still_backfills_actor() {
		$request = $this->pre_dispatch(
			$this->build_signed_inbox_request(
				array( 'type' => 'FeatureRequest' ),
				'https://remote.example.com/users/curator#main-key',
				'/' . \strtoupper( ACTIVITYPUB_REST_NAMESPACE ) . '/Inbox'
			)
		);

		$this->assertSame( 'https://remote.example.com/users/curator', $request->get_json_params()['actor'] );
	}
}
