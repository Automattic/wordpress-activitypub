<?php
/**
 * Test Proxy Controller.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Remote_Actors;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server as OAuth_Server;
use Activitypub\Rest\Proxy_Controller;

/**
 * Test class for Proxy_Controller.
 *
 * @coversDefaultClass \Activitypub\Rest\Proxy_Controller
 */
class Test_Proxy_Controller extends \WP_UnitTestCase {

	/**
	 * REST Server instance.
	 *
	 * @var \WP_REST_Server
	 */
	protected $server;

	/**
	 * User ID for testing.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Set up test resources.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Set up test fixtures.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		\do_action( 'rest_api_init' );
	}

	/**
	 * Clean up test resources.
	 */
	public static function tear_down_after_class() {
		\wp_delete_user( self::$user_id );
		parent::tear_down_after_class();
	}

	/**
	 * Test that the proxy route is registered.
	 *
	 * @covers ::register_routes
	 */
	public function test_route_registered() {
		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy', $routes );
	}

	/**
	 * Test that proxy rejects non-HTTPS URLs.
	 *
	 * @covers ::validate_url
	 */
	public function test_http_url_rejected() {
		// Mock OAuth authentication.
		$this->mock_oauth_auth();

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'http://example.com/users/test' ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );

		$this->unmock_oauth_auth();
	}

	/**
	 * Test that proxy rejects private network URLs.
	 *
	 * Uses wp_http_validate_url() which blocks private IP ranges.
	 *
	 * @covers ::validate_url
	 */
	public function test_private_network_rejected() {
		// Mock OAuth authentication.
		$this->mock_oauth_auth();

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'https://192.168.1.1/users/test' ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );

		$this->unmock_oauth_auth();
	}

	/**
	 * Test proxy requires authentication.
	 *
	 * @covers ::verify_authentication
	 */
	public function test_requires_authentication() {
		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'https://example.com/users/test' ) );

		$response = $this->server->dispatch( $request );

		// Should fail with 401 or similar since no OAuth token is provided.
		$this->assertNotEquals( 200, $response->get_status() );
	}

	/**
	 * Test successful proxy fetch of an actor.
	 *
	 * @covers ::create_item
	 */
	public function test_successful_actor_fetch() {
		// Mock OAuth authentication.
		$this->mock_oauth_auth();

		// Mock the HTTP response.
		$actor_data = array(
			'@context'          => 'https://www.w3.org/ns/activitystreams',
			'type'              => 'Person',
			'id'                => 'https://example.com/users/test',
			'inbox'             => 'https://example.com/users/test/inbox',
			'preferredUsername' => 'test',
			'name'              => 'Test User',
		);

		\add_filter(
			'pre_http_request',
			function () use ( $actor_data ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => \wp_json_encode( $actor_data ),
					'headers'  => array( 'content-type' => 'application/activity+json' ),
				);
			}
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'https://example.com/users/test' ) );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'Person', $data['type'] );
		$this->assertEquals( 'https://example.com/users/test', $data['id'] );

		// The canonical actor was cached under its own id.
		$this->assertInstanceOf( 'WP_Post', Remote_Actors::get_by_uri( 'https://example.com/users/test' ) );

		$this->unmock_oauth_auth();
	}

	/**
	 * A proxied actor whose declared id is not the fetched URL must not be cached
	 * under that id, even though the object is still returned to the caller.
	 *
	 * @covers ::create_item
	 */
	public function test_proxy_does_not_cache_cross_host_actor_id() {
		$this->mock_oauth_auth();

		$fetched_url   = 'https://example.com/proxy-me';
		$mismatched_id = 'https://example.org/users/alice';

		$documents = array(
			// Served at $fetched_url, claims the canonical actor's id on another host.
			$fetched_url   => array(
				'@context'          => 'https://www.w3.org/ns/activitystreams',
				'type'              => 'Person',
				'id'                => $mismatched_id,
				'inbox'             => 'https://example.com/inbox',
				'preferredUsername' => 'alice',
			),
			// The other host's real server does not confirm the mismatched id.
			$mismatched_id => array(
				'@context' => 'https://www.w3.org/ns/activitystreams',
				'type'     => 'Person',
				'id'       => 'https://example.net/someone-else',
				'inbox'    => 'https://example.net/someone-else/inbox',
			),
		);

		$filter = function ( $pre, $args, $url ) use ( $documents ) {
			if ( ! \array_key_exists( $url, $documents ) ) {
				return $pre;
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode( $documents[ $url ] ),
				'headers'  => array( 'content-type' => 'application/activity+json' ),
			);
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => $fetched_url ) );

		$response = $this->server->dispatch( $request );

		\remove_filter( 'pre_http_request', $filter );

		// A document served under a mismatched cross-host id is rejected, not proxied...
		$this->assertEquals( 502, $response->get_status() );
		// ...and the mismatched id must NOT have been written to the actor cache.
		$this->assertWPError( Remote_Actors::get_by_uri( $mismatched_id ), 'A cross-host proxied actor id must not be cached.' );

		$this->unmock_oauth_auth();
	}

	/**
	 * Test that proxy accepts a WebFinger handle (`user@host`) and resolves it.
	 *
	 * @covers ::validate_url
	 * @covers ::sanitize_url
	 * @covers ::create_item
	 */
	public function test_successful_actor_fetch_by_handle() {
		$this->mock_oauth_auth();

		$actor_data = array(
			'@context'          => 'https://www.w3.org/ns/activitystreams',
			'type'              => 'Person',
			'id'                => 'https://example.com/users/test',
			'inbox'             => 'https://example.com/users/test/inbox',
			'preferredUsername' => 'test',
			'name'              => 'Test User',
		);

		$filter = function ( $preempt, $args, $url ) use ( $actor_data ) {
			if ( false !== \strpos( $url, '/.well-known/webfinger' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => \wp_json_encode(
						array(
							'subject' => 'acct:test@example.com',
							'links'   => array(
								array(
									'rel'  => 'self',
									'type' => 'application/activity+json',
									'href' => $actor_data['id'],
								),
							),
						)
					),
					'headers'  => array( 'content-type' => 'application/jrd+json' ),
				);
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode( $actor_data ),
				'headers'  => array( 'content-type' => 'application/activity+json' ),
			);
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'test@example.com' ) );

		$response = $this->server->dispatch( $request );

		\remove_filter( 'pre_http_request', $filter, 10 );
		$this->unmock_oauth_auth();

		$this->assertEquals( 200, $response->get_status(), \wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertEquals( 'Person', $data['type'] );
		$this->assertEquals( 'https://example.com/users/test', $data['id'] );
	}

	/**
	 * Test that proxy accepts an `acct:` URI and resolves it via WebFinger.
	 *
	 * @covers ::validate_url
	 * @covers ::sanitize_url
	 * @covers ::create_item
	 */
	public function test_successful_actor_fetch_by_acct_uri() {
		$this->mock_oauth_auth();

		$actor_data = array(
			'@context'          => 'https://www.w3.org/ns/activitystreams',
			'type'              => 'Person',
			'id'                => 'https://example.com/users/test',
			'inbox'             => 'https://example.com/users/test/inbox',
			'preferredUsername' => 'test',
			'name'              => 'Test User',
		);

		$filter = function ( $preempt, $args, $url ) use ( $actor_data ) {
			if ( false !== \strpos( $url, '/.well-known/webfinger' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => \wp_json_encode(
						array(
							'subject' => 'acct:test@example.com',
							'links'   => array(
								array(
									'rel'  => 'self',
									'type' => 'application/activity+json',
									'href' => $actor_data['id'],
								),
							),
						)
					),
					'headers'  => array( 'content-type' => 'application/jrd+json' ),
				);
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode( $actor_data ),
				'headers'  => array( 'content-type' => 'application/activity+json' ),
			);
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'acct:test@example.com' ) );

		$response = $this->server->dispatch( $request );

		\remove_filter( 'pre_http_request', $filter, 10 );
		$this->unmock_oauth_auth();

		$this->assertEquals( 200, $response->get_status(), \wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertEquals( 'Person', $data['type'] );
		$this->assertEquals( 'https://example.com/users/test', $data['id'] );
	}

	/**
	 * Test that proxy accepts a WebFinger handle with leading `@` and resolves it.
	 *
	 * @covers ::validate_url
	 * @covers ::sanitize_url
	 * @covers ::create_item
	 */
	public function test_successful_actor_fetch_by_handle_with_leading_at() {
		$this->mock_oauth_auth();

		$actor_data = array(
			'@context'          => 'https://www.w3.org/ns/activitystreams',
			'type'              => 'Person',
			'id'                => 'https://example.com/users/test',
			'inbox'             => 'https://example.com/users/test/inbox',
			'preferredUsername' => 'test',
			'name'              => 'Test User',
		);

		$filter = function ( $preempt, $args, $url ) use ( $actor_data ) {
			if ( false !== \strpos( $url, '/.well-known/webfinger' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => \wp_json_encode(
						array(
							'subject' => 'acct:test@example.com',
							'links'   => array(
								array(
									'rel'  => 'self',
									'type' => 'application/activity+json',
									'href' => $actor_data['id'],
								),
							),
						)
					),
					'headers'  => array( 'content-type' => 'application/jrd+json' ),
				);
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode( $actor_data ),
				'headers'  => array( 'content-type' => 'application/activity+json' ),
			);
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => '@test@example.com' ) );

		$response = $this->server->dispatch( $request );

		\remove_filter( 'pre_http_request', $filter, 10 );
		$this->unmock_oauth_auth();

		$this->assertEquals( 200, $response->get_status(), \wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertEquals( 'Person', $data['type'] );
		$this->assertEquals( 'https://example.com/users/test', $data['id'] );
	}

	/**
	 * Test that the stream sub-route also accepts acct identifiers.
	 *
	 * Guards against the `format => 'uri'` schema constraint that previously
	 * rejected handles before the validate_callback ran.
	 *
	 * @covers ::register_routes
	 * @covers ::get_stream
	 */
	public function test_stream_accepts_acct_identifier() {
		$this->mock_oauth_auth();

		$filter = function ( $preempt, $args, $url ) {
			if ( false !== \strpos( $url, '/.well-known/webfinger' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => \wp_json_encode(
						array(
							'subject' => 'acct:test@example.com',
							'links'   => array(
								array(
									'rel'  => 'self',
									'type' => 'application/activity+json',
									'href' => 'https://example.com/users/test',
								),
							),
						)
					),
					'headers'  => array( 'content-type' => 'application/jrd+json' ),
				);
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode(
					array(
						'type' => 'Person',
						'id'   => 'https://example.com/users/test',
					)
				),
				'headers'  => array( 'content-type' => 'application/activity+json' ),
			);
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy/stream' );
		$request->set_query_params( array( 'id' => 'test@example.com' ) );

		$response = $this->server->dispatch( $request );

		\remove_filter( 'pre_http_request', $filter, 10 );
		$this->unmock_oauth_auth();

		// The route accepts the handle, the webfinger lookup resolves, and
		// get_stream() reaches the "no eventStream" branch — returning 404
		// rather than the schema-level `rest_invalid_param` 400 it would
		// have produced with the previous `format => 'uri'` constraint.
		$this->assertEquals( 404, $response->get_status() );
		$this->assertEquals( 'activitypub_no_event_stream', $response->get_data()['code'] );
	}

	/**
	 * Test that proxy still rejects garbage input that is neither URL nor handle.
	 *
	 * @covers ::validate_url
	 */
	public function test_malformed_input_rejected() {
		$this->mock_oauth_auth();

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'not-a-url-or-handle' ) );

		$response = $this->server->dispatch( $request );

		$this->unmock_oauth_auth();

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * Mock OAuth authentication for testing.
	 */
	private function mock_oauth_auth() {
		\add_filter( 'activitypub_oauth_check_permission', '__return_true' );
	}

	/**
	 * Test that the remote status reaches the client.
	 *
	 * A missing object and an unreachable host used to be indistinguishable, so a client could
	 * not tell whether to drop the request or replay it later.
	 *
	 * @covers ::create_item
	 */
	public function test_remote_status_is_passed_on() {
		$this->mock_oauth_auth();

		$respond = function () {
			return array(
				'response' => array( 'code' => 404 ),
				'body'     => '',
				'headers'  => array(),
			);
		};
		\add_filter( 'pre_http_request', $respond );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'https://example.com/gone-forever' ) );

		$this->assertEquals( 404, $this->server->dispatch( $request )->get_status() );

		\remove_filter( 'pre_http_request', $respond );
		$this->unmock_oauth_auth();
	}

	/**
	 * Test that a failure with no response at all is still a gateway error.
	 *
	 * @covers ::create_item
	 */
	public function test_unreachable_host_is_a_gateway_error() {
		$this->mock_oauth_auth();

		$respond = function () {
			return new \WP_Error( 'http_request_failed', 'Connection timed out' );
		};
		\add_filter( 'pre_http_request', $respond );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'https://example.com/unreachable' ) );

		$this->assertEquals( 502, $this->server->dispatch( $request )->get_status() );

		\remove_filter( 'pre_http_request', $respond );
		$this->unmock_oauth_auth();
	}

	/**
	 * Remove OAuth mock.
	 */
	private function unmock_oauth_auth() {
		\remove_filter( 'activitypub_oauth_check_permission', '__return_true' );
	}

	/**
	 * Test that the proxy is authorized as a read, not as a write.
	 *
	 * The endpoint is a POST because the target URL travels in the body, but it only fetches a
	 * remote object. Requiring `write` would mean a read-only client could not use it, and would
	 * make a client that only wants to read ask for permission to post.
	 *
	 * @covers ::register_routes
	 */
	public function test_proxy_requires_the_read_scope() {
		$respond = function () {
			return array(
				'response' => array( 'code' => 404 ),
				'body'     => '',
				'headers'  => array(),
			);
		};
		\add_filter( 'pre_http_request', $respond );

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/proxy' );
		$request->set_body_params( array( 'id' => 'https://example.com/gone-forever' ) );

		// A read-scoped token reaches the handler, which forwards the remote 404.
		$this->set_oauth_scopes( array( Scope::READ ) );
		$this->assertEquals( 404, $this->server->dispatch( $request )->get_status(), 'A read token may use the proxy.' );

		// A token without read does not, even though the request is a POST.
		$this->set_oauth_scopes( array( Scope::WRITE ) );
		$this->assertEquals( 403, $this->server->dispatch( $request )->get_status(), 'Write is not the authority to read a remote object.' );

		$this->set_oauth_scopes( null );
		\remove_filter( 'pre_http_request', $respond );
	}

	/**
	 * Put the OAuth Server into a state as though a token with these scopes authenticated.
	 *
	 * @param array|null $scopes Scopes the token carries, or null for no OAuth session.
	 */
	private function set_oauth_scopes( $scopes ) {
		$token = null;

		if ( null !== $scopes ) {
			$token = new class( $scopes, self::$user_id ) {
				/**
				 * Scopes the token carries.
				 *
				 * @var array
				 */
				private $scopes;

				/**
				 * User ID.
				 *
				 * @var int
				 */
				private $user_id;

				/**
				 * Constructor.
				 *
				 * @param array $scopes  Scopes.
				 * @param int   $user_id User ID.
				 */
				public function __construct( $scopes, $user_id ) {
					$this->scopes  = $scopes;
					$this->user_id = $user_id;
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
				public function has_scope( $scope ) {
					return \in_array( $scope, $this->scopes, true );
				}
			};
		}

		$property = ( new \ReflectionClass( OAuth_Server::class ) )->getProperty( 'current_token' );
		$property->setAccessible( true );
		$property->setValue( null, $token );
	}
}
