<?php
/**
 * Test file for the Dislike handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Handler\Dislike;

/**
 * Test class for the Dislike handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Dislike
 */
class Test_Dislike extends \WP_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	protected $post_id;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		\add_filter( 'pre_get_remote_metadata_by_actor', array( self::class, 'remote_metadata' ), 0, 2 );
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\remove_filter( 'pre_get_remote_metadata_by_actor', array( self::class, 'remote_metadata' ) );
		\delete_option( 'activitypub_allow_dislikes' );

		parent::tear_down();
	}

	/**
	 * Remote actor metadata.
	 *
	 * @param mixed  $value The value.
	 * @param string $actor The actor.
	 *
	 * @return array The metadata.
	 */
	public static function remote_metadata( $value, $actor ) {
		return array(
			'name' => 'Lemmy User',
			'url'  => $actor,
			'id'   => $actor,
		);
	}

	/**
	 * Build a Dislike activity.
	 *
	 * @param array $overrides Fields to override.
	 *
	 * @return array The activity.
	 */
	private function dislike( $overrides = array() ) {
		return \array_merge(
			array(
				'id'     => 'https://lemmy.example/activities/dislike/' . \wp_generate_uuid4(),
				'type'   => 'Dislike',
				'actor'  => 'https://lemmy.example/u/remote',
				'object' => \get_permalink( $this->post_id ),
			),
			$overrides
		);
	}

	/**
	 * Count the dislike comments on the test post.
	 *
	 * @return int The count.
	 */
	private function count_dislikes() {
		return (int) \get_comments(
			array(
				'post_id' => $this->post_id,
				'type'    => 'dislike',
				'status'  => 'all',
				'count'   => true,
			)
		);
	}

	/**
	 * Test the handler.
	 *
	 * @dataProvider dislike_provider
	 * @covers ::handle_dislike
	 *
	 * @param array       $overrides Activity overrides.
	 * @param string|null $allowed   The option value, or null to leave it unset.
	 * @param int         $expected  Expected number of dislike comments.
	 */
	public function test_handle_dislike( $overrides, $allowed, $expected ) {
		if ( null !== $allowed ) {
			\update_option( 'activitypub_allow_dislikes', $allowed );
		}

		Dislike::handle_dislike( $this->dislike( $overrides ), 1 );

		$this->assertSame( $expected, $this->count_dislikes() );
	}

	/**
	 * Data provider for test_handle_dislike.
	 *
	 * @return array[]
	 */
	public function dislike_provider() {
		return array(
			'stored when enabled' => array( array(), '1', 1 ),
			'ignored when off'    => array( array(), '0', 0 ),
			'ignored when unset'  => array( array(), null, 0 ),
			'empty object'        => array( array( 'object' => '' ), '1', 0 ),
			'id on another host'  => array( array( 'id' => 'https://other.example/dislike/1' ), '1', 0 ),
		);
	}

	/**
	 * A repeated delivery is stored once.
	 *
	 * @covers ::handle_dislike
	 */
	public function test_handle_dislike_dedupes() {
		\update_option( 'activitypub_allow_dislikes', '1' );
		$activity = $this->dislike();

		Dislike::handle_dislike( $activity, 1 );
		Dislike::handle_dislike( $activity, 1 );

		$this->assertSame( 1, $this->count_dislikes() );
	}

	/**
	 * The handler listens to the hook the inbox fires for Dislike activities.
	 *
	 * @covers ::init
	 */
	public function test_inbox_hook_stores_dislike() {
		\update_option( 'activitypub_allow_dislikes', '1' );

		\do_action( 'activitypub_inbox_dislike', $this->dislike(), 1 );

		$this->assertSame( 1, $this->count_dislikes() );
	}
}
