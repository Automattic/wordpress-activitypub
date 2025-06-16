<?php
/**
 * Test file for Delete handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Handler\Delete;

/**
 * Test class for Delete handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Delete
 */
class Test_Delete extends \WP_UnitTestCase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before tests run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
	}

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		\add_filter( 'pre_get_remote_metadata_by_actor', array( self::class, 'get_remote_metadata_by_actor' ), 0, 2 );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\remove_filter( 'pre_get_remote_metadata_by_actor', array( self::class, 'get_remote_metadata_by_actor' ) );

		parent::tear_down();
	}

	/**
	 * Test delete interactions.
	 */
	public function test_delete_interactions() {
		self::factory()->comment->create_many(
			5,
			array(
				'author_url'   => get_author_posts_url( self::$user_id ),
				'comment_meta' => array( 'protocol' => 'activitypub' ),
			)
		);

		Delete::delete_interactions( get_author_posts_url( self::$user_id ) );

		$this->assertEmpty( get_comments( array( 'user_id' => self::$user_id ) ) );
	}

	/**
	 * Get remote metadata by actor.
	 *
	 * @param string $value Value.
	 * @param string $actor Actor.
	 * @return array
	 */
	public static function get_remote_metadata_by_actor( $value, $actor ) {
		return array(
			'name' => 'Example User',
			'icon' => array(
				'url' => 'https://example.com/icon',
			),
			'url'  => get_author_posts_url( $actor ),
			'id'   => 'http://example.org/users/example',
		);
	}
}
