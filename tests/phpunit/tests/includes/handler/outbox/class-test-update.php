<?php
/**
 * Test file for Outbox Update Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler\Outbox;

use Activitypub\Handler\Outbox\Update;
use Activitypub\Scheduler\Post;

/**
 * Test class for Outbox Update Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Outbox\Update
 */
class Test_Update extends \WP_UnitTestCase {

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

		// Prevent wp_update_post() from triggering the full outbox chain.
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$this->user_id = self::factory()->user->create( array( 'role' => 'editor' ) );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );

		parent::tear_down();
	}

	/**
	 * Test outgoing Update with a Note updates the post.
	 *
	 * @covers ::handle_update
	 */
	public function test_outgoing_updates_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => $this->user_id,
				'post_title'   => 'Original Title',
				'post_content' => 'Original content',
				'post_status'  => 'publish',
			)
		);

		$permalink = \get_permalink( $post_id );

		$data = array(
			'type'   => 'Update',
			'object' => array(
				'type'    => 'Note',
				'id'      => $permalink,
				'content' => 'Updated content',
				'name'    => 'Updated Title',
				'summary' => 'Updated summary',
			),
		);

		Update::handle_update( $data, $this->user_id );

		$post = \get_post( $post_id );
		$this->assertEquals( 'Updated Title', $post->post_title );
		$this->assertEquals( 'Updated content', $post->post_content );
		$this->assertEquals( 'Updated summary', $post->post_excerpt );
	}

	/**
	 * Test outgoing Update generates title from content for Notes without name.
	 *
	 * @covers ::handle_update
	 */
	public function test_outgoing_generates_title_from_content() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);

		$permalink = \get_permalink( $post_id );

		$data = array(
			'type'   => 'Update',
			'object' => array(
				'type'    => 'Note',
				'id'      => $permalink,
				'content' => 'This is a short note without a title field.',
			),
		);

		Update::handle_update( $data, $this->user_id );

		$post = \get_post( $post_id );
		$this->assertNotEmpty( $post->post_title );
		$this->assertStringContainsString( 'This is a short', $post->post_title );
	}

	/**
	 * Test outgoing Update ignores non-Note/Article types.
	 *
	 * @covers ::handle_update
	 */
	public function test_outgoing_ignores_unsupported_types() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_title'  => 'Original',
				'post_status' => 'publish',
			)
		);

		$permalink = \get_permalink( $post_id );

		$data = array(
			'type'   => 'Update',
			'object' => array(
				'type'    => 'Event',
				'id'      => $permalink,
				'content' => 'Should not update',
			),
		);

		Update::handle_update( $data, $this->user_id );

		$post = \get_post( $post_id );
		$this->assertEquals( 'Original', $post->post_title );
	}

	/**
	 * Test outgoing Update skips posts not owned by user.
	 *
	 * @covers ::handle_update
	 */
	public function test_outgoing_skips_unowned_post() {
		$other_user = self::factory()->user->create();
		$post_id    = self::factory()->post->create(
			array(
				'post_author' => $other_user,
				'post_title'  => 'Other User Post',
				'post_status' => 'publish',
			)
		);

		$permalink = \get_permalink( $post_id );

		$data = array(
			'type'   => 'Update',
			'object' => array(
				'type'    => 'Note',
				'id'      => $permalink,
				'content' => 'Should not update',
				'name'    => 'Hijacked',
			),
		);

		Update::handle_update( $data, $this->user_id );

		$post = \get_post( $post_id );
		$this->assertEquals( 'Other User Post', $post->post_title );
	}

	/**
	 * Test outgoing Update returns early for non-array object.
	 *
	 * @covers ::handle_update
	 */
	public function test_outgoing_returns_early_for_string_object() {
		$data = array(
			'type'   => 'Update',
			'object' => 'https://example.com/note/1',
		);

		// Should not throw errors.
		Update::handle_update( $data, $this->user_id );
		$this->assertTrue( true );
	}

	/**
	 * Test outgoing Update returns early for empty object ID.
	 *
	 * @covers ::handle_update
	 */
	public function test_outgoing_returns_early_for_empty_id() {
		$data = array(
			'type'   => 'Update',
			'object' => array(
				'type'    => 'Note',
				'content' => 'No ID provided',
			),
		);

		// Should not throw errors.
		Update::handle_update( $data, $this->user_id );
		$this->assertTrue( true );
	}

	/**
	 * Test outgoing Update fires action hook on success.
	 *
	 * @covers ::handle_update
	 */
	public function test_outgoing_fires_action() {
		$post_id = self::factory()->post->create(
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
		\add_action( 'activitypub_outbox_updated_post', $callback );

		$data = array(
			'type'   => 'Update',
			'object' => array(
				'type'    => 'Note',
				'id'      => $permalink,
				'content' => 'Updated',
			),
		);

		Update::handle_update( $data, $this->user_id );

		$this->assertTrue( $fired, 'activitypub_outbox_updated_post action should fire.' );

		\remove_action( 'activitypub_outbox_updated_post', $callback );
	}
}
