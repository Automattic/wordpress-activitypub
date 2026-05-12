<?php
/**
 * Test file for Statistics_Controller permissions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest\Admin;

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

		parent::tear_down();
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
}
