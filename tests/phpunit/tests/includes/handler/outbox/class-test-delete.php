<?php
/**
 * Test file for Outbox Delete Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler\Outbox;

use Activitypub\Handler\Outbox\Delete;
use Activitypub\Scheduler\Post;

/**
 * Test class for Outbox Delete Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Outbox\Delete
 */
class Test_Delete extends \WP_UnitTestCase {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		// Prevent wp_trash_post from triggering the full outbox chain.
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$this->user_id = self::factory()->user->create();
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );

		parent::tear_down();
	}

	/**
	 * Test outgoing Delete trashes the post.
	 *
	 * @covers ::handle_delete
	 */
	public function test_handle_delete_trashes_post() {
		$post_id   = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);
		$permalink = \get_permalink( $post_id );

		$data = array(
			'type'   => 'Delete',
			'object' => $permalink,
		);

		Delete::handle_delete( $data, $this->user_id );

		$post = \get_post( $post_id );
		$this->assertEquals( 'trash', $post->post_status );
	}

	/**
	 * Test outgoing Delete fires action hook.
	 *
	 * @covers ::handle_delete
	 */
	public function test_handle_delete_fires_action() {
		$post_id   = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);
		$permalink = \get_permalink( $post_id );
		$fired     = false;

		$callback = function () use ( &$fired ) {
			$fired = true;
		};
		\add_action( 'activitypub_outbox_deleted_post', $callback );

		$data = array(
			'type'   => 'Delete',
			'object' => $permalink,
		);

		Delete::handle_delete( $data, $this->user_id );

		$this->assertTrue( $fired, 'activitypub_outbox_deleted_post action should fire.' );

		\remove_action( 'activitypub_outbox_deleted_post', $callback );
	}

	/**
	 * Test outgoing Delete skips posts not owned by user.
	 *
	 * @covers ::handle_delete
	 */
	public function test_handle_delete_skips_unowned_post() {
		$other_user = self::factory()->user->create();
		$post_id    = self::factory()->post->create(
			array(
				'post_author' => $other_user,
				'post_status' => 'publish',
			)
		);
		$permalink  = \get_permalink( $post_id );

		$data = array(
			'type'   => 'Delete',
			'object' => $permalink,
		);

		Delete::handle_delete( $data, $this->user_id );

		$post = \get_post( $post_id );
		$this->assertEquals( 'publish', $post->post_status, 'Post should not be trashed by non-owner.' );
	}

	/**
	 * Test outgoing Delete with empty object does nothing.
	 *
	 * @covers ::handle_delete
	 */
	public function test_handle_delete_empty_object() {
		$data = array(
			'type'   => 'Delete',
			'object' => '',
		);

		// Should not throw errors.
		Delete::handle_delete( $data, $this->user_id );
		$this->assertTrue( true );
	}

	/**
	 * Test outgoing Delete with non-existent post does nothing.
	 *
	 * @covers ::handle_delete
	 */
	public function test_handle_delete_nonexistent_post() {
		$data = array(
			'type'   => 'Delete',
			'object' => 'https://example.com/nonexistent-post',
		);

		// Should not throw errors.
		Delete::handle_delete( $data, $this->user_id );
		$this->assertTrue( true );
	}

	/**
	 * Test outgoing Delete with object as array extracts ID.
	 *
	 * @covers ::handle_delete
	 */
	public function test_handle_delete_with_object_array() {
		$post_id   = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);
		$permalink = \get_permalink( $post_id );

		$data = array(
			'type'   => 'Delete',
			'object' => array(
				'type' => 'Tombstone',
				'id'   => $permalink,
			),
		);

		Delete::handle_delete( $data, $this->user_id );

		$post = \get_post( $post_id );
		$this->assertEquals( 'trash', $post->post_status );
	}

	/**
	 * Test that init registers the filter.
	 *
	 * @covers ::init
	 */
	public function test_init_registers_filter() {
		Delete::init();

		$this->assertNotFalse(
			\has_filter( 'activitypub_outbox_delete', array( Delete::class, 'handle_delete' ) ),
			'Filter should be registered.'
		);
	}
}
