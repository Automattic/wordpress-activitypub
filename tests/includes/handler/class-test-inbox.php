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
		$filter_called = false;

		\add_filter(
			'activitypub_handled_inbox',
			function ( $response ) use ( &$filter_called ) {
				$filter_called = true;
				return $response;
			}
		);

		$data     = array(
			'id'     => 'https://example.com/activity/1',
			'type'   => 'Create',
			'object' => array(
				'id'   => 'https://example.com/object/1',
				'type' => 'Note',
			),
		);
		$user_id  = 1;
		$type     = 'Create';
		$activity = \Activitypub\Activity\Activity::init_from_array( $data );

		Inbox::handle_inbox_requests( $data, $user_id, $type, $activity );

		$this->assertTrue( $filter_called );

		$filter_called = false;

		$data['object']['type'] = 'Person';
		$activity               = \Activitypub\Activity\Activity::init_from_array( $data );
		Inbox::handle_inbox_requests( $data, $user_id, $type, $activity );

		$this->assertFalse( $filter_called );

		$filter_called = false;

		$data['type']           = 'Delete';
		$data['object']['type'] = 'Article';
		$type                   = 'Delete';
		$activity               = \Activitypub\Activity\Activity::init_from_array( $data );
		Inbox::handle_inbox_requests( $data, $user_id, $type, $activity );

		$this->assertFalse( $filter_called );

		$filter_called = false;

		$data['type'] = 'Update';
		$type         = 'Update';
		$activity     = \Activitypub\Activity\Activity::init_from_array( $data );
		Inbox::handle_inbox_requests( $data, $user_id, $type, $activity );

		$this->assertTrue( $filter_called );
	}
}
