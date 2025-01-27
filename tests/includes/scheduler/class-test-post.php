<?php
/**
 * Test Post scheduler class.
 *
 * @package Activitypub\Tests\Scheduler
 */

namespace Activitypub\Tests\Scheduler;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Extra_Fields;

/**
 * Test Post scheduler class.
 *
 * @coversDefaultClass \Activitypub\Scheduler\Post
 */
class Test_Post extends \WP_UnitTestCase {
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
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		// Add activitypub capability to the user.
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );

		\add_filter( 'pre_schedule_event', '__return_false' );
	}

	/**
	 * Clean up test resources.
	 */
	public static function tear_down_after_class() {
		\wp_delete_user( self::$user_id );
		\remove_filter( 'pre_schedule_event', '__return_false' );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		$outbox_items = get_posts(
			array(
				'post_type'      => Outbox::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			)
		);

		foreach ( $outbox_items as $outbox_item ) {
			\wp_delete_post( $outbox_item, true );
		}
	}

	/**
	 * Test post activity scheduling for regular posts.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_schedule_post_activity_regular_post() {
		$post_id       = self::factory()->post->create();
		$activitpub_id = \add_query_arg( 'p', $post_id, \trailingslashit( \home_url() ) );

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
		$activitpub_id = \add_query_arg( 'p', $post_id, \trailingslashit( \home_url() ) );

		$this->assertNull( $this->get_latest_outbox_item( $activitpub_id ) );

		\wp_delete_post( $post_id, true );
	}

	/**
	 * Retrieve the latest Outbox item to compare against.
	 *
	 * @param string $title Title of the Outbox item.
	 * @return int|\WP_Post|null
	 */
	private function get_latest_outbox_item( $title = '' ) {
		$outbox = get_posts(
			array(
				'post_type'      => Outbox::POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'pending',
				'post_title'     => $title,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return $outbox ? $outbox[0] : null;
	}
}
