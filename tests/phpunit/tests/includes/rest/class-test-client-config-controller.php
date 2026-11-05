<?php
/**
 * Client Configuration REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

/**
 * Tests for Client Configuration REST API endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Client_Config_Controller
 */
class Test_Client_Config_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

	/**
	 * Set up the test.
	 */
	public function set_up() {
		\update_option( 'activitypub_api', '1' );

		parent::set_up();
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_api' );

		parent::tear_down();
	}

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = \rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/client-config', $routes );
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/client-config/1.0', $routes );
	}

	/**
	 * Test that the routes are not registered when the API is disabled.
	 *
	 * @covers ::register_routes
	 */
	public function test_routes_require_api() {
		\delete_option( 'activitypub_api' );

		global $wp_rest_server;
		$wp_rest_server = new \Spy_REST_Server();
		\do_action( 'rest_api_init', $wp_rest_server );

		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/client-config' );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test the discovery document.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/client-config' );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $data['links'] );
		$this->assertEquals( 'https://w3id.org/fep/65ca/1.0', $data['links'][0]['rel'] );
		$this->assertStringEndsWith( '/client-config/1.0', $data['links'][0]['href'] );

		// Make sure the link works.
		$request  = new \WP_REST_Request( 'GET', str_replace( \get_rest_url(), '/', $data['links'][0]['href'] ) );
		$response = \rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test the configuration document.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/client-config/1.0' );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$headers  = $response->get_headers();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 'public, max-age=3600', $headers['Cache-Control'] );

		$this->assertSame( '1.0', $data['version'] );
		$this->assertSame( array( 'max_length' => null ), $data['composer'] );
		$this->assertFalse( $data['accounts']['username']['changeable'] );

		$this->assertSame( \wp_login_url(), $data['links']['login'] );
		$this->assertSame( \wp_lostpassword_url(), $data['links']['password_reset'] );
		$this->assertSame( \admin_url( 'profile.php' ), $data['links']['account_settings'] );
		$this->assertArrayNotHasKey( 'registration', $data['links'] );
		$this->assertArrayNotHasKey( 'privacy', $data['links'] );
	}

	/**
	 * Test that registration and privacy links appear when the site provides them.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_optional_links() {
		\update_option( 'users_can_register', 1 );

		$privacy_page = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		\update_option( 'wp_page_for_privacy_policy', $privacy_page );

		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/client-config/1.0' );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( \wp_registration_url(), $data['links']['registration'] );
		$this->assertSame( \get_privacy_policy_url(), $data['links']['privacy'] );
	}

	/**
	 * Test get_item_schema method.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_item_schema() {
		// Controller does not implement get_item_schema().
	}
}
