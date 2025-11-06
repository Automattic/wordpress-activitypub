<?php
/**
 * Test file for Activitypub Move.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Actors;
use Activitypub\Move;

/**
 * Test class for Activitypub Move.
 *
 * @coversDefaultClass \Activitypub\Move
 */
class Test_Move extends \WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before tests run.
	 */
	public static function set_up_before_class() {
		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
	}

	/**
	 * Test the account() method with valid input.
	 *
	 * @covers ::account
	 */
	public function test_account_with_valid_input() {
		$from = Actors::get_by_id( self::$user_id )->get_id();
		$to   = 'https://newsite.com/user/1';

		add_filter( 'pre_http_request', '__return_false' );
		Move::externally( $from, $to );

		$moved_to = Actors::get_by_id( self::$user_id )->get_moved_to();
		$this->assertEquals( $to, $moved_to );
	}

	/**
	 * Test the account() method with invalid user.
	 *
	 * @covers ::account
	 */
	public function test_account_with_invalid_user() {
		$result = Move::externally(
			'https://example.com/nonexistent/user',
			'https://newsite.com/user/999'
		);

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_no_user_found', $result->get_error_code() );
	}

	/**
	 * Test the account() method with invalid target URL.
	 *
	 * @covers ::account
	 */
	public function test_account_with_invalid_target() {
		$from = Actors::get_by_id( self::$user_id )->get_id();
		$to   = 'https://example.com/user/1';

		$filter = function () {
			return new \WP_Error( 'http_request_failed', 'Invalid URL' );
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		$result = Move::externally( $from, $to );

		$this->assertWPError( $result );
		$this->assertEquals( 'http_request_failed', $result->get_error_code() );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}

	/**
	 * Test the account() method with duplicate moves.
	 *
	 * @covers ::account
	 */
	public function test_account_with_duplicate_moves() {
		$from = Actors::get_by_id( self::$user_id )->get_id();
		$to   = 'https://newsite.com/user/1';

		\update_user_option( self::$user_id, 'activitypub_also_known_as', array( 'https://old.example.com/user/1' ) );

		$filter = function () use ( $from ) {
			return array(
				'body'     => wp_json_encode( array( 'also_known_as' => array( $from ) ) ),
				'response' => array( 'code' => 200 ),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		Move::externally( $from, $to );

		$moved_to = Actors::get_by_id( self::$user_id )->get_moved_to();
		$this->assertEquals( $to, $moved_to );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Test the account() method with duplicate moves.
	 *
	 * @covers ::account
	 */
	public function test_account_with_blog_author_as_actor() {
		// Change user mode to blog author.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$from = Actors::get_by_id( Actors::BLOG_USER_ID )->get_id();
		$to   = 'https://newsite.com/user/0';

		Move::externally( $from, $to );

		$moved_to = Actors::get_by_id( Actors::BLOG_USER_ID )->get_moved_to();
		$this->assertEquals( $to, $moved_to );

		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test the internally() method with valid input.
	 *
	 * @covers ::internally
	 */
	public function test_internally_with_valid_input() {
		$from = get_author_posts_url( self::$user_id );
		$to   = Actors::get_by_id( self::$user_id )->get_id();

		Move::internally( $from, $to );

		// Clear cache.
		wp_cache_delete( self::$user_id, 'users' );

		// Updated user should not have moved_to set.
		$moved_to = Actors::get_by_id( self::$user_id )->get_moved_to();
		$this->assertNull( $moved_to );

		$also_known_as = Actors::get_by_id( self::$user_id )->get_also_known_as();
		$this->assertContains( $from, $also_known_as );
	}

	/**
	 * Test that the Move Activity created by internally() has the correct properties.
	 *
	 * @covers ::internally
	 */
	public function test_internally_activity_object_properties() {
		$from = get_author_posts_url( self::$user_id );
		$to   = Actors::get_by_id( self::$user_id )->get_id();

		// Call the method and get the outbox item ID.
		$outbox_id = Move::internally( $from, $to );

		// Verify we got a valid outbox ID.
		$this->assertIsInt( $outbox_id );

		// Get the outbox item from the database.
		$outbox_item = get_post( $outbox_id );

		// Verify the outbox item exists.
		$this->assertNotNull( $outbox_item );

		// Get the activity JSON from the outbox item.
		$activity = json_decode( $outbox_item->post_content );

		// Verify the activity type is Move.
		$this->assertEquals( 'Move', $activity->type );

		// Verify the activity object is set to the actor, not the target.
		$this->assertEquals( $from, $activity->object );
		$this->assertEquals( $from, $activity->actor );
		$this->assertEquals( $from, $activity->origin );
		$this->assertEquals( $to, $activity->target );
	}

	/**
	 * Test the change_domain() method with valid input.
	 *
	 * @covers ::change_domain
	 */
	public function test_change_domain_with_valid_input() {
		// Enable blog actor.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$old_domain = home_url();
		$new_domain = 'http://newdomain.com';
		\remove_filter( 'option_home', '_config_wp_home' );
		\update_option( 'home', $new_domain );

		// Run the domain change.
		$results = Move::change_domain( $old_domain, $new_domain );

		// Verify the results.
		$this->assertIsArray( $results );

		// Check that each result has the expected structure.
		$result      = reset( $results );
		$outbox_item = json_decode( get_post_field( 'post_content', $result['result'] ) );

		$this->assertSame( $outbox_item->target, $result['actor'] );
		$this->assertStringStartsWith( $new_domain, $outbox_item->target );

		// Verify the old host was stored.
		$this->assertEquals( \wp_parse_url( $old_domain, PHP_URL_HOST ), \get_option( 'activitypub_old_host' ) );

		// Clean up.
		\delete_option( 'activitypub_old_host' );
		\delete_option( 'activitypub_blog_user_old_host_data' );
		\delete_option( 'activitypub_actor_mode' );
		\update_option( 'home', $old_domain );
		\add_filter( 'option_home', '_config_wp_home' );
		\delete_user_option( self::$user_id, 'activitypub_old_host_data' );
	}
}
