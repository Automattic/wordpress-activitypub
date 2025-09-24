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
	 * Test handle_inbox_requests with various activity scenarios.
	 *
	 * @dataProvider inbox_requests_provider
	 *
	 * @param array  $activity_data    The activity data to test.
	 * @param string $activity_type    The activity type.
	 * @param bool   $expected_success The expected success result.
	 * @param string $description      Description of the test case.
	 */
	public function test_handle_inbox_requests( $activity_data, $activity_type, $expected_success, $description ) {
		$was_successful = false;

		\add_filter(
			'activitypub_handled_inbox',
			function ( $data, $user_id, $success ) use ( &$was_successful ) {
				$was_successful = $success;
				return $data;
			},
			10,
			3
		);

		$user_id  = 1;
		$activity = \Activitypub\Activity\Activity::init_from_array( $activity_data );

		Inbox::handle_inbox_requests( $activity_data, $user_id, $activity_type, $activity );

		$this->assertEquals( $expected_success, $was_successful, $description );

		\remove_all_filters( 'activitypub_handled_inbox' );
	}

	/**
	 * Data provider for inbox requests tests.
	 *
	 * @return array Test cases with activity data, type, expected success, and description.
	 */
	public function inbox_requests_provider() {
		return array(
			'create_note_success'    => array(
				array(
					'id'     => 'https://example.com/activity/1',
					'type'   => 'Create',
					'object' => array(
						'id'   => 'https://example.com/object/1',
						'type' => 'Note',
					),
					'actor'  => 'https://example.com/actor/1',
				),
				'Create',
				true,
				'Should handle Create activity with Note object successfully',
			),
			'create_person_failure'  => array(
				array(
					'id'     => 'https://example.com/activity/2',
					'type'   => 'Create',
					'object' => array(
						'id'   => 'https://example.com/object/2',
						'type' => 'Person',
					),
					'actor'  => 'https://example.com/actor/2',
				),
				'Create',
				false,
				'Should not handle Create activity with Person object',
			),
			'delete_article_failure' => array(
				array(
					'id'     => 'https://example.com/activity/3',
					'type'   => 'Delete',
					'object' => array(
						'id'   => 'https://example.com/object/3',
						'type' => 'Article',
					),
					'actor'  => 'https://example.com/actor/3',
				),
				'Delete',
				false,
				'Should not handle Delete activity with Article object',
			),
			'update_article_success' => array(
				array(
					'id'     => 'https://example.com/activity/4',
					'type'   => 'Update',
					'object' => array(
						'id'   => 'https://example.com/object/4',
						'type' => 'Article',
					),
					'actor'  => 'https://example.com/actor/4',
				),
				'Update',
				true,
				'Should handle Update activity successfully',
			),
		);
	}
}
