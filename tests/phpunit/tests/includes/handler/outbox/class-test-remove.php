<?php
/**
 * Test file for Outbox Remove Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler\Outbox;

use Activitypub\Collection\Actors;
use Activitypub\Handler\Outbox\Remove;

/**
 * Test class for Outbox Remove Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Outbox\Remove
 */
class Test_Remove extends \WP_UnitTestCase {

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

		$this->user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$user = \get_user_by( 'id', $this->user_id );
		$user->add_cap( 'activitypub' );

		// Prevent outbox processing from dispatching during tests.
		\remove_all_actions( 'activitypub_process_outbox' );
	}

	/**
	 * Test that handle_remove unsticks a post and adds to outbox.
	 *
	 * @covers ::handle_remove
	 */
	public function test_handle_remove_unsticks_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);

		// Make the post sticky first.
		\stick_post( $post_id );
		$this->assertTrue( \is_sticky( $post_id ), 'Post should be sticky before Remove.' );

		$actor = Actors::get_by_id( $this->user_id );

		$data = array(
			'type'   => 'Remove',
			'object' => \get_permalink( $post_id ),
			'target' => $actor->get_featured(),
		);

		$result = Remove::handle_remove( $data, $this->user_id );

		// Should return an outbox post ID.
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		// Verify the post is no longer sticky.
		$this->assertFalse(
			\is_sticky( $post_id ),
			'Post should not be sticky after Remove.'
		);
	}

	/**
	 * Test that handle_remove returns data for empty object.
	 *
	 * @covers ::handle_remove
	 */
	public function test_handle_remove_empty_object() {
		$data = array(
			'type'   => 'Remove',
			'object' => '',
			'target' => 'https://example.com/featured',
		);

		$result = Remove::handle_remove( $data, $this->user_id );

		$this->assertSame( $data, $result, 'Should return original data for empty object.' );
	}

	/**
	 * Test that handle_remove returns data for empty target.
	 *
	 * @covers ::handle_remove
	 */
	public function test_handle_remove_empty_target() {
		$data = array(
			'type'   => 'Remove',
			'object' => 'https://example.com/post/1',
			'target' => '',
		);

		$result = Remove::handle_remove( $data, $this->user_id );

		$this->assertSame( $data, $result, 'Should return original data for empty target.' );
	}

	/**
	 * Test that handle_remove ignores non-featured targets.
	 *
	 * @covers ::handle_remove
	 */
	public function test_handle_remove_non_featured_target() {
		$data = array(
			'type'   => 'Remove',
			'object' => 'https://example.com/post/1',
			'target' => 'https://example.com/some-other-collection',
		);

		$result = Remove::handle_remove( $data, $this->user_id );

		$this->assertSame( $data, $result, 'Should return original data for non-featured target.' );
	}

	/**
	 * Test that handle_remove rejects other users posts.
	 *
	 * @covers ::handle_remove
	 */
	public function test_handle_remove_forbidden_for_other_users_post() {
		$other_user = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id    = self::factory()->post->create(
			array(
				'post_author' => $other_user,
				'post_status' => 'publish',
			)
		);

		\stick_post( $post_id );

		$actor = Actors::get_by_id( $this->user_id );

		$data = array(
			'type'   => 'Remove',
			'object' => \get_permalink( $post_id ),
			'target' => $actor->get_featured(),
		);

		$result = Remove::handle_remove( $data, $this->user_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_forbidden', $result->get_error_code() );
	}

	/**
	 * Test that handle_remove returns error for non-existent post.
	 *
	 * @covers ::handle_remove
	 */
	public function test_handle_remove_post_not_found() {
		$actor = Actors::get_by_id( $this->user_id );

		$data = array(
			'type'   => 'Remove',
			'object' => \home_url( '/non-existent-post/' ),
			'target' => $actor->get_featured(),
		);

		$result = Remove::handle_remove( $data, $this->user_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_object_not_found', $result->get_error_code() );
	}

	/**
	 * Test that init registers the filter.
	 *
	 * @covers ::init
	 */
	public function test_init_registers_filter() {
		Remove::init();

		$this->assertNotFalse(
			\has_filter( 'activitypub_outbox_remove', array( Remove::class, 'handle_remove' ) ),
			'Filter should be registered.'
		);
	}
}
