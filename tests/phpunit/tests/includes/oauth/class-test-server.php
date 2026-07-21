<?php
/**
 * Test file for OAuth Server class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\OAuth;

use Activitypub\OAuth\Client;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server;
use Activitypub\OAuth\Token;
use Activitypub\Post_Types;

/**
 * Test class for OAuth Server.
 *
 * @coversDefaultClass \Activitypub\OAuth\Server
 *
 * @group activitypub
 * @group oauth
 */
class Test_Server extends \WP_UnitTestCase {

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
	 * A valid access token string for the test user.
	 *
	 * @var string
	 */
	protected $access_token;

	/**
	 * Original `rest_route` query var, restored on tear down.
	 *
	 * @var mixed
	 */
	protected $original_rest_route;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		Post_Types::register_oauth_post_types();

		$this->user_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$client          = Client::register(
			array(
				'name'          => 'Test Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
			)
		);
		$this->client_id = $client['client_id'];

		$token              = Token::create( $this->user_id, $this->client_id, array( Scope::READ ) );
		$this->access_token = $token['access_token'];

		global $wp;
		$this->original_rest_route = $wp->query_vars['rest_route'] ?? null;
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		unset( $_SERVER['HTTP_AUTHORIZATION'] );

		global $wp;
		if ( null === $this->original_rest_route ) {
			unset( $wp->query_vars['rest_route'] );
		} else {
			$wp->query_vars['rest_route'] = $this->original_rest_route;
		}

		\wp_set_current_user( 0 );

		if ( $this->client_id ) {
			Client::delete( $this->client_id );
		}

		parent::tear_down();
	}

	/**
	 * Present the test user's bearer token on the request.
	 */
	private function set_bearer_header() {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->access_token;
	}

	/**
	 * Set the REST route the request is dispatching to.
	 *
	 * @param string|null $route The route, or null to clear it (non-REST context).
	 */
	private function set_rest_route( $route ) {
		global $wp;
		if ( null === $route ) {
			unset( $wp->query_vars['rest_route'] );
		} else {
			$wp->query_vars['rest_route'] = $route;
		}
	}

	/**
	 * A valid bearer token must not authenticate core REST routes.
	 *
	 * Regression test: honoring the token on `/wp/v2/*` would grant a full,
	 * unscoped session, letting a `read`-only client write content (CWE-863).
	 *
	 * @covers ::authenticate_oauth
	 */
	public function test_bearer_token_ignored_on_core_rest_route() {
		$this->set_bearer_header();
		$this->set_rest_route( '/wp/v2/posts' );

		$result = Server::authenticate_oauth( null );

		$this->assertNull( $result, 'OAuth must not authenticate core REST routes.' );
		$this->assertFalse( Server::is_oauth_request(), 'No OAuth session should be established.' );
		$this->assertSame( 0, \get_current_user_id(), 'The current user must not be set from the token.' );
	}

	/**
	 * A prior authentication error on a core route must be preserved untouched.
	 *
	 * @covers ::authenticate_oauth
	 */
	public function test_bearer_token_preserves_prior_result_on_core_rest_route() {
		$this->set_bearer_header();
		$this->set_rest_route( '/wp/v2/users/me' );

		$prior  = new \WP_Error( 'some_prior_error', 'Prior error.' );
		$result = Server::authenticate_oauth( $prior );

		$this->assertSame( $prior, $result, 'The incoming result must be returned unchanged.' );
		$this->assertFalse( Server::is_oauth_request() );
	}

	/**
	 * A valid bearer token authenticates the plugin's own REST namespace.
	 *
	 * @covers ::authenticate_oauth
	 */
	public function test_bearer_token_authenticates_activitypub_route() {
		$this->set_bearer_header();
		$this->set_rest_route( '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $this->user_id . '/outbox' );

		$result = Server::authenticate_oauth( null );

		$this->assertTrue( $result, 'OAuth must authenticate ActivityPub routes.' );
		$this->assertTrue( Server::is_oauth_request() );
		$this->assertSame( $this->user_id, \get_current_user_id() );
	}

	/**
	 * A look-alike namespace prefix must not be treated as an ActivityPub route.
	 *
	 * @covers ::authenticate_oauth
	 */
	public function test_bearer_token_ignored_on_lookalike_namespace() {
		$this->set_bearer_header();
		$this->set_rest_route( '/' . ACTIVITYPUB_REST_NAMESPACE . 'evil/steal' );

		$result = Server::authenticate_oauth( null );

		$this->assertNull( $result );
		$this->assertFalse( Server::is_oauth_request() );
		$this->assertSame( 0, \get_current_user_id() );
	}

	/**
	 * Outside REST dispatch (e.g. outbox permalinks) the token still authenticates.
	 *
	 * @covers ::authenticate_oauth
	 */
	public function test_bearer_token_authenticates_non_rest_request() {
		$this->set_bearer_header();
		$this->set_rest_route( null );

		$result = Server::authenticate_oauth( null );

		$this->assertTrue( $result, 'Direct (non-REST) callers must still authenticate.' );
		$this->assertTrue( Server::is_oauth_request() );
		$this->assertSame( $this->user_id, \get_current_user_id() );
	}
}
