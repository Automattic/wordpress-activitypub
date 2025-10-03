<?php
/**
 * Test file for Jetpack integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Comment;
use Activitypub\Integration\Jetpack;

/**
 * Test class for Jetpack integration.
 *
 * @coversDefaultClass \Activitypub\Integration\Jetpack
 */
class Test_Jetpack extends \WP_UnitTestCase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role' => 'author',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_content' => 'Test post content',
				'post_title'   => 'Test Post',
				'post_status'  => 'publish',
			)
		);
	}

	/**
	 * Load mock Manager class for specific tests.
	 */
	private function load_mock_manager() {
		if ( ! class_exists( '\Automattic\Jetpack\Connection\Manager' ) ) {
			require_once AP_TESTS_DIR . '/data/class-manager.php';
		}
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down() {
		// Remove any filters that may have been added during tests.
		remove_all_filters( 'jetpack_sync_post_meta_whitelist' );
		remove_all_filters( 'jetpack_sync_comment_meta_whitelist' );
		remove_all_filters( 'jetpack_sync_whitelisted_comment_types' );
		remove_all_filters( 'jetpack_json_api_comment_types' );
		remove_all_filters( 'jetpack_api_include_comment_types_count' );
		remove_all_filters( 'activitypub_following_row_actions' );
		remove_all_filters( 'pre_option_activitypub_following_ui' );

		parent::tear_down();
	}

	/**
	 * Test init method registers sync hooks without Manager class.
	 *
	 * This test must run before the Manager class is loaded to test the behavior
	 * when the class doesn't exist.
	 *
	 * @covers ::init
	 */
	public function test_a_init_registers_sync_hooks_without_manager() {
		// Verify Manager class is not yet loaded.
		$this->assertFalse( class_exists( '\Automattic\Jetpack\Connection\Manager' ), 'Manager class should not exist yet' );

		// Ensure hooks are not already registered.
		$this->assertFalse( has_filter( 'jetpack_sync_post_meta_whitelist' ) );
		$this->assertFalse( has_filter( 'activitypub_following_row_actions' ) );
		$this->assertFalse( has_filter( 'pre_option_activitypub_following_ui' ) );

		// Initialize Jetpack integration without Manager class loaded.
		Jetpack::init();

		// Check that sync hooks are registered regardless of Manager class.
		$this->assertTrue( has_filter( 'jetpack_sync_post_meta_whitelist' ) );
		$this->assertTrue( has_filter( 'jetpack_sync_comment_meta_whitelist' ) );
		$this->assertTrue( has_filter( 'jetpack_sync_whitelisted_comment_types' ) );
		$this->assertTrue( has_filter( 'jetpack_json_api_comment_types' ) );
		$this->assertTrue( has_filter( 'jetpack_api_include_comment_types_count' ) );

		// Following UI hooks should NOT be registered without Manager class.
		$this->assertFalse( has_filter( 'activitypub_following_row_actions' ) );
		$this->assertFalse( has_filter( 'pre_option_activitypub_following_ui' ) );
	}

	/**
	 * Test init method registers all hooks with Manager class available.
	 *
	 * @covers ::init
	 */
	public function test_b_init_registers_hooks_with_manager() {
		// Load mock Manager class.
		$this->load_mock_manager();

		// Ensure hooks are not already registered.
		$this->assertFalse( has_filter( 'jetpack_sync_post_meta_whitelist' ) );
		$this->assertFalse( has_filter( 'activitypub_following_row_actions' ) );
		$this->assertFalse( has_filter( 'pre_option_activitypub_following_ui' ) );

		// Initialize Jetpack integration with Manager class.
		Jetpack::init();

		// Check that sync hooks are registered.
		$this->assertTrue( has_filter( 'jetpack_sync_post_meta_whitelist' ) );
		$this->assertTrue( has_filter( 'jetpack_sync_comment_meta_whitelist' ) );
		$this->assertTrue( has_filter( 'jetpack_sync_whitelisted_comment_types' ) );
		$this->assertTrue( has_filter( 'jetpack_json_api_comment_types' ) );
		$this->assertTrue( has_filter( 'jetpack_api_include_comment_types_count' ) );

		// Following UI hooks should also be registered (mock Manager returns connected).
		$this->assertTrue( has_filter( 'activitypub_following_row_actions' ) );
		$this->assertTrue( has_filter( 'pre_option_activitypub_following_ui' ) );
	}

	/**
	 * Test that Manager class connection check works when available.
	 *
	 * @covers ::init
	 */
	public function test_c_manager_connection_check() {
		// Load mock Manager class.
		$this->load_mock_manager();

		// Test that our mock Manager class exists and works.
		$this->assertTrue( class_exists( '\Automattic\Jetpack\Connection\Manager' ), 'Mock Manager class should exist' );

		$manager = new \Automattic\Jetpack\Connection\Manager();
		$this->assertTrue( $manager->is_user_connected(), 'Mock Manager should return connected' );
	}

	/**
	 * Test add_sync_meta method adds ActivityPub meta keys.
	 *
	 * @covers ::add_sync_meta
	 */
	public function test_add_sync_meta() {
		$original_list = array( 'existing_meta_key' );

		$updated_list = Jetpack::add_sync_meta( $original_list );

		// Check that original keys are preserved.
		$this->assertContains( 'existing_meta_key', $updated_list );

		// Check that ActivityPub meta keys are added.
		$this->assertContains( Followers::FOLLOWER_META_KEY, $updated_list );
		$this->assertContains( Following::FOLLOWING_META_KEY, $updated_list );
	}

	/**
	 * Test add_sync_comment_meta method adds ActivityPub comment meta keys.
	 *
	 * @covers ::add_sync_comment_meta
	 */
	public function test_add_sync_comment_meta() {
		$original_list = array( 'existing_comment_meta' );

		$updated_list = Jetpack::add_sync_comment_meta( $original_list );

		// Check that original keys are preserved.
		$this->assertContains( 'existing_comment_meta', $updated_list );

		// Check that ActivityPub comment meta keys are added.
		$this->assertContains( 'avatar_url', $updated_list );
	}

	/**
	 * Test add_comment_types method adds ActivityPub comment types.
	 *
	 * @covers ::add_comment_types
	 */
	public function test_add_comment_types() {
		$original_types = array( 'comment', 'pingback', 'trackback' );

		$updated_types = Jetpack::add_comment_types( $original_types );

		// Check that original types are preserved.
		$this->assertContains( 'comment', $updated_types );
		$this->assertContains( 'pingback', $updated_types );
		$this->assertContains( 'trackback', $updated_types );

		// Check that ActivityPub comment types are added.
		$expected_ap_types = Comment::get_comment_type_slugs();
		foreach ( $expected_ap_types as $type ) {
			$this->assertContains( $type, $updated_types );
		}

		// Check that duplicates are removed.
		$this->assertEquals( $updated_types, array_unique( $updated_types ) );
	}

	/**
	 * Test pre_option_activitypub_following_ui method forces UI to be enabled.
	 *
	 * @covers ::pre_option_activitypub_following_ui
	 */
	public function test_pre_option_activitypub_following_ui() {
		$result = Jetpack::pre_option_activitypub_following_ui();

		$this->assertEquals( '1', $result );
	}

	/**
	 * Test integration with actual WordPress filters.
	 */
	public function test_filter_integration() {
		// Initialize Jetpack integration.
		Jetpack::init();

		// Test sync meta filter integration (only if not on WordPress.com).
		if ( ! defined( 'IS_WPCOM' ) ) {
			$sync_meta = apply_filters( 'jetpack_sync_post_meta_whitelist', array() );
			$this->assertContains( Followers::FOLLOWER_META_KEY, $sync_meta );
			$this->assertContains( Following::FOLLOWING_META_KEY, $sync_meta );

			// Test comment meta filter integration.
			$comment_meta = apply_filters( 'jetpack_sync_comment_meta_whitelist', array() );
			$this->assertContains( 'avatar_url', $comment_meta );

			// Test comment types filter integration.
			$comment_types     = apply_filters( 'jetpack_sync_whitelisted_comment_types', array() );
			$expected_ap_types = Comment::get_comment_type_slugs();
			foreach ( $expected_ap_types as $type ) {
				$this->assertContains( $type, $comment_types );
			}
		} else {
			// On WordPress.com, sync filters should not be registered.
			// Test that they are indeed not registered.
			$sync_meta = apply_filters( 'jetpack_sync_post_meta_whitelist', array() );
			$this->assertNotContains( Followers::FOLLOWER_META_KEY, $sync_meta );
			$this->assertNotContains( Following::FOLLOWING_META_KEY, $sync_meta );

			$comment_meta = apply_filters( 'jetpack_sync_comment_meta_whitelist', array() );
			$this->assertNotContains( 'avatar_url', $comment_meta );
		}

		// Test following UI filter integration - test direct method calls.
		$ui_result = Jetpack::pre_option_activitypub_following_ui();
		$this->assertEquals( '1', $ui_result );

		// Test reader link method directly.
		$test_item        = array(
			'id'         => 123,
			'status'     => 'active',
			'identifier' => 'https://example.com/feed',
		);
		$original_actions = array( 'edit' => '<a href="#">Edit</a>' );
		$updated_actions  = Jetpack::add_reader_link( $original_actions, $test_item );
		$this->assertArrayHasKey( 'reader', $updated_actions );
	}

	/**
	 * Data provider for Reader link test scenarios.
	 *
	 * @return array Test cases with different following item configurations.
	 */
	public function reader_link_data() {
		return array(
			'active following without feed ID' => array(
				'item'                    => array(
					'id'         => 123,
					'status'     => 'active',
					'identifier' => 'https://example.com/feed',
				),
				'feed_id'                 => false,
				'expected_url'            => 'https://wordpress.com/reader/feeds/lookup/https%3A%2F%2Fexample.com%2Ffeed',
				'should_have_reader_link' => true,
			),
			'active following with feed ID'    => array(
				'item'                    => array(
					'id'         => 123,
					'status'     => 'active',
					'identifier' => 'https://example.com/feed',
				),
				'feed_id'                 => 456,
				'expected_url'            => 'https://wordpress.com/reader/feeds/456',
				'should_have_reader_link' => true,
			),
			'pending following should not have reader link' => array(
				'item'                    => array(
					'id'         => 123,
					'status'     => 'pending',
					'identifier' => 'https://example.com/feed',
				),
				'feed_id'                 => 456,
				'expected_url'            => null,
				'should_have_reader_link' => false,
			),
		);
	}

	/**
	 * Test add_reader_link method adds correct Reader links.
	 *
	 * @dataProvider reader_link_data
	 * @covers ::add_reader_link
	 *
	 * @param array       $item                    The following item.
	 * @param int|false   $feed_id                 The feed ID or false.
	 * @param string|null $expected_url            Expected URL or null.
	 * @param bool        $should_have_reader_link Whether reader link should be added.
	 */
	public function test_add_reader_link( $item, $feed_id, $expected_url, $should_have_reader_link ) {
		$original_actions = array( 'edit' => '<a href="#">Edit</a>' );

		// Set up WPCOM environment if expecting WPCOM-style URL.
		$is_wpcom_test = $expected_url && strpos( $expected_url, '/reader/feeds/lookup/' ) === false;
		if ( $is_wpcom_test && ! defined( 'IS_WPCOM' ) ) {
			define( 'IS_WPCOM', true );
		}

		// Mock the feed ID meta if provided.
		if ( false !== $feed_id ) {
			add_filter(
				'get_post_metadata',
				function ( $value, $object_id, $meta_key ) use ( $item, $feed_id ) {
					if ( $object_id === $item['id'] && '_activitypub_actor_feed' === $meta_key ) {
						// Return as array of values (WordPress expects this format).
						return array( array( 'feed_id' => $feed_id ) );
					}
					return $value;
				},
				10,
				3
			);
		}

		$updated_actions = Jetpack::add_reader_link( $original_actions, $item );

		// Check that original actions are preserved.
		$this->assertArrayHasKey( 'edit', $updated_actions );

		if ( $should_have_reader_link ) {
			// Check that reader link is added.
			$this->assertArrayHasKey( 'reader', $updated_actions );
			$this->assertStringContainsString( $expected_url, $updated_actions['reader'] );
			$this->assertStringContainsString( 'View Feed', $updated_actions['reader'] );
			$this->assertStringContainsString( 'target="_blank"', $updated_actions['reader'] );
		} else {
			// Check that reader link is not added for pending items.
			$this->assertArrayNotHasKey( 'reader', $updated_actions );
		}

		// Clean up filters.
		remove_all_filters( 'get_post_metadata' );
	}
}
