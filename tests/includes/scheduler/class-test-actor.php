<?php
/**
 * Test Actor scheduler class.
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
 * @coversDefaultClass \Activitypub\Scheduler\Actor
 */
class Test_Actor extends \WP_UnitTestCase {
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
	 * Test post activity scheduling for ActivityPub extra fields.
	 *
	 * @covers ::schedule_post_activity
	 */
	public function test_schedule_post_activity_extra_fields() {
		$author_post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_type'   => Extra_Fields::USER_POST_TYPE,
			)
		);
		$activitpub_id  = Actors::get_by_id( self::$user_id )->get_id();

		$post = $this->get_latest_outbox_item( $activitpub_id );
		$this->assertSame( $activitpub_id, $post->post_title );

		update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		$blog_post_id  = self::factory()->post->create( array( 'post_type' => Extra_Fields::BLOG_POST_TYPE ) );
		$activitpub_id = Actors::get_by_id( Actors::BLOG_USER_ID )->get_id();

		$post = $this->get_latest_outbox_item( $activitpub_id );
		$this->assertSame( $activitpub_id, $post->post_title );

		// Clean up.
		\wp_delete_post( $author_post_id, true );
		\wp_delete_post( $blog_post_id, true );
		update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );
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
				'post_status'    => 'draft',
				'post_title'     => $title,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return $outbox ? $outbox[0] : null;
	}
}
