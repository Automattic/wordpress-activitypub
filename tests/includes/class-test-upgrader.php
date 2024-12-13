<?php
/**
 * Test_Upgrader class.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests;

use Activitypub\Comment;
use Activitypub\Upgrader;

/**
 * Test cases for the Upgrader class.
 */
class Test_Upgrader extends \WP_UnitTestCase {

	/**
	 * Set up test resources.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( 'activitypub_version' );
		delete_option( 'activitypub_450_comment_counts_updated' );
	}

	/**
	 * Tear down test resources.
	 */
	public function tear_down() {
		delete_option( 'activitypub_version' );
		delete_option( 'activitypub_450_comment_counts_updated' );
		parent::tear_down();
	}

	/**
	 * Test init() sets up the action hook correctly.
	 */
	public function test_init_sets_up_action_hook() {
		Upgrader::init();
		$this->assertEquals( 10, has_action( 'activitypub_update_comment_counts', array( Upgrader::class, 'update_comment_counts' ) ) );
	}

	/**
	 * Test maybe_upgrade() when version is current.
	 */
	public function test_maybe_upgrade_with_current_version() {
		update_option( 'activitypub_version', ACTIVITYPUB_PLUGIN_VERSION );

		Upgrader::maybe_upgrade();

		$this->assertEquals( ACTIVITYPUB_PLUGIN_VERSION, get_option( 'activitypub_version' ) );
	}

	/**
	 * Test maybe_upgrade() when version needs upgrade.
	 */
	public function test_maybe_upgrade_with_old_version() {
		update_option( 'activitypub_version', '4.4.0' );

		Upgrader::maybe_upgrade();

		$this->assertEquals( ACTIVITYPUB_PLUGIN_VERSION, get_option( 'activitypub_version' ) );
	}

	/**
	 * Test maybe_upgrade() with no version set.
	 */
	public function test_maybe_upgrade_with_no_version() {
		Upgrader::maybe_upgrade();

		$this->assertEquals( ACTIVITYPUB_PLUGIN_VERSION, get_option( 'activitypub_version' ) );
	}

	/**
	 * Test upgrade_to_450() when already done.
	 */
	public function test_upgrade_to_450_when_already_done() {
		update_option( 'activitypub_450_comment_counts_updated', true );

		$this->assertNull( $this->invoke_private_method( 'upgrade_to_450' ) );
	}

	/**
	 * Test update_comment_counts() properly cleans up the lock.
	 */
	public function test_update_comment_counts_with_lock() {

		// Register comment types.
		Comment::register_comment_types();

		// Create test comments.
		$post_id    = $this->factory->post->create();
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
				'comment_type'     => 'repost', // One of the registered comment types.
			)
		);

		Upgrader::update_comment_counts( 10, 0 );

		// Verify lock was cleaned up.
		$lock_name = 'activitypub_update_comment_counts.lock';
		$this->assertFalse( get_option( $lock_name ) );

		// Clean up.
		wp_delete_comment( $comment_id, true );
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test update_comment_counts() with existing valid lock.
	 */
	public function test_update_comment_counts_with_existing_valid_lock() {
		// Register comment types.
		Comment::register_comment_types();

		$lock_name = 'activitypub_update_comment_counts.lock';
		update_option( $lock_name, time() );

		Upgrader::update_comment_counts( 10, 0 );

		// Verify a scheduled event was created.
		$next_scheduled = wp_next_scheduled(
			'activitypub_update_comment_counts',
			array(
				'batch_size' => 10,
				'offset'     => 0,
			)
		);
		$this->assertNotFalse( $next_scheduled );

		// Clean up.
		delete_option( $lock_name );
		wp_clear_scheduled_hook(
			'activitypub_update_comment_counts',
			array(
				'batch_size' => 10,
				'offset'     => 0,
			)
		);
	}

	/**
	 * Helper method to invoke private methods.
	 *
	 * @param string $method_name Name of the private method.
	 * @param array  $parameters  Parameters to pass to the method.
	 * @return mixed
	 */
	private function invoke_private_method( $method_name, $parameters = array() ) {
		$reflection = new \ReflectionClass( Upgrader::class );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method->invokeArgs( null, $parameters );
	}
}
