<?php
/**
 * Test Dispatcher Class.
 *
 * @package ActivityPub
 */

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Dispatcher;


/**
 * Test class for Activitypub Dispatcher.
 *
 * @covers Activitypub\Dispatcher
 */
class Test_Dispatcher extends WP_UnitTestCase {
	/**
	 * Test maybe_add_inboxes_of_blog_user when actor mode is not ACTIVITYPUB_ACTOR_AND_BLOG_MODE
	 */
	public function test_maybe_add_inboxes_of_blog_user_wrong_mode() {
		update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$inboxes  = array( 'https://example.com/inbox' );
		$activity = $this->createMock( Activity::class );

		$result = Dispatcher::maybe_add_inboxes_of_blog_user( $inboxes, 123, $activity );
		$this->assertEquals( $inboxes, $result );
	}

	/**
	 * Test maybe_add_inboxes_of_blog_user when actor is blog user
	 */
	public function test_maybe_add_inboxes_of_blog_user_is_blog_user() {
		update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$inboxes  = array( 'https://example.com/inbox' );
		$activity = $this->createMock( Activity::class );

		$result = Dispatcher::maybe_add_inboxes_of_blog_user( $inboxes, Actors::BLOG_USER_ID, $activity );
		$this->assertEquals( $inboxes, $result );
	}

	/**
	 * Test maybe_add_inboxes_of_blog_user when activity type is not Update
	 */
	public function test_maybe_add_inboxes_of_blog_user_not_update() {
		update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$inboxes  = array( 'https://example.com/inbox' );
		$activity = $this->createMock( Activity::class );

		// Mock the static method using reflection.
		$activity->expects( $this->once() )
			->method( '__call' )
			->with( 'get_type' )
			->willReturn( 'Create' );

		$result = Dispatcher::maybe_add_inboxes_of_blog_user( $inboxes, 123, $activity );
		$this->assertEquals( $inboxes, $result );
	}
}
