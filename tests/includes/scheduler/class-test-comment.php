<?php
/**
 * Test Comment scheduler class.
 *
 * @package Activitypub\Tests\Scheduler
 */

namespace Activitypub\Tests\Scheduler;

/**
 * Test Comment scheduler class.
 *
 * @coversDefaultClass \Activitypub\Scheduler\Comment
 */
class Test_Comment extends \Activitypub\Tests\ActivityPub_Outbox_TestCase {

	/**
	 * Post ID for testing.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Set up test resources.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$post_id = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
	}

	/**
	 * Clean up test resources.
	 */
	public static function tear_down_after_class() {
		wp_delete_post( self::$post_id, true );
	}

	/**
	 * Test scheduling comment activity on approval.
	 */
	public function test_schedule_comment_activity_on_approval() {
		$comment_id    = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$user_id,
				'comment_approved' => 0,
			)
		);
		$activitpub_id = \Activitypub\Comment::generate_id( $comment_id );

		wp_set_comment_status( $comment_id, 'approve' );

		$post = $this->get_latest_outbox_item( $activitpub_id );
		$this->assertSame( $activitpub_id, $post->post_title );

		wp_delete_comment( $comment_id, true );
	}

	/**
	 * Test scheduling comment activity on direct insert with approval.
	 */
	public function test_schedule_comment_activity_on_insert() {
		$comment_id    = self::factory()->comment->create(
			array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$user_id,
				'comment_approved' => 1,
			)
		);
		$activitpub_id = \Activitypub\Comment::generate_id( $comment_id );

		$post = $this->get_latest_outbox_item( $activitpub_id );
		$this->assertSame( $activitpub_id, $post->post_title );

		wp_delete_comment( $comment_id, true );
	}

	/**
	 * Data provider for no activity tests.
	 *
	 * @return array[] Test parameters.
	 */
	public function no_activity_comment_provider() {
		return array(
			'unapproved_comment'  => array(
				array(
					'comment_post_ID'  => self::$post_id,
					'user_id'          => self::$user_id,
					'comment_approved' => 0,
				),
			),
			'non_registered_user' => array(
				array(
					'comment_post_ID'  => self::$post_id,
					'comment_approved' => 1,
				),
			),
			'federation_disabled' => array(
				array(
					'comment_post_ID'  => self::$post_id,
					'user_id'          => self::$user_id,
					'comment_approved' => 1,
					'comment_meta'     => array(
						'protocol' => 'activitypub',
					),
				),
			),
		);
	}

	/**
	 * Test comment activity scheduling under various conditions.
	 *
	 * @dataProvider no_activity_comment_provider
	 *
	 * @param array $comment_data   Comment data for creating the test comment.
	 */
	public function test_no_activity_scheduled( $comment_data ) {
		$comment_id    = self::factory()->comment->create( $comment_data );
		$activitpub_id = \Activitypub\Comment::generate_id( $comment_id );

		$this->assertNull( $this->get_latest_outbox_item( $activitpub_id ) );

		wp_delete_comment( $comment_id, true );
	}
}
