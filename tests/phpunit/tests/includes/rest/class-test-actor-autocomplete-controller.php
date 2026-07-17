<?php
/**
 * Actor Autocomplete REST endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Model\Blog;
use Activitypub\Model\User;
use Activitypub\Rest\Actor_Autocomplete_Controller;

/**
 * Tests for the Actor Autocomplete REST endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Actor_Autocomplete_Controller
 */
class Test_Actor_Autocomplete_Controller extends \WP_UnitTestCase {

	/**
	 * Set up before each test: register the route and cache a remote actor.
	 */
	public function set_up() {
		parent::set_up();

		\update_option( 'activitypub_api', '1' );

		global $wp_rest_server;
		$wp_rest_server = new \Spy_REST_Server();
		\do_action( 'rest_api_init', $wp_rest_server );

		Remote_Actors::create(
			array(
				'id'                => 'https://remote.example.com/actor/alice',
				'type'              => 'Person',
				'inbox'             => 'https://remote.example.com/actor/alice/inbox',
				'name'              => 'Alice Remote',
				'preferredUsername' => 'alice',
			)
		);
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_api' );
		\remove_filter( 'activitypub_oauth_check_permission', '__return_true' );

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Grant OAuth permission for the request under test.
	 */
	private function authenticate() {
		\add_filter( 'activitypub_oauth_check_permission', '__return_true' );
	}

	/**
	 * The route is registered under the ActivityPub namespace.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/autocomplete', $routes );
	}

	/**
	 * The endpoint requires authentication.
	 *
	 * @covers ::get_items
	 */
	public function test_requires_authentication() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/autocomplete' );
		$request->set_param( 'q', 'alice' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * A query shorter than the minimum length returns a 400.
	 *
	 * @covers ::get_items
	 */
	public function test_query_too_short() {
		$this->authenticate();

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/autocomplete' );
		$request->set_param( 'q', 'a' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * A query matching nothing returns a 200 with an empty Collection.
	 *
	 * @covers ::get_items
	 */
	public function test_empty_results_return_empty_collection() {
		$this->authenticate();

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/autocomplete' );
		$request->set_param( 'q', 'nobodymatchesthis' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 'Collection', $data['type'] );
		$this->assertSame( 0, $data['totalItems'] );
		$this->assertSame( array(), $data['items'] );
	}

	/**
	 * A cached remote actor is returned as an actor object.
	 *
	 * @covers ::get_items
	 * @covers \Activitypub\Collection\Remote_Actors::search
	 */
	public function test_matches_remote_actor() {
		$this->authenticate();

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/autocomplete' );
		$request->set_param( 'q', 'alice' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 1, $data['totalItems'] );
		$this->assertSame( 'https://remote.example.com/actor/alice', $data['items'][0]['id'] );
		$this->assertSame( 'Person', $data['items'][0]['type'] );
		$this->assertArrayNotHasKey( '@context', $data['items'][0] );
	}

	/**
	 * A local user is returned as an actor object.
	 *
	 * @covers ::get_items
	 * @covers \Activitypub\Collection\Actors::search
	 */
	public function test_matches_local_user() {
		$this->authenticate();

		$user_id = self::factory()->user->create(
			array(
				'role'         => 'author',
				'display_name' => 'Aloysius Author',
				'user_login'   => 'aloysius',
			)
		);
		\get_user_by( 'id', $user_id )->add_cap( 'activitypub' );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/autocomplete' );
		$request->set_param( 'q', 'aloysius' );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$ids = \wp_list_pluck( $data['items'], 'id' );
		$this->assertContains( Actors::get_by_id( $user_id )->get_id(), $ids );
	}

	/**
	 * The response advertises both the ActivityStreams and autocomplete contexts.
	 *
	 * @covers ::get_items
	 */
	public function test_response_context() {
		$this->authenticate();

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/autocomplete' );
		$request->set_param( 'q', 'alice' );

		$data = rest_get_server()->dispatch( $request )->get_data();

		$this->assertContains( 'https://swicg.github.io/activitypub-api/autocomplete', $data['@context'] );
	}

	/**
	 * The Blog and User actors advertise actorAutocomplete when the API is enabled.
	 *
	 * @covers \Activitypub\Model\Blog::get_endpoints
	 * @covers \Activitypub\Model\User::get_endpoints
	 */
	public function test_actors_advertise_autocomplete_endpoint() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', $user_id )->add_cap( 'activitypub' );

		$blog_endpoints = ( new Blog() )->get_endpoints();
		$user_endpoints = User::from_wp_user( $user_id )->get_endpoints();

		$this->assertArrayHasKey( 'actorAutocomplete', $blog_endpoints );
		$this->assertStringEndsWith( '?q={q}', $blog_endpoints['actorAutocomplete'] );
		$this->assertArrayHasKey( 'actorAutocomplete', $user_endpoints );
		$this->assertStringEndsWith( '?q={q}', $user_endpoints['actorAutocomplete'] );
	}

	/**
	 * The actorAutocomplete endpoint is not advertised when the API is disabled.
	 *
	 * @covers \Activitypub\Model\Blog::get_endpoints
	 */
	public function test_autocomplete_endpoint_hidden_when_api_disabled() {
		\delete_option( 'activitypub_api' );

		$this->assertArrayNotHasKey( 'actorAutocomplete', ( new Blog() )->get_endpoints() );
	}
}
