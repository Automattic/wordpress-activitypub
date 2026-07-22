<?php
/**
 * Test file for Statistics_Controller permissions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest\Admin;

use Activitypub\OAuth\Client;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server;
use Activitypub\OAuth\Token;
use Activitypub\Post_Types;
use Activitypub\Rest\Admin\Statistics_Controller;

/**
 * Test class for Statistics_Controller permissions.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Admin\Statistics_Controller
 */
class Test_Statistics_Controller extends \WP_UnitTestCase {

	/**
	 * The controller under test.
	 *
	 * @var Statistics_Controller
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

		$this->controller = new Statistics_Controller();
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\wp_set_current_user( 0 );
		\remove_all_filters( 'activitypub_user_can_act_as_blog' );

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
	 * Build a request targeting a given user_id.
	 *
	 * @param int $user_id The user ID to request stats for.
	 * @return \WP_REST_Request
	 */
	private function build_request( $user_id ) {
		$request = new \WP_REST_Request( 'GET', '/activitypub/1.0/stats/' . $user_id );
		$request->set_param( 'user_id', $user_id );

		return $request;
	}

	/**
	 * Test administrators can view blog actor stats.
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_admin_can_view_blog_stats() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $admin_id );

		$this->assertTrue( $this->controller->get_item_permissions_check( $this->build_request( 0 ) ) );
	}

	/**
	 * Test non-administrators cannot view blog actor stats by default.
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_non_admin_cannot_view_blog_stats() {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\wp_set_current_user( $author_id );

		$result = $this->controller->get_item_permissions_check( $this->build_request( 0 ) );

		$this->assertWPError( $result );
		$this->assertEquals( 'rest_forbidden', $result->get_error_code() );
		$this->assertEquals( 403, $result->get_error_data()['status'] );
	}

	/**
	 * Test the `activitypub_user_can_act_as_blog` filter can grant access to non-admins.
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_filter_can_grant_blog_stats_access() {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\wp_set_current_user( $author_id );
		\add_filter( 'activitypub_user_can_act_as_blog', '__return_true' );

		$this->assertTrue( $this->controller->get_item_permissions_check( $this->build_request( 0 ) ) );
	}

	/**
	 * Test users can view their own stats.
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_user_can_view_own_stats() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\wp_set_current_user( $user_id );

		$this->assertTrue( $this->controller->get_item_permissions_check( $this->build_request( $user_id ) ) );
	}

	/**
	 * Test users cannot view another user's stats.
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_user_cannot_view_other_user_stats() {
		$user_id  = self::factory()->user->create( array( 'role' => 'author' ) );
		$other_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\wp_set_current_user( $user_id );

		$result = $this->controller->get_item_permissions_check( $this->build_request( $other_id ) );

		$this->assertWPError( $result );
		$this->assertEquals( 'rest_forbidden', $result->get_error_code() );
	}

	/**
	 * Test OAuth-authenticated requests are denied, even for the user's own stats.
	 *
	 * Statistics is an admin surface, not part of the ActivityPub C2S API, so a
	 * scoped OAuth token must not reach it.
	 *
	 * @covers ::get_item_permissions_check
	 */
	public function test_oauth_authenticated_request_is_denied() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->authenticate_via_oauth( $user_id );

		$result = $this->controller->get_item_permissions_check( $this->build_request( $user_id ) );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_oauth_not_allowed', $result->get_error_code() );
		$this->assertEquals( 403, $result->get_error_data()['status'] );
	}
}
