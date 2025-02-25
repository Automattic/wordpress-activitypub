<?php
/**
 * Test file for Scheduler class.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests;

use Activitypub\Scheduler;
use Activitypub\Collection\Outbox;
use Activitypub\Activity\Base_Object;
use WP_UnitTestCase;

/**
 * Test class for Scheduler.
 *
 * @coversDefaultClass \Activitypub\Scheduler
 */
class Test_Scheduler extends WP_UnitTestCase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role' => 'author',
			)
		);
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_user( self::$user_id );
	}

	/**
	 * Test reprocess_outbox method.
	 *
	 * @covers ::reprocess_outbox
	 */
	public function test_reprocess_outbox() {
		// Create test activity objects.
		$activity_object = new Base_Object();
		$activity_object->set_content( 'Test Content' );
		$activity_object->set_type( 'Note' );
		$activity_object->set_id( 'https://example.com/test-id' );

		// Add multiple pending activities.
		$pending_ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$pending_ids[] = Outbox::add(
				$activity_object,
				'Create',
				self::$user_id,
				ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC
			);
		}

		$pending_ids[] = Outbox::add(
			$activity_object,
			'Update',
			self::$user_id,
			ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC
		);

		// Track scheduled events.
		$scheduled_events = array();
		add_filter(
			'schedule_event',
			function ( $event ) use ( &$scheduled_events ) {
				if ( 'activitypub_process_outbox' === $event->hook ) {
					$scheduled_events[] = $event->args[0];
				}
				return $event;
			}
		);

		// Run reprocess_outbox.
		Scheduler::reprocess_outbox();

		// Verify each pending activity was scheduled.
		$this->assertCount( 2, $scheduled_events, 'Should schedule 2 activities for processing' );
		$this->assertNotContains( $pending_ids[0], $scheduled_events, "Activity $pending_ids[0] should be scheduled" );
		$this->assertContains( $pending_ids[3], $scheduled_events, "Activity $pending_ids[3] should be scheduled" );

		// Test with published activities (should not be scheduled).
		$published_id = Outbox::add(
			$activity_object,
			'Create',
			self::$user_id,
			ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC
		);
		wp_update_post(
			array(
				'ID'          => $published_id,
				'post_status' => 'publish',
			)
		);

		// Reset tracked events.
		$scheduled_events = array();

		// Run reprocess_outbox again.
		Scheduler::reprocess_outbox();

		// Verify published activity was not scheduled.
		$this->assertNotContains( $published_id, $scheduled_events, 'Published activity should not be scheduled' );

		// Clean up.
		foreach ( $pending_ids as $id ) {
			wp_delete_post( $id, true );
		}
		wp_delete_post( $published_id, true );
		remove_all_filters( 'schedule_event' );
	}

	/**
	 * Test reprocess_outbox with no pending activities.
	 *
	 * @covers ::reprocess_outbox
	 */
	public function test_reprocess_outbox_no_pending() {
		$scheduled_events = array();
		add_filter(
			'schedule_event',
			function ( $event ) use ( &$scheduled_events ) {
				if ( 'activitypub_process_outbox' === $event->hook ) {
					$scheduled_events[] = $event->args[0];
				}
				return $event;
			}
		);

		// Run reprocess_outbox with no pending activities.
		Scheduler::reprocess_outbox();

		// Verify no events were scheduled.
		$this->assertEmpty( $scheduled_events, 'No events should be scheduled when there are no pending activities' );

		remove_all_filters( 'schedule_event' );
	}

	/**
	 * Test reprocess_outbox scheduling behavior.
	 *
	 * @covers ::reprocess_outbox
	 */
	public function test_reprocess_outbox_scheduling() {
		// Create a test activity.
		$activity_object = new Base_Object();
		$activity_object->set_content( 'Test Content' );
		$activity_object->set_type( 'Note' );
		$activity_object->set_id( 'https://example.com/test-id-2' );

		$pending_id = Outbox::add(
			$activity_object,
			'Create',
			self::$user_id,
			ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC
		);

		// Track scheduled events and their timing.
		$scheduled_time = 0;
		add_filter(
			'schedule_event',
			function ( $event ) use ( &$scheduled_time ) {
				if ( 'activitypub_process_outbox' === $event->hook ) {
					$scheduled_time = $event->timestamp;
				}
				return $event;
			}
		);

		// Run reprocess_outbox.
		Scheduler::reprocess_outbox();

		// Verify scheduling time.
		$this->assertSame( $scheduled_time, wp_next_scheduled( 'activitypub_process_outbox', array( $pending_id ) ) );

		// Clean up.
		wp_delete_post( $pending_id, true );
		remove_all_filters( 'schedule_event' );
	}

	/**
	 * Test purge_outbox method with more than 20 posts.
	 *
	 * @covers ::purge_outbox
	 */
	public function test_purge_outbox_more_than_20_posts() {
		// Create 25 posts, 5 older than 6 months.
		self::factory()->post->create_many(
			25,
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) ),
			)
		);
		self::factory()->post->create_many(
			5,
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-7 months' ) ),
			)
		);

		Scheduler::purge_outbox();
		wp_cache_delete( _count_posts_cache_key( Outbox::POST_TYPE ), 'counts' );

		// Assert that 5 posts were deleted, leaving 25.
		$this->assertEquals( 25, wp_count_posts( Outbox::POST_TYPE )->publish );
	}

	/**
	 * Test purge_outbox method with 20 or fewer posts.
	 *
	 * @covers ::purge_outbox
	 */
	public function test_purge_outbox_20_or_fewer_posts() {
		// Create 20 posts, all older than 6 months.
		self::factory()->post->create_many(
			20,
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-7 months' ) ),
			)
		);

		Scheduler::purge_outbox();
		wp_cache_delete( _count_posts_cache_key( Outbox::POST_TYPE ), 'counts' );

		// Assert that no posts were deleted.
		$this->assertEquals( 20, wp_count_posts( Outbox::POST_TYPE )->publish );
	}

	/**
	 * Test purge_outbox method with changing activitypub_outbox_purge_days option.
	 *
	 * @covers ::purge_outbox
	 */
	public function test_purge_outbox_with_different_purge_days() {
		// Create posts older than initial_days.
		self::factory()->post->create_many(
			25,
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-4 months' ) ),
				'post_status' => 'publish',
			)
		);

		// Run purge_outbox with initial_days.
		Scheduler::purge_outbox();
		wp_cache_delete( _count_posts_cache_key( Outbox::POST_TYPE ), 'counts' );

		// Verify posts are not deleted.
		$this->assertEquals( 25, wp_count_posts( Outbox::POST_TYPE )->publish );

		// Change the purge days option.
		update_option( 'activitypub_outbox_purge_days', 90 );

		// Run purge_outbox with changed_days.
		Scheduler::purge_outbox();
		wp_cache_delete( _count_posts_cache_key( Outbox::POST_TYPE ), 'counts' );

		// Verify posts are deleted.
		$this->assertEquals( 0, wp_count_posts( Outbox::POST_TYPE )->publish );
	}

	/**
	 * Test async_batch method.
	 *
	 * @covers ::async_batch
	 */
	public function test_async_batch_with_invalid_callback() {
		// Set up expectations for _doing_it_wrong notice.
		$this->setExpectedIncorrectUsage( 'Activitypub\Scheduler::async_batch' );

		// Create a mock callback that implements __invoke but is not in the allowed list.
		$mock_class = $this->getMockBuilder( 'stdClass' )
			->addMethods( array( 'callback' ) )
			->getMock();

		$mock_class->expects( $this->never() )
			->method( 'callback' );

		// Run async_batch with invalid callback.
		Scheduler::async_batch( array( $mock_class, 'callback' ) );
	}
}
