<?php
/**
 * Test file for ActivityPub Moderation class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activity\Activity;
use Activitypub\Moderation;

/**
 * Test class for ActivityPub Moderation.
 *
 * @coversDefaultClass \Activitypub\Moderation
 */
class Test_Moderation extends \WP_UnitTestCase {

	/**
	 * Test user ID for testing.
	 *
	 * @var int
	 */
	private $test_user_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		// Create a test user.
		$this->test_user_id = $this->factory->user->create(
			array(
				'user_login' => 'testuser',
				'user_email' => 'test@example.com',
			)
		);

		// Clear all existing blocks to ensure clean state.
		$this->clean_moderation_data();

		add_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_remote_actor' ), 10, 2 );
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down(): void {
		$this->clean_moderation_data();
		remove_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_remote_actor' ) );

		parent::tear_down();
	}

	/**
	 * Mock remote actor response for testing.
	 *
	 * @param mixed  $response      The pre-filtered response.
	 * @param string $url_or_object The URL or object being requested.
	 *
	 * @return mixed The mocked response or the original response.
	 */
	public function mock_remote_actor( $response, $url_or_object ) {
		if ( 'https://example.com/@user' === $url_or_object ) {
			$response = array(
				'id'   => 'https://example.com/@user',
				'type' => 'Person',
				'guid' => 'https://example.com/@user',
			);
		}

		return $response;
	}

	/**
	 * Clean all moderation data.
	 */
	private function clean_moderation_data() {
		// Clean user meta.
		if ( $this->test_user_id ) {
			\delete_user_meta( $this->test_user_id, Moderation::USER_META_KEYS['domain'] );
			\delete_user_meta( $this->test_user_id, Moderation::USER_META_KEYS['keyword'] );
		}

		// Clean site options.
		\delete_option( Moderation::OPTION_KEYS['domain'] );
		\delete_option( Moderation::OPTION_KEYS['keyword'] );

		\wp_cache_flush();
	}

	/**
	 * Test adding user blocks for valid types.
	 *
	 * @covers ::add_user_block
	 * @covers ::get_user_blocks
	 */
	public function test_add_user_block_valid_types() {
		// Test domain block.
		$this->assertNotFalse( Moderation::add_user_block( $this->test_user_id, 'domain', 'spam.example.com' ) );

		// Test keyword block.
		$this->assertNotFalse( Moderation::add_user_block( $this->test_user_id, 'keyword', 'spam' ) );

		// Verify blocks were saved.
		$blocks = Moderation::get_user_blocks( $this->test_user_id );
		$this->assertContains( 'spam.example.com', $blocks['domains'] );
		$this->assertContains( 'spam', $blocks['keywords'] );
	}

	/**
	 * Test adding user blocks with invalid types.
	 *
	 * @covers ::add_user_block
	 */
	public function test_add_user_block_invalid_type() {
		$this->assertTrue( Moderation::add_user_block( $this->test_user_id, 'invalid_type', 'value' ) );
		$this->assertTrue( Moderation::add_user_block( $this->test_user_id, '', 'value' ) );
		$this->assertTrue( Moderation::add_user_block( $this->test_user_id, null, 'value' ) );
	}

	/**
	 * Test adding duplicate user blocks.
	 *
	 * @covers ::add_user_block
	 * @covers ::get_user_blocks
	 */
	public function test_add_user_block_duplicate() {
		$domain = 'spam.example.com';

		// Add block first time.
		$this->assertTrue( Moderation::add_user_block( $this->test_user_id, 'domain', $domain ) );

		// Add same block again - should return true but not duplicate.
		$this->assertTrue( Moderation::add_user_block( $this->test_user_id, 'domain', $domain ) );

		$blocks = Moderation::get_user_blocks( $this->test_user_id );
		$this->assertCount( 1, $blocks['domains'] );
		$this->assertContains( $domain, $blocks['domains'] );
	}

	/**
	 * Test removing user blocks.
	 *
	 * @covers ::remove_user_block
	 * @covers ::add_user_block
	 * @covers ::get_user_blocks
	 */
	public function test_remove_user_block() {
		$domain = 'spam.example.com';

		// Add blocks first.
		Moderation::add_user_block( $this->test_user_id, 'domain', $domain );

		// Remove domain block.
		$this->assertTrue( Moderation::remove_user_block( $this->test_user_id, 'domain', $domain ) );

		$blocks = Moderation::get_user_blocks( $this->test_user_id );
		$this->assertNotContains( $domain, $blocks['domains'] );
	}

	/**
	 * Test removing non-existent user blocks.
	 *
	 * @covers ::remove_user_block
	 */
	public function test_remove_user_block_nonexistent() {
		// Try to remove block that doesn't exist - should return true.
		$this->assertTrue( Moderation::remove_user_block( $this->test_user_id, 'domain', 'https://nonexistent.com/@user' ) );
	}

	/**
	 * Test removing user blocks with invalid types.
	 *
	 * @covers ::remove_user_block
	 */
	public function test_remove_user_block_invalid_type() {
		$this->assertTrue( Moderation::remove_user_block( $this->test_user_id, 'invalid_type', 'value' ) );
		$this->assertTrue( Moderation::remove_user_block( $this->test_user_id, '', 'value' ) );
		$this->assertTrue( Moderation::remove_user_block( $this->test_user_id, null, 'value' ) );
	}

	/**
	 * Test getting user blocks for empty user.
	 *
	 * @covers ::get_user_blocks
	 */
	public function test_get_user_blocks_empty() {
		$blocks = Moderation::get_user_blocks( $this->test_user_id );

		$this->assertIsArray( $blocks );
		$this->assertArrayHasKey( 'domains', $blocks );
		$this->assertArrayHasKey( 'keywords', $blocks );
		$this->assertEmpty( $blocks['domains'] );
		$this->assertEmpty( $blocks['keywords'] );
	}

	/**
	 * Test adding site blocks.
	 *
	 * @covers ::add_site_block
	 * @covers ::get_site_blocks
	 */
	public function test_add_site_block() {
		$this->assertTrue( Moderation::add_site_block( 'domain', 'spam-instance.com' ) );
		$this->assertTrue( Moderation::add_site_block( 'keyword', 'advertisement' ) );

		$blocks = Moderation::get_site_blocks();
		$this->assertContains( 'spam-instance.com', $blocks['domains'] );
		$this->assertContains( 'advertisement', $blocks['keywords'] );
	}

	/**
	 * Test adding duplicate site blocks.
	 *
	 * @covers ::add_site_block
	 * @covers ::get_site_blocks
	 */
	public function test_add_site_block_duplicate() {
		$domain = 'spam-instance.com';

		$this->assertNotFalse( Moderation::add_site_block( 'domain', $domain ) );
		$this->assertTrue( Moderation::add_site_block( 'domain', $domain ) );

		$blocks = Moderation::get_site_blocks();
		$this->assertCount( 1, $blocks['domains'] );
	}

	/**
	 * Test removing site blocks.
	 *
	 * @covers ::remove_site_block
	 * @covers ::add_site_block
	 * @covers ::get_site_blocks
	 */
	public function test_remove_site_block() {
		$domain = 'spam-instance.com';

		Moderation::add_site_block( 'domain', $domain );
		$this->assertTrue( Moderation::remove_site_block( 'domain', $domain ) );

		$blocks = Moderation::get_site_blocks();
		$this->assertNotContains( $domain, $blocks['domains'] );
	}

	/**
	 * Test activity blocking with site-wide blocks.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::activity_is_blocked_site_wide
	 * @covers ::check_activity_against_blocks
	 * @covers ::add_site_block
	 */
	public function test_activity_is_blocked_site_wide() {
		// Add site-wide blocks.
		Moderation::add_site_block( 'domain', 'spam-instance.com' );
		Moderation::add_site_block( 'keyword', 'buy now' );

		// Test domain blocking.
		/* @var Activity $activity Activity. */
		$activity = Activity::init_from_array(
			array(
				'type'   => 'Create',
				'actor'  => 'https://spam-instance.com/@anyuser',
				'object' => array(
					'id'      => 'https://example.com/note/1',
					'type'    => 'Note',
					'content' => 'Hello world',
				),
			)
		);
		$this->assertTrue( Moderation::activity_is_blocked( $activity ) );

		// Test keyword blocking.
		/* @var Activity $activity Activity. */
		$activity = Activity::init_from_array(
			array(
				'type'   => 'Create',
				'actor'  => 'https://good.example.com/@user',
				'object' => array(
					'id'      => 'https://example.com/note/1',
					'type'    => 'Note',
					'content' => 'Check out this product, buy now!',
				),
			)
		);
		$this->assertTrue( Moderation::activity_is_blocked( $activity ) );

		// Test non-blocked activity.
		/* @var Activity $activity Activity. */
		$activity = Activity::init_from_array(
			array(
				'type'   => 'Create',
				'actor'  => 'https://good.example.com/@user',
				'object' => array(
					'id'      => 'https://example.com/note/1',
					'type'    => 'Note',
					'content' => 'Hello everyone!',
				),
			)
		);
		$this->assertFalse( Moderation::activity_is_blocked( $activity ) );
	}

	/**
	 * Test activity blocking with user-specific blocks.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::activity_is_blocked_for_user
	 * @covers ::check_activity_against_blocks
	 * @covers ::add_user_block
	 */
	public function test_activity_is_blocked_user_specific() {
		// Add user-specific blocks.
		Moderation::add_user_block( $this->test_user_id, 'domain', 'noise-instance.com' );
		Moderation::add_user_block( $this->test_user_id, 'keyword', 'politics' );

		// Test activity blocked for specific user but not site-wide.
		/* @var Activity $activity Activity. */
		$activity = Activity::init_from_array(
			array(
				'type'   => 'Create',
				'actor'  => 'https://noise-instance.com/@user',
				'object' => array(
					'id'      => 'https://example.com/note/1',
					'type'    => 'Note',
					'content' => 'Hello world',
				),
			)
		);

		// Should be blocked for the specific user.
		$this->assertTrue( Moderation::activity_is_blocked( $activity, $this->test_user_id ) );

		// Should not be blocked site-wide.
		$this->assertFalse( Moderation::activity_is_blocked( $activity ) );
	}

	/**
	 * Test hierarchical blocking priority.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::activity_is_blocked_site_wide
	 * @covers ::check_activity_against_blocks
	 * @covers ::add_site_block
	 */
	public function test_hierarchical_blocking() {
		$domain = 'test.example.com';

		// Add site-wide block.
		Moderation::add_site_block( 'domain', $domain );

		/* @var Activity $activity Activity. */
		$activity = Activity::init_from_array(
			array(
				'type'   => 'Create',
				'actor'  => 'https://test.example.com/@user',
				'object' => array(
					'id'      => 'https://example.com/note/1',
					'type'    => 'Note',
					'content' => 'Hello world',
				),
			)
		);

		// Should be blocked site-wide (takes precedence).
		$this->assertTrue( Moderation::activity_is_blocked( $activity ) );
		$this->assertTrue( Moderation::activity_is_blocked( $activity, $this->test_user_id ) );
	}

	/**
	 * Test edge cases with malformed activity data.
	 *
	 * @covers ::activity_is_blocked
	 * @throws \Exception Thrown when an error occurs.
	 */
	public function test_activity_blocking_edge_cases() {
		// Test with empty activity.
		$this->assertFalse( Moderation::activity_is_blocked( array() ) );

		// Test with null activity.
		$this->assertFalse( Moderation::activity_is_blocked( null ) );

		// Test with non-array activity.
		$this->assertFalse( Moderation::activity_is_blocked( 'invalid' ) );

		// Test with missing actor.
		/* @var Activity $activity Activity. */
		$activity = Activity::init_from_array(
			array(
				'id'     => 'https://example.com/activities/1',
				'type'   => 'Create',
				'object' => array(
					'id'      => 'https://example.com/note/1',
					'type'    => 'Note',
					'content' => 'Test',
				),
			)
		);
		$this->assertFalse( Moderation::activity_is_blocked( $activity ) );

		// Test with empty actor.
		/* @var Activity $activity Activity. */
		$activity = Activity::init_from_array(
			array(
				'id'     => 'https://example.com/activities/1',
				'type'   => 'Create',
				'actor'  => '',
				'object' => array(
					'id'      => 'https://example.com/note/1',
					'type'    => 'Note',
					'content' => 'Test',
				),
			)
		);
		$this->assertFalse( Moderation::activity_is_blocked( $activity ) );

		// Test with malformed actor object.
		/* @var Activity $activity Activity. */
		$activity = Activity::init_from_array(
			array(
				'id'     => 'https://example.com/activities/1',
				'type'   => 'Create',
				'actor'  => array(
					'type' => 'Person',
					// Missing 'id' field.
				),
				'object' => array(
					'id'      => 'https://example.com/note/1',
					'type'    => 'Note',
					'content' => 'Test',
				),
			)
		);

		// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		\set_error_handler(
			static function ( $errno, $errstr ) {
				throw new \Exception( \esc_html( $errstr ), \esc_html( $errno ) );
			},
			E_NOTICE | E_WARNING
		);

		// PHP 7.2 uses "Undefined index", PHP 8+ uses "Undefined array key".
		if ( version_compare( PHP_VERSION, '8.0.0', '>=' ) ) {
			$this->expectExceptionMessage( 'Undefined array key &quot;id&quot;' );
		} else {
			$this->expectExceptionMessage( 'Undefined index: id' );
		}
		$this->assertFalse( Moderation::activity_is_blocked( $activity ) );

		\restore_error_handler();
	}

	/**
	 * Test domain extraction from various URL formats.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::activity_is_blocked_site_wide
	 * @covers ::add_site_block
	 */
	public function test_domain_blocking_url_formats() {
		Moderation::add_site_block( 'domain', 'example.com' );

		// Test different URL formats.
		$test_urls = array(
			'https://example.com/@user',
			'http://example.com/@user',
			'https://www.example.com/@user',
			'https://sub.example.com/@user',
		);

		foreach ( $test_urls as $url ) {
			/* @var Activity $activity Activity. */
			$activity = Activity::init_from_array(
				array(
					'type'   => 'Create',
					'actor'  => $url,
					'object' => array(
						'id'      => 'https://example.org/note/1',
						'type'    => 'Note',
						'content' => 'Test',
					),
				)
			);

			// Only exact domain matches should be blocked.
			if ( 'https://example.com/@user' === $url || 'http://example.com/@user' === $url ) {
				$this->assertTrue( Moderation::activity_is_blocked( $activity ), "URL $url should be blocked" );
			} else {
				$this->assertFalse( Moderation::activity_is_blocked( $activity ), "URL $url should not be blocked" );
			}
		}
	}

	/**
	 * Test keyword blocking case insensitivity.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::activity_is_blocked_site_wide
	 * @covers ::add_site_block
	 */
	public function test_keyword_blocking_case_insensitive() {
		Moderation::add_site_block( 'keyword', 'SPAM' );

		$test_contents = array(
			'This is spam content',
			'This is SPAM content',
			'This is Spam content',
			'This is SpAm content',
		);

		foreach ( $test_contents as $content ) {
			/* @var Activity $activity Activity. */
			$activity = Activity::init_from_array(
				array(
					'type'   => 'Create',
					'actor'  => 'https://example.com/@user',
					'object' => array(
						'id'      => 'https://example.com/note/1',
						'type'    => 'Note',
						'content' => $content,
					),
				)
			);

			$this->assertTrue( Moderation::activity_is_blocked( $activity ), "Content '$content' should be blocked" );
		}
	}

	/**
	 * Test with invalid user IDs.
	 *
	 * @covers ::add_user_block
	 * @covers ::remove_user_block
	 */
	public function test_invalid_user_ids() {
		// Test with non-existent user ID.
		$this->assertNotFalse( Moderation::add_user_block( 99999, 'domain', 'example.com' ) );
		$this->assertTrue( Moderation::remove_user_block( 99999, 'domain', 'example.com' ) );

		// Test with zero user ID - WordPress treats user ID 0 specially, may return false.
		$result = Moderation::add_user_block( 0, 'domain', 'example.com' );
		// User ID 0 might be handled differently by WordPress, so we allow both true/false.
		$this->assertFalse( $result );

		// Test with negative user ID.
		$this->assertNotFalse( Moderation::add_user_block( -1, 'domain', 'example.com' ) );
	}

	/**
	 * Test with extremely long values.
	 *
	 * @covers ::add_user_block
	 * @covers ::add_site_block
	 * @covers ::get_user_blocks
	 */
	public function test_long_values() {
		$long_value = str_repeat( 'a', 10000 );

		$this->assertNotFalse( Moderation::add_user_block( $this->test_user_id, 'keyword', $long_value ) );
		$this->assertNotFalse( Moderation::add_site_block( 'keyword', $long_value ) );

		$blocks = Moderation::get_user_blocks( $this->test_user_id );
		$this->assertContains( $long_value, $blocks['keywords'] );
	}

	/**
	 * Test with special characters and Unicode.
	 *
	 * @covers ::add_user_block
	 * @covers ::get_user_blocks
	 */
	public function test_special_characters() {
		$special_values = array(
			'https://example.com/@user-with-dashes',
			'https://example.com/@user_with_underscores',
			'https://example.com/@user.with.dots',
			'keyword with spaces',
			'keyword-with-dashes',
			'keyword_with_underscores',
			'unicode-keyword-🚫',
			'émojis-and-accénts',
		);

		foreach ( $special_values as $value ) {
			$this->assertNotFalse( Moderation::add_user_block( $this->test_user_id, 'keyword', $value ), "Failed to add: $value" );
		}

		$blocks = Moderation::get_user_blocks( $this->test_user_id );
		foreach ( $special_values as $value ) {
			$this->assertContains( $value, $blocks['keywords'], "Missing: $value" );
		}
	}

	/**
	 * Test array re-indexing after removal.
	 *
	 * @covers ::add_user_block
	 * @covers ::remove_user_block
	 * @covers ::get_user_blocks
	 */
	public function test_array_reindexing() {
		$domains = array(
			'example.com',
			'activitypub.blog',
			'example.org',
		);

		// Add all domains.
		foreach ( $domains as $domain ) {
			Moderation::add_user_block( $this->test_user_id, 'domain', $domain );
		}

		// Remove middle domain.
		Moderation::remove_user_block( $this->test_user_id, 'domain', $domains[1] );

		$blocks = Moderation::get_user_blocks( $this->test_user_id );

		// Array should be properly re-indexed.
		$this->assertCount( 2, $blocks['domains'] );
		$this->assertContains( $domains[0], $blocks['domains'] );
		$this->assertContains( $domains[2], $blocks['domains'] );
		$this->assertNotContains( $domains[1], $blocks['domains'] );

		// Keys should be sequential.
		$this->assertEquals( array_values( $blocks['domains'] ), $blocks['domains'] );
	}

	/**
	 * Test WordPress comment disallowed list fallback.
	 *
	 * @covers ::activity_is_blocked
	 */
	public function test_wordpress_disallowed_list_fallback() {
		\update_option( 'disallowed_keys', "badword\nspam.example.com" );

		/* @var Activity $activity Activity. */
		$activity = Activity::init_from_array(
			array(
				'type'   => 'Create',
				'actor'  => 'https://good.example.com/@user',
				'object' => array(
					'id'      => 'https://example.com/note/1',
					'type'    => 'Note',
					'content' => 'This contains badword in it',
				),
			)
		);

		// Should be blocked by WordPress disallowed list.
		$this->assertTrue( Moderation::activity_is_blocked( $activity ) );

		// Clean up.
		\delete_option( 'disallowed_keys' );
	}

	/**
	 * Test blocked attributes.
	 *
	 * @covers ::activity_is_blocked
	 *
	 * @dataProvider blocked_attributes_provider
	 *
	 * @param array $data     The data.
	 * @param bool  $expected The expected result.
	 */
	public function test_blocked_attributes( $data, $expected ) {
		Moderation::add_site_block( 'keyword', 'spam' );

		$data = Activity::init_from_array( $data );

		$this->assertEquals( $expected, Moderation::activity_is_blocked( $data ) );

		Moderation::remove_site_block( 'keyword', 'spam' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function blocked_attributes_provider() {
		return array(
			array(
				array(
					'actor'  => 'https://example.com/@user',
					'object' => array(
						'id'      => 'https://example.com/note/1',
						'type'    => 'Note',
						'content' => 'spam',
					),
				),
				true,
			),
			array(
				array(
					'actor'  => 'https://example.com/@user',
					'object' => array(
						'id'                => 'https://example.com/note/1',
						'type'              => 'Person',
						'preferredUsername' => 'spam',
					),
				),
				true,
			),
			array(
				array(
					'actor'  => 'https://example.com/@user',
					'object' => array(
						'id'      => 'https://example.com/note/1',
						'type'    => 'Note',
						'summary' => 'spam',
					),
				),
				true,
			),
			array(
				array(
					'actor'  => 'https://example.com/@user',
					'object' => array(
						'id'   => 'https://example.com/note/1',
						'type' => 'Note',
						'name' => 'spam',
					),
				),
				true,
			),
			array(
				array(
					'actor'  => 'https://example.com/@user',
					'object' => array(
						'id'      => 'https://example.com/note/1',
						'type'    => 'Note',
						'content' => 'Test',
					),
				),
				false,
			),
		);
	}
}
