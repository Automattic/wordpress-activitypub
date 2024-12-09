<?php
/**
 * Test Akismet Integration.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Integration\Akismet;
use WP_UnitTestCase;

/**
 * Test Akismet Integration class.
 *
 * @coversDefaultClass \Activitypub\Integration\Akismet
 */
class Test_Akismet extends WP_UnitTestCase {
	/**
	 * A test post.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$post_id = $factory->post->create();
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post_id, true );
	}

	/**
	 * Test comment_row_actions method.
	 *
	 * @covers ::comment_row_actions
	 */
	public function test_comment_row_actions() {
		// Create a normal comment.
		$normal_comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_content' => 'Normal comment',
			)
		);

		// Create an ActivityPub comment.
		$ap_comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_content' => 'ActivityPub comment',
			)
		);
		add_comment_meta( $ap_comment_id, 'protocol', 'activitypub' );

		// Test actions for normal comment.
		$actions = array(
			'approve' => 'Approve',
			'spam'    => 'Spam',
			'history' => 'History',
		);

		$filtered_actions = Akismet::comment_row_actions( $actions, get_comment( $normal_comment_id ) );
		$this->assertArrayHasKey( 'history', $filtered_actions, 'History action should remain for normal comments' );

		// Test actions for ActivityPub comment.
		$filtered_actions = Akismet::comment_row_actions( $actions, get_comment( $ap_comment_id ) );
		$this->assertArrayNotHasKey( 'history', $filtered_actions, 'History action should be removed for ActivityPub comments' );
		$this->assertArrayHasKey( 'approve', $filtered_actions, 'Other actions should remain untouched' );
		$this->assertArrayHasKey( 'spam', $filtered_actions, 'Other actions should remain untouched' );

		// Clean up.
		wp_delete_comment( $normal_comment_id, true );
		wp_delete_comment( $ap_comment_id, true );
	}

	/**
	 * Test init method.
	 *
	 * @covers ::init
	 */
	public function test_init() {
		Akismet::init();

		$this->assertEquals( 10, has_filter( 'comment_row_actions', array( Akismet::class, 'comment_row_actions' ) ) );
	}
}
