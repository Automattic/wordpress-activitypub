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
 * @coversDefaultClass Activitypub\Dispatcher
 */
class Test_Dispatcher extends WP_UnitTestCase {
	/**
	 * Tear down the test case.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_actor_mode' );

		parent::tear_down();
	}

	/**
	 * Test maybe_add_inboxes_of_blog_user when actor mode is not ACTIVITYPUB_ACTOR_AND_BLOG_MODE
	 *
	 * @covers ::maybe_add_inboxes_of_blog_user
	 */
	public function test_maybe_add_inboxes_of_blog_user_wrong_mode() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		$inboxes  = array( 'https://example.com/inbox' );
		$activity = $this->createMock( Activity::class );

		$result = Dispatcher::maybe_add_inboxes_of_blog_user( $inboxes, 1, $activity );
		$this->assertEquals( $inboxes, $result );
	}

	/**
	 * Test maybe_add_inboxes_of_blog_user when actor is blog user
	 *
	 * @covers ::maybe_add_inboxes_of_blog_user
	 */
	public function test_maybe_add_inboxes_of_blog_user_is_blog_user() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$inboxes  = array( 'https://example.com/inbox' );
		$activity = $this->createMock( Activity::class );

		$result = Dispatcher::maybe_add_inboxes_of_blog_user( $inboxes, Actors::BLOG_USER_ID, $activity );
		$this->assertEquals( $inboxes, $result );
	}

	/**
	 * Test maybe_add_inboxes_of_blog_user when activity type is not Update
	 *
	 * @covers ::maybe_add_inboxes_of_blog_user
	 */
	public function test_maybe_add_inboxes_of_blog_user_not_update() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$inboxes  = array( 'https://example.com/inbox' );
		$activity = $this->createMock( Activity::class, array( '__call' ) );

		// Mock the static method using reflection.
		$activity->expects( $this->any() )
			->method( '__call' )
			->willReturnCallback(
				function ( $name ) {
					if ( 'get_to' === $name ) {
						return array( 'https://www.w3.org/ns/activitystreams#Public' );
					}

					if ( 'get_cc' === $name ) {
						return array();
					}

					if ( 'get_type' === $name ) {
						return 'Create';
					}

					return null;
				}
			);

		$result = Dispatcher::maybe_add_inboxes_of_blog_user( $inboxes, 1, $activity );
		$this->assertEquals( $inboxes, $result );
	}
}
