<?php
/**
 * Test file for Inbox handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Handler\Inbox;

/**
 * Test class for Inbox handler.
 */
class Test_Inbox extends \WP_UnitTestCase {
	/**
	 * Test handle_inbox_requests.
	 */
	public function test_handle_inbox_requests() {
		$was_success = false;

		\add_filter(
			'activitypub_handled_inbox',
			function ( $data, $user_id, $success ) use ( &$was_success ) {
				$was_success = $success;
				return $data;
			},
			10,
			3
		);

		$data     = array(
			'id'     => 'https://example.com/activity/1',
			'type'   => 'Create',
			'object' => array(
				'id'   => 'https://example.com/object/1',
				'type' => 'Note',
			),
			'actor'  => 'https://example.com/actor/1',
		);
		$user_id  = 1;
		$type     = 'Create';
		$activity = \Activitypub\Activity\Activity::init_from_array( $data );

		Inbox::handle_inbox_requests( $data, $user_id, $type, $activity );

		$this->assertTrue( $was_success );

		$was_success = false;

		$data['object']['type'] = 'Person';
		$activity               = \Activitypub\Activity\Activity::init_from_array( $data );
		Inbox::handle_inbox_requests( $data, $user_id, $type, $activity );

		$this->assertFalse( $was_success );

		$was_success = false;

		$data['type']           = 'Delete';
		$data['object']['type'] = 'Article';
		$type                   = 'Delete';
		$activity               = \Activitypub\Activity\Activity::init_from_array( $data );
		Inbox::handle_inbox_requests( $data, $user_id, $type, $activity );

		$this->assertFalse( $was_success );

		$was_success = false;

		$data['type'] = 'Update';
		$type         = 'Update';
		$activity     = \Activitypub\Activity\Activity::init_from_array( $data );
		Inbox::handle_inbox_requests( $data, $user_id, $type, $activity );

		$this->assertTrue( $was_success );
	}
}
