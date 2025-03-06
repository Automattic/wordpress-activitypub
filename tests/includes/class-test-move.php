<?php
/**
 * Test file for Activitypub Move.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Actors;
use Activitypub\Model\User;

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
	 * Clean up after tests.
	 */
	public static function tear_down_after_class() {
		wp_delete_user( self::$user_id );
	}

	/**
	 * Test the account() method with valid input.
	 *
	 * @covers ::account
	 */
	public function test_account_with_valid_input() {
		$from = Actors::get_by_id( self::$user_id )->get_id();
		$to   = 'https://newsite.com/user/1';

		\Activitypub\Move::account( $from, $to );

		$moved_to = Actors::get_by_id( self::$user_id )->get_moved_to();
		$this->assertEquals( $to, $moved_to );

		$also_known_as = Actors::get_by_id( self::$user_id )->get_also_known_as();
		$this->assertContains( $from, $also_known_as );
	}

	/**
	 * Test the account() method with invalid user.
	 *
	 * @covers ::account
	 */
	public function test_account_with_invalid_user() {
		$result = \Activitypub\Move::account(
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
		$to   = 'https://invalid-url.com/user/1';

		$filter = function () {
			return new \WP_Error( 'http_request_failed', 'Invalid URL' );
		};
		\add_filter( 'pre_http_request', $filter );

		$result = \Activitypub\Move::account( $from, $to );

		$this->assertWPError( $result );
		$this->assertEquals( 'http_request_failed', $result->get_error_code() );

		\remove_filter( 'pre_http_request', $filter );
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

		\Activitypub\Move::account( $from, $to );

		$also_known_as = Actors::get_by_id( self::$user_id )->get_also_known_as();
		$this->assertCount( 3, $also_known_as );
		$this->assertContains( $from, $also_known_as );
		$this->assertContains( 'https://old.example.com/user/1', $also_known_as );

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

		\Activitypub\Move::account( $from, $to );

		$also_known_as = Actors::get_by_id( Actors::BLOG_USER_ID )->get_also_known_as();
		$this->assertCount( 3, $also_known_as );
		$this->assertContains( $from, $also_known_as );

		\delete_option( 'activitypub_actor_mode' );
	}
}
