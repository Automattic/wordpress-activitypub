<?php
/**
 * Test file for Activitypub Like Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Handler\Like;

/**
 * Test class for Activitypub Like Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Like
 */
class Test_Like extends \WP_UnitTestCase {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * User URL.
	 *
	 * @var string
	 */
	public $user_url;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public $post_id;

	/**
	 * Post permalink.
	 *
	 * @var string
	 */
	public $post_permalink;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id  = 1;
		$authordata     = \get_userdata( $this->user_id );
		$this->user_url = $authordata->user_url;

		$this->post_id        = \wp_insert_post(
			array(
				'post_author'  => $this->user_id,
				'post_content' => 'test',
			)
		);
		$this->post_permalink = \get_permalink( $this->post_id );

		\add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'get_remote_metadata_by_actor' ), 0, 2 );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'get_remote_metadata_by_actor' ) );
		parent::tear_down();
	}

	/**
	 * Get remote metadata by actor.
	 *
	 * @param string $value The value.
	 * @param string $actor The actor.
	 * @return array The metadata.
	 */
	public static function get_remote_metadata_by_actor( $value, $actor ) {
		return array(
			'name' => 'Example User',
			'icon' => array(
				'url' => 'https://example.com/icon',
			),
			'url'  => $actor,
			'id'   => 'http://example.org/users/example',
		);
	}

	/**
	 * Create a test object.
	 *
	 * @return array The test object.
	 */
	public function create_test_object() {
		return array(
			'actor'  => $this->user_url,
			'type'   => 'Like',
			'id'     => 'https://example.com/id/' . microtime( true ),
			'to'     => array( $this->user_url ),
			'cc'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => $this->post_permalink,
		);
	}

	/**
	 * Test handle_like with different scenarios.
	 *
	 * @dataProvider handle_like_provider
	 * @covers ::handle_like
	 *
	 * @param array  $activity_data      The like activity data.
	 * @param bool   $should_create_comment Whether a comment should be created.
	 * @param string $description        Description of the test case.
	 */
	public function test_handle_like( $activity_data, $should_create_comment, $description ) {
		// Create the activity using provided data or defaults.
		$activity = array_merge( $this->create_test_object(), $activity_data );

		// Get comment count before.
		$comments_before = \get_comments(
			array(
				'type'    => 'like',
				'post_id' => $this->post_id,
			)
		);
		$count_before    = count( $comments_before );

		// Process the like.
		Like::handle_like( $activity, $this->user_id );

		// Check comment count after.
		$comments_after = \get_comments(
			array(
				'type'    => 'like',
				'post_id' => $this->post_id,
			)
		);
		$count_after    = count( $comments_after );

		if ( $should_create_comment ) {
			$this->assertEquals( $count_before + 1, $count_after, $description . ' - Should create like comment' );
			$this->assertInstanceOf( 'WP_Comment', $comments_after[0], $description . ' - Should create WP_Comment object' );
		} else {
			$this->assertEquals( $count_before, $count_after, $description . ' - Should not create like comment' );
		}
	}

	/**
	 * Data provider for handle_like tests.
	 *
	 * @return array Test cases with activity data, expected result, and description.
	 */
	public function handle_like_provider() {
		return array(
			'valid_like'             => array(
				array(), // Use default test object.
				true,
				'Valid like activity should create comment',
			),
			'like_with_different_id' => array(
				array(
					'id' => 'https://example.com/different-like-id',
				),
				true,
				'Like with different ID should create comment',
			),
			'like_empty_object'      => array(
				array(
					'object' => '',
				),
				false,
				'Like with empty object should not create comment',
			),
			'like_null_object'       => array(
				array(
					'object' => null,
				),
				false,
				'Like with null object should not create comment',
			),
		);
	}

	/**
	 * Test duplicate like handling.
	 *
	 * @covers ::handle_like
	 */
	public function test_handle_like_duplicate() {
		$activity = array_merge(
			$this->create_test_object(),
			array( 'id' => 'https://example.com/duplicate-test' )
		);

		// Process the like first time.
		Like::handle_like( $activity, $this->user_id );

		$comments_after_first = \get_comments(
			array(
				'type'    => 'like',
				'post_id' => $this->post_id,
			)
		);
		$count_after_first    = count( $comments_after_first );

		// Process the same like again.
		Like::handle_like( $activity, $this->user_id );

		$comments_after_second = \get_comments(
			array(
				'type'    => 'like',
				'post_id' => $this->post_id,
			)
		);
		$count_after_second    = count( $comments_after_second );

		$this->assertEquals( $count_after_first, $count_after_second, 'Duplicate like should not create additional comment' );
	}

	/**
	 * Test handle_like action hook fires.
	 *
	 * @covers ::handle_like
	 */
	public function test_handle_like_action_hook() {
		$hook_fired    = false;
		$hook_activity = null;
		$hook_user_id  = null;
		$hook_success  = null;
		$hook_result   = null;

		\add_action(
			'activitypub_handled_like',
			function ( $activity, $user_id, $success, $result ) use ( &$hook_fired, &$hook_activity, &$hook_user_id, &$hook_success, &$hook_result ) {
				$hook_fired    = true;
				$hook_activity = $activity;
				$hook_user_id  = $user_id;
				$hook_success  = $success;
				$hook_result   = $result;
			},
			10,
			4
		);

		$activity = $this->create_test_object();
		Like::handle_like( $activity, $this->user_id );

		// Verify hook was fired.
		$this->assertTrue( $hook_fired, 'Action hook should be fired' );
		$this->assertEquals( $activity, $hook_activity, 'Activity data should match' );
		$this->assertIsArray( $hook_user_id, 'User ID should be an array' );
		$this->assertContains( $this->user_id, $hook_user_id, 'Array should contain user ID' );
		$this->assertTrue( $hook_success, 'Success should be true' );
		$this->assertInstanceOf( 'WP_Comment', $hook_result, 'Result should be WP_Comment' );

		// Clean up.
		\remove_all_actions( 'activitypub_handled_like' );
	}

	/**
	 * Test outbox_activity method with Like activity.
	 *
	 * @covers ::outbox_activity
	 */
	public function test_outbox_activity() {
		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( 'Like' );
		$activity->set_object( array( 'id' => 'https://example.com/post/123' ) );

		$result = Like::outbox_activity( $activity );

		// Verify the object was converted to URI.
		$this->assertSame( $activity, $result, 'Should return the same activity object' );
		$this->assertEquals( 'https://example.com/post/123', $activity->get_object(), 'Object should be converted to URI' );
	}

	/**
	 * Test outbox_activity with non-Like activity.
	 *
	 * @covers ::outbox_activity
	 */
	public function test_outbox_activity_non_like() {
		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( 'Follow' );
		$activity->set_object( array( 'id' => 'https://example.com/user/123' ) );

		$original_object = $activity->get_object();
		$result          = Like::outbox_activity( $activity );

		// Verify the object was not changed for non-Like activities.
		$this->assertSame( $activity, $result, 'Should return the same activity object' );
		$this->assertEquals( $original_object, $activity->get_object(), 'Object should remain unchanged for non-Like activity' );
	}
}
