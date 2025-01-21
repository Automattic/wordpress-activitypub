<?php
/**
 * Test Comment scheduler class.
 *
 * @package Activitypub\Tests\Scheduler
 */

namespace Activitypub\Tests\Scheduler;

use Activitypub\Collection\Outbox;
use Activitypub\Scheduler\Comment;

/**
 * Test Comment scheduler class.
 *
 * @coversDefaultClass \Activitypub\Scheduler\Comment
 */
class Test_Comment extends \WP_UnitTestCase {
	/**
	 * User ID for testing.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Post ID for testing.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Set up test resources.
	 *
	 * @param \WP_UnitTest_Factory $factory Factory object.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create( array( 'role' => 'author' ) );
		self::$post_id = $factory->post->create( array( 'post_author' => self::$user_id ) );

		// Add activitypub capability to the user.
		get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Clean up test resources.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post_id, true );
		wp_delete_user( self::$user_id );
	}

	/**
	 * Test initialization of hooks.
	 */
	public function test_init() {
		$this->assertSame(
			20,
			has_action( 'transition_comment_status', array( Comment::class, 'schedule_comment_activity' ) )
		);
		$this->assertSame(
			10,
			has_action( 'wp_insert_comment', array( Comment::class, 'schedule_comment_activity_on_insert' ) )
		);
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

		$post = $this->get_leatest_outbox_item( $activitpub_id );
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

		$post = $this->get_leatest_outbox_item( $activitpub_id );
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
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$user_id,
				'comment_approved' => 0,
			),
			'non_registered_user' => array(
				'comment_post_ID'  => self::$post_id,
				'comment_approved' => 1,
			),
			'federation_disabled' => array(
				'comment_post_ID'  => self::$post_id,
				'user_id'          => self::$user_id,
				'comment_approved' => 1,
				'comment_meta'     => array(
					'protocol' => 'activitypub',
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

		$post = $this->get_leatest_outbox_item( $activitpub_id );
		$this->assertNotEquals( $activitpub_id, $post->post_title );

		wp_delete_comment( $comment_id, true );
	}

	/**
	 * Retrieve the latest Outbox item to compare against.
	 *
	 * @param string $title Title of the Outbox item.
	 * @return int|\WP_Post|null
	 */
	private function get_leatest_outbox_item( $title = '' ) {
		$outbox = get_posts(
			array(
				'post_type'      => Outbox::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'draft',
				'post_title'     => $title,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return $outbox ? $outbox[0] : null;
	}
}
