<?php
/**
 * Outbox Testcase file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Outbox;

/**
 * Outbox Testcase.
 */
class ActivityPub_Outbox_TestCase extends \WP_UnitTestCase {
	/**
	 * User ID for testing.
	 *
	 * @var int
	 */
	protected static $user_id;

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
		\delete_option( 'activitypub_actor_mode' );
		\wp_delete_user( self::$user_id );
		\remove_filter( 'pre_schedule_event', '__return_false' );

		parent::tear_down_after_class();
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		parent::tear_down();

		_delete_all_posts();
	}

	/**
	 * Retrieve the latest Outbox item to compare against.
	 *
	 * @param string $title Title of the Outbox item.
	 * @return int|\WP_Post|null
	 */
	protected function get_latest_outbox_item( $title = '' ) {
		$outbox = \get_posts(
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
