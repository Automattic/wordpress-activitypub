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
class Test_Dispatcher extends \Activitypub\Tests\ActivityPub_Outbox_TestCase {
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
	 * @expectedDeprecated Activitypub\Dispatcher::maybe_add_inboxes_of_blog_user
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
	 * @expectedDeprecated Activitypub\Dispatcher::maybe_add_inboxes_of_blog_user
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
	 * @expectedDeprecated Activitypub\Dispatcher::maybe_add_inboxes_of_blog_user
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

	/**
	 * Test process_outbox.
	 *
	 * @covers ::process_outbox
	 */
	public function test_process_outbox() {
		$post_id = self::factory()->post->create( array( 'post_author' => self::$user_id ) );

		$test_callback = function ( $send, $activity ) {
			$this->assertInstanceOf( Activity::class, $activity );
			$this->assertEquals( 'Create', $activity->get_type() );

			return $send;
		};
		add_filter( 'activitypub_send_activity_to_followers', $test_callback, 10, 2 );

		$outbox_item = $this->get_latest_outbox_item( \add_query_arg( 'p', $post_id, \home_url( '/' ) ) );

		$type     = \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true );
		$activity = new Activity();
		$activity->set_type( $type );
		$activity->set_id( $outbox_item->guid );
		// Pre-fill the Activity with data (for example cc and to).
		$activity->set_object( \json_decode( $outbox_item->post_content, true ) );
		$activity->set_actor( Actors::get_by_id( $outbox_item->post_author )->get_id() );

		Dispatcher::process_outbox( $outbox_item->ID );

		$this->assertNotFalse(
			wp_next_scheduled(
				'activitypub_async_batch',
				array(
					Dispatcher::$callback,
					$activity->to_json(),
					(string) self::$user_id,
					$outbox_item->ID,
					Dispatcher::$batch_size,
				)
			)
		);

		remove_filter( 'activitypub_send_activity_to_followers', $test_callback );
	}
}
