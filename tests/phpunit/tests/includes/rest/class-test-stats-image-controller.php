<?php
/**
 * Stats Image REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Actors;
use Activitypub\Rest\Stats_Image_Controller;
use Activitypub\Statistics;

/**
 * Tests for Stats Image REST API endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Stats_Image_Controller
 */
class Test_Stats_Image_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before our tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Seed demo stats for a user.
	 *
	 * @param int $user_id The user ID.
	 * @param int $year    The year.
	 */
	private function seed_stats( $user_id, $year ) {
		$option_name = \sprintf( 'activitypub_stats_%d_%d_annual', $user_id, $year );

		\update_option(
			$option_name,
			array(
				'posts_count'          => 42,
				'most_active_month'    => 6,
				'followers_start'      => 100,
				'followers_end'        => 250,
				'followers_net_change' => 150,
				'top_multiplicator'    => array(
					'name'  => '@supporter@example.com',
					'url'   => 'https://example.com/@supporter',
					'count' => 12,
				),
				'top_posts'            => array(
					array(
						'title'            => 'Test Post',
						'url'              => 'https://example.com/test-post/',
						'engagement_count' => 50,
					),
				),
				'like_count'           => 100,
				'repost_count'         => 50,
				'comment_count'        => 25,
				'quote_count'          => 10,
				'compiled_at'          => \gmdate( 'Y-m-d H:i:s' ),
			),
			false
		);
	}

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = \rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/stats/image/(?P<user_id>[\\d]+)/(?P<year>[\\d]{4})', $routes );
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/stats/image-url/(?P<user_id>[\\d]+)/(?P<year>[\\d]{4})', $routes );
	}

	/**
	 * Test getting a stats image returns valid response.
	 *
	 * Note: The controller calls exit() after outputting the PNG, so we
	 * cannot fully dispatch the request in tests. Instead we verify the
	 * route exists and test error cases that return before the exit.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item() {
		$this->seed_stats( self::$user_id, 2025 );

		// Verify the route exists and accepts valid parameters.
		$routes = \rest_get_server()->get_routes();
		$route  = '/' . ACTIVITYPUB_REST_NAMESPACE . '/stats/image/(?P<user_id>[\\d]+)/(?P<year>[\\d]{4})';
		$this->assertArrayHasKey( $route, $routes );

		// Verify a GET endpoint is registered.
		$endpoints = $routes[ $route ];
		$methods   = array();
		foreach ( $endpoints as $endpoint ) {
			if ( isset( $endpoint['methods'] ) ) {
				$methods = \array_merge( $methods, \array_keys( $endpoint['methods'] ) );
			}
		}
		$this->assertContains( 'GET', $methods );
	}

	/**
	 * Test schema (OPTIONS request).
	 *
	 * @covers ::register_routes
	 */
	public function test_get_item_schema() {
		$request  = new \WP_REST_Request( 'OPTIONS', '/' . ACTIVITYPUB_REST_NAMESPACE . '/stats/image/0/2025' );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'endpoints', $data );
	}

	/**
	 * Test 404 when no stats exist.
	 *
	 * @covers ::get_item
	 */
	public function test_get_item_no_stats() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/stats/image/' . self::$user_id . '/1999' );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'no_stats', $data['code'] );
	}

	/**
	 * Test endpoint is publicly accessible (no auth error on missing stats).
	 *
	 * @covers ::register_routes
	 */
	public function test_endpoint_is_public() {
		\wp_set_current_user( 0 );

		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/stats/image/' . self::$user_id . '/1999' );
		$response = \rest_get_server()->dispatch( $request );

		// Should get 404 (no stats), not 401/403.
		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test route accepts color parameters.
	 *
	 * @covers ::register_routes
	 */
	public function test_route_accepts_color_params() {
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/stats/image/' . self::$user_id . '/1999' );
		$request->set_param( 'bg', 'ff0000' );
		$request->set_param( 'fg', '00ff00' );

		$response = \rest_get_server()->dispatch( $request );

		// Should get 404 (no stats), not 400 (bad params).
		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test invalid year format.
	 *
	 * @covers ::register_routes
	 */
	public function test_invalid_year_format() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/stats/image/' . self::$user_id . '/99' );
		$response = \rest_get_server()->dispatch( $request );

		// Route pattern requires 4 digits, so this should 404 (no route match).
		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test image-url endpoint returns a URL when stats exist.
	 *
	 * @covers ::get_url
	 */
	public function test_get_url() {
		if ( ! \Activitypub\Cache\Stats_Image::is_available() ) {
			$this->markTestSkipped( 'GD library is not available.' );
		}

		$this->seed_stats( Actors::BLOG_USER_ID, 2025 );

		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/stats/image-url/' . Actors::BLOG_USER_ID . '/2025' );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'url', $data );
		$this->assertStringContainsString( 'stats', $data['url'] );
	}

	/**
	 * Test image-url endpoint returns 404 when no stats exist.
	 *
	 * @covers ::get_url
	 */
	public function test_get_url_no_stats() {
		if ( ! \Activitypub\Cache\Stats_Image::is_available() ) {
			$this->markTestSkipped( 'GD library is not available.' );
		}

		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/stats/image-url/' . self::$user_id . '/1999' );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 404, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( 'no_stats', $data['code'] );
	}
}
