<?php
/**
 * Test Scheduler class.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests;

use Activitypub\Scheduler;

/**
 * Test class for Scheduler.
 *
 * @coversDefaultClass \Activitypub\Scheduler
 */
class Test_Scheduler extends \WP_UnitTestCase {
	/**
	 * Test post.
	 *
	 * @var \WP_Post
	 */
	protected $post;

	/**
	 * Set up test resources before each test.
	 *
	 * Creates a test post in draft status.
	 */
	public function set_up() {
		parent::set_up();

		$this->post = self::factory()->post->create_and_get(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Test Content',
				'post_status'  => 'draft',
				'post_author'  => 1,
			)
		);
	}

	/**
	 * Clean up test resources after each test.
	 *
	 * Deletes the test post.
	 */
	public function tear_down() {
		wp_delete_post( $this->post->ID, true );
		parent::tear_down();
	}

	/**
	 * Test that moving a draft post to trash does not schedule federation.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_draft_to_trash_should_not_schedule_federation() {
		Scheduler::schedule_post_activity( 'trash', 'draft', $this->post );

		$this->assertFalse(
			wp_next_scheduled( 'activitypub_send_post', array( $this->post->ID, 'Delete' ) ),
			'Draft to trash transition should not schedule federation'
		);
	}

	/**
	 * Test that moving a published post to trash schedules a delete activity only if federated.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_publish_to_trash_should_schedule_delete_only_if_federated() {
		wp_publish_post( $this->post->ID );
		$this->post = get_post( $this->post->ID );

		// Test without federation state.
		Scheduler::schedule_post_activity( 'trash', 'publish', $this->post );
		$this->assertFalse(
			wp_next_scheduled( 'activitypub_send_post', array( $this->post->ID, 'Delete' ) ),
			'Published to trash transition should not schedule delete activity when not federated'
		);

		// Test with federation state.
		\Activitypub\set_wp_object_state( $this->post, 'federated' );
		Scheduler::schedule_post_activity( 'trash', 'publish', $this->post );

		$this->assertNotFalse(
			wp_next_scheduled( 'activitypub_send_post', array( $this->post->ID, 'Delete' ) ),
			'Published to trash transition should schedule delete activity when federated'
		);
	}

	/**
	 * Test that updating a draft post does not schedule federation.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_draft_to_draft_should_not_schedule_federation() {
		Scheduler::schedule_post_activity( 'draft', 'draft', $this->post );

		$this->assertFalse(
			wp_next_scheduled( 'activitypub_send_post', array( $this->post->ID, 'Update' ) ),
			'Draft to draft transition should not schedule federation'
		);
	}

	/**
	 * Test that moving a published post to draft schedules an update activity.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_publish_to_draft_should_schedule_update() {
		wp_publish_post( $this->post->ID );
		$this->post = get_post( $this->post->ID );
		Scheduler::schedule_post_activity( 'draft', 'publish', $this->post );

		$this->assertNotFalse(
			wp_next_scheduled( 'activitypub_send_post', array( $this->post->ID, 'Update' ) ),
			'Published to draft transition should schedule update activity'
		);
	}

	/**
	 * Test that publishing a draft post schedules a create activity.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_draft_to_publish_should_schedule_create() {
		Scheduler::schedule_post_activity( 'publish', 'draft', $this->post );

		$this->assertNotFalse(
			wp_next_scheduled( 'activitypub_send_post', array( $this->post->ID, 'Create' ) ),
			'Draft to publish transition should schedule create activity'
		);
	}

	/**
	 * Test that updating a published post schedules an update activity.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_publish_to_publish_should_schedule_update() {
		wp_publish_post( $this->post->ID );
		$this->post = get_post( $this->post->ID );
		Scheduler::schedule_post_activity( 'publish', 'publish', $this->post );

		$this->assertNotFalse(
			wp_next_scheduled( 'activitypub_send_post', array( $this->post->ID, 'Update' ) ),
			'Published to published transition should schedule update activity'
		);
	}

	/**
	 * Test that various non-standard status transitions do not schedule federation.
	 *
	 * Tests transitions from pending, private, and future statuses.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_other_status_transitions_should_not_schedule_federation() {
		// Test pending to draft.
		Scheduler::schedule_post_activity( 'draft', 'pending', $this->post );

		$this->assertFalse(
			wp_next_scheduled( 'activitypub_send_post', array( $this->post->ID, 'Update' ) ),
			'Pending to draft transition should not schedule federation'
		);

		// Test private to draft.
		Scheduler::schedule_post_activity( 'draft', 'private', $this->post );

		$this->assertFalse(
			wp_next_scheduled( 'activitypub_send_post', array( $this->post->ID, 'Update' ) ),
			'Private to draft transition should not schedule federation'
		);

		// Test future to draft.
		Scheduler::schedule_post_activity( 'draft', 'future', $this->post );

		$this->assertFalse(
			wp_next_scheduled( 'activitypub_send_post', array( $this->post->ID, 'Update' ) ),
			'Future to draft transition should not schedule federation'
		);
	}

	/**
	 * Test that disabled posts do not schedule federation activities.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_disabled_post_should_not_schedule_federation() {
		update_post_meta( $this->post->ID, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );
		Scheduler::schedule_post_activity( 'publish', 'draft', $this->post );

		$this->assertFalse(
			wp_next_scheduled( 'activitypub_send_post', array( $this->post->ID, 'Create' ) ),
			'Disabled posts should not schedule federation activities'
		);
	}

	/**
	 * Test that password protected posts do not schedule federation activities.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_password_protected_post_should_not_schedule_federation() {
		wp_update_post(
			array(
				'ID'            => $this->post->ID,
				'post_password' => 'test-password',
			)
		);
		$this->post = get_post( $this->post->ID );
		Scheduler::schedule_post_activity( 'publish', 'draft', $this->post );

		$this->assertFalse(
			wp_next_scheduled( 'activitypub_send_post', array( $this->post->ID, 'Create' ) ),
			'Password protected posts should not schedule federation activities'
		);
	}
}
