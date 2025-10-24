<?php
/**
 * Test file for Inbox handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Collection\Inbox as Inbox_Collection;
use Activitypub\Handler\Inbox;

/**
 * Test class for Inbox handler.
 */
class Test_Inbox extends \WP_UnitTestCase {
	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();
		// Enable inbox persistence for tests.
		\update_option( 'activitypub_persist_inbox', '1' );
	}

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

		\add_action(
			'activitypub_handled_inbox',
			// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			function ( $data, $user_ids, $type, $activity, $result, $context ) use ( &$was_successful ) {
				// Success if result is an integer, failure if it's a WP_Error.
				$was_successful = ! \is_wp_error( $result ) && \is_int( $result );
			},
			10,
			6
		);

		$user_id  = 1;
		$activity = \Activitypub\Activity\Activity::init_from_array( $activity_data );

		Inbox::handle_inbox_requests( $activity_data, $user_id, $activity_type, $activity );

		$this->assertEquals( $expected_success, $was_successful, $description );

		\remove_all_actions( 'activitypub_handled_inbox' );
	}

	/**
	 * Data provider for inbox requests tests.
	 *
	 * @return array Test cases with activity data, type, expected success, and description.
	 */
	public function inbox_requests_provider() {
		return array(
			'create_note_success'       => array(
				array(
					'id'     => 'https://example.com/activity/1',
					'type'   => 'Create',
					'object' => array(
						'id'   => 'https://example.com/object/1',
						'type' => 'Note',
					),
					'actor'  => 'https://example.com/actor/1',
				),
				'create',
				true,
				'Should handle Create activity with Note object successfully',
			),
			'create_other_note_success' => array(
				array(
					'id'     => 'https://example.com/activity/1',
					'type'   => 'Create',
					'object' => 'https://example.com/object/1',
					'actor'  => 'https://example.com/actor/1',
				),
				'create',
				true,
				'Should handle Create activity with Note object successfully',
			),
			'create_note_no_type'       => array(
				array(
					'id'     => 'https://example.com/activity/1',
					'type'   => 'Create',
					'object' => array(
						'id' => 'https://example.com/object/1',
					),
					'actor'  => 'https://example.com/actor/1',
				),
				'create',
				true,
				'Should handle Create activity even with object missing type',
			),
			'create_person_success'     => array(
				array(
					'id'     => 'https://example.com/activity/2',
					'type'   => 'Create',
					'object' => array(
						'id'   => 'https://example.com/object/2',
						'type' => 'Person',
					),
					'actor'  => 'https://example.com/actor/2',
				),
				'create',
				true,
				'Should handle Create activity with Person object',
			),
			'delete_article_failure'    => array(
				array(
					'id'     => 'https://example.com/activity/3',
					'type'   => 'Delete',
					'object' => array(
						'id'   => 'https://example.com/object/3',
						'type' => 'Article',
					),
					'actor'  => 'https://example.com/actor/3',
				),
				'delete',
				false,
				'Should not handle Delete activity with Article object',
			),
			'update_article_success'    => array(
				array(
					'id'     => 'https://example.com/activity/4',
					'type'   => 'Update',
					'object' => array(
						'id'   => 'https://example.com/object/4',
						'type' => 'Article',
					),
					'actor'  => 'https://example.com/actor/4',
				),
				'update',
				true,
				'Should handle Update activity successfully',
			),
			'quote_request_success'     => array(
				array(
					'id'         => 'https://example.com/activity/4',
					'type'       => 'QuoteRequest',
					'actor'      => 'https://example.com/actor/4',
					'object'     => array(
						'id'   => 'https://example.com/object/4',
						'type' => 'Note',
					),
					'instrument' => array(
						'type'         => 'Note',
						'id'           => 'https://example.com/users/bob/statuses/1',
						'attributedTo' => 'https://example.com/users/bob',
						'to'           => array(
							'https://www.w3.org/ns/activitystreams#Public',
							'https://example.com/users/alice',
						),
						'content'      => "I am quoting alice's post<br/>RE: https://example.com/users/alice/statuses/1",
						'quote'        => 'https://example.com/users/alice/statuses/1',
					),
				),
				'quote_request',
				true,
				'Should handle QuoteRequest activity successfully',
			),
		);
	}

	/**
	 * Test handle_inbox_requests with multiple recipients.
	 */
	public function test_handle_inbox_requests_with_multiple_recipients() {
		$activity_data = array(
			'id'     => 'https://example.com/activity/multi',
			'type'   => 'Create',
			'object' => array(
				'id'   => 'https://example.com/object/multi',
				'type' => 'Note',
			),
			'actor'  => 'https://example.com/actor/multi',
		);

		$captured_user_ids = null;

		\add_action(
			'activitypub_handled_inbox',
			function ( $data, $user_ids ) use ( &$captured_user_ids ) {
				$captured_user_ids = $user_ids;
			},
			10,
			2
		);

		$activity = \Activitypub\Activity\Activity::init_from_array( $activity_data );

		// Test with array of recipients.
		Inbox::handle_inbox_requests( $activity_data, array( 1, 2, 3 ), 'create', $activity );

		$this->assertIsArray( $captured_user_ids );
		$this->assertCount( 3, $captured_user_ids );
		$this->assertContains( 1, $captured_user_ids );
		$this->assertContains( 2, $captured_user_ids );
		$this->assertContains( 3, $captured_user_ids );

		\remove_all_actions( 'activitypub_handled_inbox' );
	}

	/**
	 * Test handle_inbox_requests converts single user_id to array.
	 */
	public function test_handle_inbox_requests_normalizes_single_user_id() {
		$activity_data = array(
			'id'     => 'https://example.com/activity/single',
			'type'   => 'Create',
			'object' => array(
				'id'   => 'https://example.com/object/single',
				'type' => 'Note',
			),
			'actor'  => 'https://example.com/actor/single',
		);

		$captured_user_ids = null;

		\add_action(
			'activitypub_handled_inbox',
			function ( $data, $user_ids ) use ( &$captured_user_ids ) {
				$captured_user_ids = $user_ids;
			},
			10,
			2
		);

		$activity = \Activitypub\Activity\Activity::init_from_array( $activity_data );

		// Test with single user_id (backward compatibility).
		Inbox::handle_inbox_requests( $activity_data, 1, 'create', $activity );

		$this->assertIsArray( $captured_user_ids );
		$this->assertCount( 1, $captured_user_ids );
		$this->assertEquals( 1, $captured_user_ids[0] );

		\remove_all_actions( 'activitypub_handled_inbox' );
	}

	/**
	 * Test context parameter is passed correctly to action hooks.
	 */
	public function test_context_parameter_in_action_hooks() {
		$activity_data = array(
			'id'     => 'https://example.com/activity/context',
			'type'   => 'Create',
			'object' => array(
				'id'   => 'https://example.com/object/context',
				'type' => 'Note',
			),
			'actor'  => 'https://example.com/actor/context',
		);

		$captured_context = null;

		\add_action(
			'activitypub_handled_inbox',
			function ( $data, $user_ids, $type, $activity, $inbox_id, $context ) use ( &$captured_context ) {
				$captured_context = $context;
			},
			10,
			6
		);

		$activity = \Activitypub\Activity\Activity::init_from_array( $activity_data );

		// Test with inbox context.
		Inbox::handle_inbox_requests( $activity_data, 1, 'create', $activity, Inbox_Collection::CONTEXT_INBOX );
		$this->assertEquals( Inbox_Collection::CONTEXT_INBOX, $captured_context );

		// Test with shared inbox context.
		$captured_context = null;
		Inbox::handle_inbox_requests( $activity_data, array( 1, 2 ), 'create', $activity, Inbox_Collection::CONTEXT_SHARED_INBOX );
		$this->assertEquals( Inbox_Collection::CONTEXT_SHARED_INBOX, $captured_context );

		\remove_all_actions( 'activitypub_handled_inbox' );
	}

	/**
	 * Test type-specific action hook is fired.
	 */
	public function test_type_specific_action_hook() {
		$activity_data = array(
			'id'     => 'https://example.com/activity/type-specific',
			'type'   => 'Create',
			'object' => array(
				'id'   => 'https://example.com/object/type-specific',
				'type' => 'Note',
			),
			'actor'  => 'https://example.com/actor/type-specific',
		);

		$hook_fired = false;

		\add_action(
			'activitypub_handled_inbox_create',
			function () use ( &$hook_fired ) {
				$hook_fired = true;
			}
		);

		$activity = \Activitypub\Activity\Activity::init_from_array( $activity_data );

		Inbox::handle_inbox_requests( $activity_data, 1, 'create', $activity );

		$this->assertTrue( $hook_fired, 'Type-specific action hook should fire' );

		\remove_all_actions( 'activitypub_handled_inbox_create' );
	}

	/**
	 * Test maybe_handle_inbox_request filters out shared inbox context.
	 */
	public function test_maybe_handle_inbox_request_filters_shared_inbox() {
		$activity_data = array(
			'id'     => 'https://example.com/activity/middleware',
			'type'   => 'Create',
			'object' => array(
				'id'   => 'https://example.com/object/middleware',
				'type' => 'Note',
			),
			'actor'  => 'https://example.com/actor/middleware',
		);

		$hook_fired = false;

		\add_action(
			'activitypub_handled_inbox',
			function () use ( &$hook_fired ) {
				$hook_fired = true;
			}
		);

		$activity = \Activitypub\Activity\Activity::init_from_array( $activity_data );

		// Test with shared inbox context - should be filtered out.
		Inbox::maybe_handle_inbox_request( $activity_data, array( 1, 2 ), 'create', $activity, Inbox_Collection::CONTEXT_SHARED_INBOX );

		$this->assertFalse( $hook_fired, 'Should not process shared inbox context' );

		// Test with inbox context - should process.
		Inbox::maybe_handle_inbox_request( $activity_data, 1, 'create', $activity, Inbox_Collection::CONTEXT_INBOX );

		$this->assertTrue( $hook_fired, 'Should process inbox context' );

		\remove_all_actions( 'activitypub_handled_inbox' );
	}

	/**
	 * Test handle_inbox_requests creates inbox item with correct recipients.
	 */
	public function test_handle_inbox_requests_creates_item_with_recipients() {
		$activity_data = array(
			'id'     => 'https://example.com/activity/recipients',
			'type'   => 'Create',
			'object' => array(
				'id'   => 'https://example.com/object/recipients',
				'type' => 'Note',
			),
			'actor'  => 'https://example.com/actor/recipients',
		);

		$result_id = null;

		\add_action(
			'activitypub_handled_inbox',
			// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			function ( $data, $user_ids, $type, $activity, $result, $context ) use ( &$result_id ) {
				$result_id = $result;
			},
			10,
			6
		);

		$activity = \Activitypub\Activity\Activity::init_from_array( $activity_data );

		Inbox::handle_inbox_requests( $activity_data, array( 1, 2, 0 ), 'create', $activity );

		$this->assertIsInt( $result_id );
		$this->assertGreaterThan( 0, $result_id );

		// Verify recipients were stored correctly.
		$recipients = Inbox_Collection::get_recipients( $result_id );
		$this->assertCount( 3, $recipients );
		$this->assertContains( 0, $recipients );
		$this->assertContains( 1, $recipients );
		$this->assertContains( 2, $recipients );

		\remove_all_actions( 'activitypub_handled_inbox' );
	}

	/**
	 * Test handle_inbox_requests with WP_Error activity.
	 */
	public function test_handle_inbox_requests_with_wp_error() {
		$activity_data = array(
			'id'     => 'https://example.com/activity/error',
			'type'   => 'Create',
			'object' => array(
				'id'   => 'https://example.com/object/error',
				'type' => 'Note',
			),
			'actor'  => 'https://example.com/actor/error',
		);

		$captured_result = null;

		\add_action(
			'activitypub_handled_inbox',
			// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			function ( $data, $user_ids, $type, $activity, $result, $context ) use ( &$captured_result ) {
				$captured_result = $result;
			},
			10,
			6
		);

		$error = new \WP_Error( 'test_error', 'Test error message' );

		Inbox::handle_inbox_requests( $activity_data, 1, 'create', $error );

		// Should still fire the action hook even with WP_Error activity.
		// In this case, validation should fail and result should be WP_Error.
		$this->assertInstanceOf( 'WP_Error', $captured_result );

		\remove_all_actions( 'activitypub_handled_inbox' );
	}

	/**
	 * Test that Delete activities are deferred and not persisted.
	 */
	public function test_delete_activity_is_deferred() {
		$activity_data = array(
			'id'     => 'https://example.com/activity/delete-deferred',
			'type'   => 'Delete',
			'object' => array(
				'id'   => 'https://example.com/object/delete-deferred',
				'type' => 'Tombstone',
			),
			'actor'  => 'https://example.com/actor/delete-deferred',
		);

		$captured_inbox_id = null;
		$hook_fired        = false;

		\add_action(
			'activitypub_handled_inbox',
			function ( $data, $user_ids, $type, $activity, $inbox_id ) use ( &$captured_inbox_id, &$hook_fired ) {
				$captured_inbox_id = $inbox_id;
				$hook_fired        = true;
			},
			10,
			5
		);

		$activity = \Activitypub\Activity\Activity::init_from_array( $activity_data );

		Inbox::handle_inbox_requests( $activity_data, 1, 'delete', $activity );

		// Hook should NOT fire because Delete is deferred via defer_inbox_storage filter.
		$this->assertFalse( $hook_fired, 'activitypub_handled_inbox should not fire for deferred Delete activities' );
		$this->assertNull( $captured_inbox_id, 'No inbox item should be created for Delete activities' );

		// Verify no inbox item was created.
		$posts = \get_posts(
			array(
				'post_type'   => Inbox_Collection::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
			)
		);

		foreach ( $posts as $post ) {
			$this->assertNotEquals( 'https://example.com/activity/delete-deferred', $post->guid, 'Delete activity should not be persisted' );
		}

		\remove_all_actions( 'activitypub_handled_inbox' );
	}

	/**
	 * Test that defer_inbox_storage filter works correctly.
	 */
	public function test_defer_inbox_storage_filter() {
		$activity_data = array(
			'id'     => 'https://example.com/activity/deferred',
			'type'   => 'Create',
			'object' => array(
				'id'   => 'https://example.com/object/deferred',
				'type' => 'Note',
			),
			'actor'  => 'https://example.com/actor/deferred',
		);

		// Add filter to defer all Create activities.
		\add_filter(
			'activitypub_skip_inbox_storage',
			function ( $defer, $data ) {
				if ( isset( $data['type'] ) && 'Create' === $data['type'] ) {
					return true;
				}
				return $defer;
			},
			10,
			2
		);

		$hook_fired = false;

		\add_action(
			'activitypub_handled_inbox',
			function () use ( &$hook_fired ) {
				$hook_fired = true;
			}
		);

		$activity = \Activitypub\Activity\Activity::init_from_array( $activity_data );

		Inbox::handle_inbox_requests( $activity_data, 1, 'create', $activity );

		// Hook should NOT fire when storage is deferred.
		$this->assertFalse( $hook_fired, 'Hook should not fire when storage is deferred' );

		\remove_all_filters( 'activitypub_skip_inbox_storage' );
		\remove_all_actions( 'activitypub_handled_inbox' );
	}
}
