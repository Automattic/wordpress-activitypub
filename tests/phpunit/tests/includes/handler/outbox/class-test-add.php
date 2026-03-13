<?php
/**
 * Test file for Outbox Add Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler\Outbox;

use Activitypub\Collection\Actors;
use Activitypub\Handler\Outbox\Add;

/**
 * Test class for Outbox Add Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Outbox\Add
 */
class Test_Add extends \WP_UnitTestCase {

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
	 * Test that handle_add makes a post sticky and adds to outbox.
	 *
	 * @covers ::handle_add
	 */
	public function test_handle_add_makes_post_sticky() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);

		$actor = Actors::get_by_id( $this->user_id );

		$data = array(
			'type'   => 'Add',
			'object' => \get_permalink( $post_id ),
			'target' => $actor->get_featured(),
		);

		$result = Add::handle_add( $data, $this->user_id );

		// Should return an outbox post ID.
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		// Verify the post is now sticky.
		$this->assertTrue(
			\is_sticky( $post_id ),
			'Post should be sticky after Add.'
		);
	}

	/**
	 * Test that handle_add returns data for empty object.
	 *
	 * @covers ::handle_add
	 */
	public function test_handle_add_empty_object() {
		$data = array(
			'type'   => 'Add',
			'object' => '',
			'target' => 'https://example.com/featured',
		);

		$result = Add::handle_add( $data, $this->user_id );

		$this->assertSame( $data, $result, 'Should return original data for empty object.' );
	}

	/**
	 * Test that handle_add returns data for empty target.
	 *
	 * @covers ::handle_add
	 */
	public function test_handle_add_empty_target() {
		$data = array(
			'type'   => 'Add',
			'object' => 'https://example.com/post/1',
			'target' => '',
		);

		$result = Add::handle_add( $data, $this->user_id );

		$this->assertSame( $data, $result, 'Should return original data for empty target.' );
	}

	/**
	 * Test that handle_add ignores non-featured targets.
	 *
	 * @covers ::handle_add
	 */
	public function test_handle_add_non_featured_target() {
		$data = array(
			'type'   => 'Add',
			'object' => 'https://example.com/post/1',
			'target' => 'https://example.com/some-other-collection',
		);

		$result = Add::handle_add( $data, $this->user_id );

		$this->assertSame( $data, $result, 'Should return original data for non-featured target.' );
	}

	/**
	 * Test that handle_add rejects other users posts.
	 *
	 * @covers ::handle_add
	 */
	public function test_handle_add_forbidden_for_other_users_post() {
		$other_user = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id    = self::factory()->post->create(
			array(
				'post_author' => $other_user,
				'post_status' => 'publish',
			)
		);

		$actor = Actors::get_by_id( $this->user_id );

		$data = array(
			'type'   => 'Add',
			'object' => \get_permalink( $post_id ),
			'target' => $actor->get_featured(),
		);

		$result = Add::handle_add( $data, $this->user_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_forbidden', $result->get_error_code() );
	}

	/**
	 * Test that handle_add returns error for non-existent post.
	 *
	 * @covers ::handle_add
	 */
	public function test_handle_add_post_not_found() {
		$actor = Actors::get_by_id( $this->user_id );

		$data = array(
			'type'   => 'Add',
			'object' => \home_url( '/non-existent-post/' ),
			'target' => $actor->get_featured(),
		);

		$result = Add::handle_add( $data, $this->user_id );

		$this->assertWPError( $result );
		$this->assertEquals( 'activitypub_object_not_found', $result->get_error_code() );
	}

	/**
	 * Test that init registers the filter.
	 *
	 * @covers ::init
	 */
	public function test_init_registers_filter() {
		Add::init();

		$this->assertNotFalse(
			\has_filter( 'activitypub_outbox_add', array( Add::class, 'handle_add' ) ),
			'Filter should be registered.'
		);
	}
}
