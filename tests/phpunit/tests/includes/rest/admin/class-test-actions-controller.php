<?php
/**
 * Test file for Actions_Controller permissions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest\Admin;

use Activitypub\OAuth\Client;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server;
use Activitypub\OAuth\Token;
use Activitypub\Post_Types;
use Activitypub\Rest\Admin\Actions_Controller;

/**
 * Test class for Actions_Controller permissions.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Admin\Actions_Controller
 */
class Test_Actions_Controller extends \WP_UnitTestCase {

	/**
	 * The controller under test.
	 *
	 * @var Actions_Controller
	 */
	protected $controller;

	/**
	 * OAuth client ID created for the denial test, if any.
	 *
	 * @var string
	 */
	protected $client_id = '';

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->controller = new Actions_Controller();
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\wp_set_current_user( 0 );

		// Clear any OAuth session established during the test.
		unset( $_SERVER['HTTP_AUTHORIZATION'] );
		Server::authenticate_oauth( null );

		if ( $this->client_id ) {
			Client::delete( $this->client_id );
		}

		parent::tear_down();
	}

	/**
	 * Authenticate the current request as the given user via an OAuth bearer token.
	 *
	 * @param int $user_id The user to mint a token for.
	 */
	private function authenticate_via_oauth( $user_id ) {
		Post_Types::register_oauth_post_types();

		$client          = Client::register(
			array(
				'name'          => 'Test Client',
				'redirect_uris' => array( 'https://example.com/callback' ),
			)
		);
		$this->client_id = $client['client_id'];

		$token                         = Token::create( $user_id, $this->client_id, array( Scope::READ ) );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token['access_token'];

		Server::authenticate_oauth( null );
	}

	/**
	 * Test an ActivityPub-enabled user is allowed when authenticated by cookie.
	 *
	 * @covers ::check_permission
	 */
	public function test_activitypub_user_is_allowed() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', $user_id )->add_cap( 'activitypub' );
		\wp_set_current_user( $user_id );

		$this->assertTrue( $this->controller->check_permission() );
	}

	/**
	 * Test OAuth-authenticated requests are denied.
	 *
	 * Moderation actions are an admin surface, not part of the ActivityPub C2S
	 * API, so a scoped OAuth token must not reach them even when its user would
	 * otherwise be capable.
	 *
	 * @covers ::check_permission
	 */
	public function test_oauth_authenticated_request_is_denied() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', $user_id )->add_cap( 'activitypub' );
		$this->authenticate_via_oauth( $user_id );

		$result = $this->controller->check_permission();

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_oauth_not_allowed', $result->get_error_code() );
		$this->assertEquals( 403, $result->get_error_data()['status'] );
	}

	/**
	 * A logged-out request must be denied even when the blog actor is enabled, whose ID (0)
	 * collides with the anonymous user ID.
	 *
	 * @covers ::check_permission
	 */
	public function test_anonymous_request_is_denied_with_blog_actor_enabled() {
		\update_option( 'activitypub_actor_mode', 'actor_blog' );
		\wp_set_current_user( 0 );

		$result = $this->controller->check_permission();

		$this->assertWPError( $result );
		$this->assertEquals( 'rest_forbidden', $result->get_error_code() );
		$this->assertEquals( 403, $result->get_error_data()['status'] );
	}
}
