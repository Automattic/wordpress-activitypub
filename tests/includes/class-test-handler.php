<?php
/**
 * Handler Test Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Outbox;
use Activitypub\Handler;
use WP_UnitTestCase;

use function Activitypub\add_to_outbox;

/**
 * Handler Test Class
 */
class Test_Handler extends WP_UnitTestCase {

	/**
	 * The user ID.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
	}

	/**
	 * Test the inherit functionality
	 */
	public function test_activity() {
		// Create a mock inherit activity.
		$activity = new Activity();
		$activity->set_type( 'Move' );
		$activity->set_id( 'https://example.com/activity/1' );
		$activity->set_to( array( 'https://example.com/to' ) );
		$activity->set_cc( array( 'https://example.com/cc' ) );

		$id = add_to_outbox( $activity, null, $this->user_id );

		$outbox_item     = get_post( $id );
		$outbox_activity = Outbox::get_activity( $outbox_item );

		$this->assertEquals( 'Move', $outbox_activity->get_type() );
	}
}
