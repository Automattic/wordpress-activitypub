<?php
/**
 * Test Post scheduler class.
 *
 * @package Activitypub\Tests\Scheduler
 */

namespace Activitypub\Tests\Scheduler;

/**
 * Test Post scheduler class.
 *
 * @coversDefaultClass \Activitypub\Scheduler\Post
 */
class Test_Post extends \Activitypub\Tests\ActivityPub_Outbox_TestCase {

	/**
	 * Test post activity scheduling for regular posts.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_schedule_post_activity_regular_post() {
		$post_id       = self::factory()->post->create();
		$activitpub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		$post = $this->get_latest_outbox_item( $activitpub_id );
		$this->assertSame( $activitpub_id, $post->post_title );

		\wp_delete_post( $post_id, true );
	}

	/**
	 * Data provider for no activity tests.
	 *
	 * @return array[] Test parameters.
	 */
	public function no_activity_post_provider() {
		return array(
			'author_cannot_publish' => array(
				array( 'post_author' => 90210 ),
			),
			'password_protected'    => array(
				array( 'post_password' => 'test-password' ),
			),
			'unsupported_post_type' => array(
				array( 'post_type' => 'nav_menu_item' ),
			),
			'disabled_post'         => array(
				array(
					'meta_input' => array(
						'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL,
					),
				),
			),
		);
	}

	/**
	 * Test post activity scheduling under various conditions.
	 *
	 * @dataProvider no_activity_post_provider
	 *
	 * @param array $args Post data for creating the test post.
	 */
	public function test_no_activity_scheduled( $args ) {
		$post_id       = self::factory()->post->create( $args );
		$activitpub_id = \add_query_arg( 'p', $post_id, \home_url( '/' ) );

		$this->assertNull( $this->get_latest_outbox_item( $activitpub_id ) );

		\wp_delete_post( $post_id, true );
	}
}
