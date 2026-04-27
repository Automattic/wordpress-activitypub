<?php
/**
 * Followers REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;

/**
 * Tests for Followers REST API endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Followers_Controller
 */
class Test_Followers_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class() {
		self::factory()->post->create_many(
			25,
			array(
				'post_type'    => Remote_Actors::POST_TYPE,
				'post_content' => \wp_slash(
					\wp_json_encode(
						array(
							'id'                => 'https://example.org/actor/1',
							'type'              => 'Person',
							'preferredUsername' => 'user1',
							'name'              => 'User 1',
						)
					)
				),
				'meta_input'   => array(
					Followers::FOLLOWER_META_KEY => '0',
				),
			)
		);
	}

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/(?:users|actors)\/(?P<user_id>[-]?\d+)/followers', $routes );
	}

	/**
	 * Test schema.
	 *
	 * @covers ::get_item_schema
	 */
	public function test_get_item_schema() {
		$request  = new \WP_REST_Request( 'OPTIONS', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$response = rest_get_server()->dispatch( $request )->get_data();

		$this->assertArrayHasKey( 'schema', $response );
		$schema = $response['schema'];

		// Test specific property types.
		$this->assertContains( 'array', (array) $schema['properties']['@context']['type'] );
		$this->assertContains( 'object', (array) $schema['properties']['@context']['type'] );
		$this->assertEquals( 'string', $schema['properties']['id']['type'] );
		$this->assertEquals( 'uri', $schema['properties']['id']['format'] );
		$this->assertEquals( 'string', $schema['properties']['generator']['type'] );
		$this->assertEquals( 'uri', $schema['properties']['generator']['format'] );
		$this->assertEquals( 'string', $schema['properties']['actor']['type'] );
		$this->assertEquals( 'uri', $schema['properties']['actor']['format'] );
		$this->assertEquals( 'integer', $schema['properties']['totalItems']['type'] );
		$this->assertEquals( 'string', $schema['properties']['partOf']['type'] );
		$this->assertEquals( 'uri', $schema['properties']['partOf']['format'] );
		$this->assertEquals( 'array', $schema['properties']['orderedItems']['type'] );
	}

	/**
	 * Test get_items response.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items() {
		$actor_mode = \get_option( 'activitypub_actor_mode' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'context', 'simple' );
		$response = rest_get_server()->dispatch( $request );

		\update_option( 'activitypub_actor_mode', $actor_mode );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertStringContainsString( 'application/activity+json', $response->get_headers()['Content-Type'] );

		$data = $response->get_data();

		// Test required properties.
		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'generator', $data );
		$this->assertArrayHasKey( 'totalItems', $data );

		// Test property values.
		$this->assertEquals( 'OrderedCollectionPage', $data['type'] );
		$this->assertStringContainsString( 'wordpress.org', $data['generator'] );
		$this->assertNotEmpty( $data['orderedItems'] );
	}

	/**
	 * Test get_items response with full context.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_full_context() {
		$actor_mode = \get_option( 'activitypub_actor_mode' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'context', 'full' );
		$response = rest_get_server()->dispatch( $request );

		\update_option( 'activitypub_actor_mode', $actor_mode );

		$data = $response->get_data();
		$this->assertIsArray( $data['orderedItems'] );

		// In full context, orderedItems should contain full actor objects.
		foreach ( $data['orderedItems'] as $item ) {
			$this->assertIsArray( $item );
		}
	}

	/**
	 * Test get_items with pagination.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_pagination() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'page', 2 );
		$request->set_param( 'per_page', 10 );
		$response = rest_get_server()->dispatch( $request );

		$data = $response->get_data();

		// Test pagination properties.
		$this->assertArrayHasKey( 'first', $data );
		$this->assertArrayHasKey( 'last', $data );
		$this->assertStringContainsString( 'page=1', $data['first'] );
		$this->assertIsString( $data['last'] );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$request->set_param( 'page', 100 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_post_invalid_page_number', $response, 400 );

		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test get_items with invalid user.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_invalid_user() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/999999/followers' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_invalid_param', $response, 400 );
	}

	/**
	 * Test that the Followers response matches its schema.
	 *
	 * @covers ::get_items
	 * @covers ::get_item_schema
	 */
	public function test_response_matches_schema() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/followers' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$schema   = ( new \Activitypub\Rest\Followers_Controller() )->get_item_schema();

		$valid = \rest_validate_value_from_schema( $data, $schema );
		$this->assertNotWPError( $valid, 'Response failed schema validation: ' . ( \is_wp_error( $valid ) ? $valid->get_error_message() : '' ) );
	}

	/**
	 * Test get_item method.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_item() {
		// Controller does not implement get_item().
	}

	/**
	 * Seed one follower from a given authority against the blog actor.
	 *
	 * @param string $host Remote actor host (bare hostname).
	 * @return int The remote_actors post ID.
	 */
	private function seed_follower_on_host( $host ) {
		$actor_id = 'https://' . $host . '/users/alice';
		$post_id  = self::factory()->post->create(
			array(
				'post_type'    => Remote_Actors::POST_TYPE,
				'guid'         => $actor_id,
				'post_content' => \wp_slash(
					\wp_json_encode(
						array(
							'id'                => $actor_id,
							'type'              => 'Person',
							'preferredUsername' => 'alice',
							'name'              => 'Alice',
							'inbox'             => $actor_id . '/inbox',
						)
					)
				),
				'meta_input'   => array( Followers::FOLLOWER_META_KEY => '0' ),
			)
		);

		return $post_id;
	}

	/**
	 * Data provider for authority values that must be rejected by route validation
	 * before any handler logic runs.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function rejected_authority_provider() {
		return array(
			'loopback_ipv4'              => array( 'https://127.0.0.1' ),
			'rfc1918'                    => array( 'https://10.0.0.1' ),
			'link_local_metadata'        => array( 'https://169.254.169.254' ),
			'unspecified'                => array( 'https://0.0.0.0' ),
			'ipv6_loopback'              => array( 'https://[::1]' ),
			'localhost_name'             => array( 'https://localhost' ),
			'localhost_subdomain'        => array( 'https://api.localhost' ),
			'mdns_local'                 => array( 'https://printer.local' ),
			// FQDN trailing dot is the same host as without the dot.
			'localhost_trailing_dot'     => array( 'https://localhost.' ),
			'mdns_local_trailing_dot'    => array( 'https://printer.local.' ),
			'sub_localhost_trailing_dot' => array( 'https://api.localhost.' ),
			// IPv4-mapped IPv6 literals are accepted by FILTER_FLAG_NO_RES_RANGE on some PHP builds; reject them explicitly.
			'ipv4_mapped_loopback'       => array( 'https://[::ffff:127.0.0.1]' ),
			'ipv4_mapped_rfc1918'        => array( 'https://[::ffff:10.0.0.1]' ),
		);
	}

	/**
	 * Sync requests with an internal-address authority must be rejected at the
	 * route validation stage, before signature verification or handler logic.
	 *
	 * @dataProvider rejected_authority_provider
	 *
	 * @param string $authority The authority value under test.
	 */
	public function test_sync_rejects_internal_authority( $authority ) {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/followers/sync' );
		$request->set_param( 'authority', $authority );
		$request->set_param( 'page', 1 );

		$response = rest_get_server()->dispatch( $request );

		\delete_option( 'activitypub_actor_mode' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * Unsigned anonymous requests to the sync endpoint must be rejected
	 * because the route is not on the unsigned-GET allowlist.
	 *
	 * @covers \Activitypub\Rest\Verification::verify_signature
	 */
	public function test_sync_rejects_unsigned_request() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/followers/sync' );
		$request->set_param( 'authority', 'https://evil.example' );
		$request->set_param( 'page', 1 );

		$response = rest_get_server()->dispatch( $request );

		\delete_option( 'activitypub_actor_mode' );

		$this->assertErrorResponse( 'activitypub_signature_verification', $response, 401 );
	}

	/**
	 * Build a request ready for direct dispatch to `get_partial_followers`.
	 *
	 * The permission_callback on `/followers/sync` forces signature
	 * verification, so these handler-level tests bypass the callback and
	 * call the method directly to cover the authority-match logic without
	 * generating real HTTP signatures.
	 *
	 * @param int    $user_id    The user ID.
	 * @param string $authority  The authority query parameter value.
	 * @param string $signer_url The URI used in the fake Signature keyId.
	 * @return \WP_REST_Request Prepared request.
	 */
	private function build_sync_request( $user_id, $authority, $signer_url ) {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/followers/sync' );
		$request->set_param( 'user_id', $user_id );
		$request->set_param( 'authority', $authority );
		$request->set_param( 'page', 1 );
		$request->set_header( 'Signature', 'keyId="' . $signer_url . '#main-key",algorithm="rsa-sha256",headers="(request-target) host date",signature="x"' );

		return $request;
	}

	/**
	 * A signed peer requesting an authority it does not own must be rejected.
	 *
	 * @covers ::get_partial_followers
	 */
	public function test_sync_rejects_authority_mismatch() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$request    = $this->build_sync_request( $user_id, 'https://evil.example', 'https://other.example/users/bob' );
		$controller = new \Activitypub\Rest\Followers_Controller();
		$response   = $controller->get_partial_followers( $request );

		\delete_option( 'activitypub_actor_mode' );

		$this->assertWPError( $response );
		$this->assertSame( 'activitypub_authority_mismatch', $response->get_error_code() );
		$this->assertSame( 403, $response->get_error_data()['status'] );
	}

	/**
	 * The hide-social-graph setting governs public disclosure, not peer
	 * reconciliation. A properly signed peer whose authority matches still
	 * receives the partial collection even when the owner has hidden the
	 * graph — the peer already has the relationship on its own side.
	 *
	 * @covers ::get_partial_followers
	 */
	public function test_sync_still_syncs_when_social_graph_is_hidden() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		\update_user_option( $user_id, 'activitypub_hide_social_graph', '1' );

		$post_id = $this->seed_follower_on_host( 'peer.example' );
		Followers::add( $user_id, 'https://peer.example/users/alice' );

		$request    = $this->build_sync_request( $user_id, 'https://peer.example', 'https://peer.example/users/peer' );
		$controller = new \Activitypub\Rest\Followers_Controller();
		$response   = $controller->get_partial_followers( $request );

		\wp_delete_post( $post_id, true );
		\delete_user_option( $user_id, 'activitypub_hide_social_graph' );
		\delete_option( 'activitypub_actor_mode' );

		$this->assertNotWPError( $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'orderedItems', $data );
		$this->assertContains( 'https://peer.example/users/alice', $data['orderedItems'] );
	}

	/**
	 * A signed peer whose authority matches and whose target has a public
	 * social graph receives the partial follower collection.
	 *
	 * @covers ::get_partial_followers
	 */
	public function test_sync_returns_items_for_matching_authority() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		\delete_user_option( $user_id, 'activitypub_hide_social_graph' );

		$post_id = $this->seed_follower_on_host( 'peer.example' );
		Followers::add( $user_id, 'https://peer.example/users/alice' );

		$request    = $this->build_sync_request( $user_id, 'https://peer.example', 'https://peer.example/users/peer' );
		$controller = new \Activitypub\Rest\Followers_Controller();
		$response   = $controller->get_partial_followers( $request );

		\wp_delete_post( $post_id, true );
		\delete_option( 'activitypub_actor_mode' );

		$this->assertNotWPError( $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'orderedItems', $data );
		$this->assertContains( 'https://peer.example/users/alice', $data['orderedItems'] );
	}

	/**
	 * The defer-signature filter receives `$force_signature` as a third
	 * argument so hooks can preserve mandatory signing on peer-only
	 * endpoints like `/followers/sync` without touching unrelated requests.
	 *
	 * @covers \Activitypub\Rest\Verification::verify_signature
	 */
	public function test_sync_defer_filter_receives_force_signature_flag() {
		$captured_force = null;
		\add_filter(
			'activitypub_defer_signature_verification',
			static function ( $defer, $request, $force = false ) use ( &$captured_force ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
				$captured_force = $force;
				return $force ? false : true;
			},
			10,
			3
		);

		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/followers/sync' );
		$request->set_param( 'authority', 'https://peer.example' );
		$request->set_param( 'page', 1 );

		$response = rest_get_server()->dispatch( $request );

		\remove_all_filters( 'activitypub_defer_signature_verification' );
		\delete_option( 'activitypub_actor_mode' );

		$this->assertTrue( $captured_force, 'Filter must receive $force_signature = true for /followers/sync.' );
		$this->assertErrorResponse( 'activitypub_signature_verification', $response, 401 );
	}
}
