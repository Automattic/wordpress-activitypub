<?php
/**
 * Test file for ActivityPub Moderation class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

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
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down(): void {
		$this->clean_moderation_data();
		parent::tear_down();
	}

	/**
	 * Clean all moderation data.
	 */
	private function clean_moderation_data() {
		// Clean user meta.
		if ( $this->test_user_id ) {
			\delete_user_meta( $this->test_user_id, Moderation::USER_BLOCKED_ACTORS_META );
			\delete_user_meta( $this->test_user_id, Moderation::USER_BLOCKED_DOMAINS_META );
			\delete_user_meta( $this->test_user_id, Moderation::USER_BLOCKED_KEYWORDS_META );
		}

		// Clean site options.
		\delete_option( Moderation::SITE_BLOCKED_ACTORS_OPTION );
		\delete_option( Moderation::SITE_BLOCKED_DOMAINS_OPTION );
		\delete_option( Moderation::SITE_BLOCKED_KEYWORDS_OPTION );

		\wp_cache_flush();
	}

	/**
	 * Test adding user blocks for valid types.
	 *
	 * @covers ::add_user_block
	 * @covers ::get_user_blocks
	 * @covers ::get_user_meta_key_for_type
	 */
	public function test_add_user_block_valid_types() {
		// Test actor block.
		$this->assertNotFalse( Moderation::add_user_block( $this->test_user_id, 'actor', 'https://example.com/@user' ) );

		// Test domain block.
		$this->assertNotFalse( Moderation::add_user_block( $this->test_user_id, 'domain', 'spam.example.com' ) );

		// Test keyword block.
		$this->assertNotFalse( Moderation::add_user_block( $this->test_user_id, 'keyword', 'spam' ) );

		// Verify blocks were saved.
		$blocks = Moderation::get_user_blocks( $this->test_user_id );
		$this->assertContains( 'https://example.com/@user', $blocks['actors'] );
		$this->assertContains( 'spam.example.com', $blocks['domains'] );
		$this->assertContains( 'spam', $blocks['keywords'] );
	}

	/**
	 * Test adding user blocks with invalid types.
	 *
	 * @covers ::add_user_block
	 * @covers ::get_user_meta_key_for_type
	 */
	public function test_add_user_block_invalid_type() {
		$this->assertFalse( Moderation::add_user_block( $this->test_user_id, 'invalid_type', 'value' ) );
		$this->assertFalse( Moderation::add_user_block( $this->test_user_id, '', 'value' ) );
		$this->assertFalse( Moderation::add_user_block( $this->test_user_id, null, 'value' ) );
	}

	/**
	 * Test adding duplicate user blocks.
	 *
	 * @covers ::add_user_block
	 * @covers ::get_user_blocks
	 */
	public function test_add_user_block_duplicate() {
		$actor = 'https://example.com/@user';

		// Add block first time.
		$this->assertNotFalse( Moderation::add_user_block( $this->test_user_id, 'actor', $actor ) );

		// Add same block again - should return true but not duplicate.
		$this->assertTrue( Moderation::add_user_block( $this->test_user_id, 'actor', $actor ) );

		$blocks = Moderation::get_user_blocks( $this->test_user_id );
		$this->assertCount( 1, $blocks['actors'] );
		$this->assertContains( $actor, $blocks['actors'] );
	}

	/**
	 * Test removing user blocks.
	 *
	 * @covers ::remove_user_block
	 * @covers ::add_user_block
	 * @covers ::get_user_blocks
	 */
	public function test_remove_user_block() {
		$actor  = 'https://example.com/@user';
		$domain = 'spam.example.com';

		// Add blocks first.
		Moderation::add_user_block( $this->test_user_id, 'actor', $actor );
		Moderation::add_user_block( $this->test_user_id, 'domain', $domain );

		// Remove actor block.
		$this->assertTrue( Moderation::remove_user_block( $this->test_user_id, 'actor', $actor ) );

		$blocks = Moderation::get_user_blocks( $this->test_user_id );
		$this->assertNotContains( $actor, $blocks['actors'] );
		$this->assertContains( $domain, $blocks['domains'] );
	}

	/**
	 * Test removing non-existent user blocks.
	 *
	 * @covers ::remove_user_block
	 */
	public function test_remove_user_block_nonexistent() {
		// Try to remove block that doesn't exist - should return true.
		$this->assertTrue( Moderation::remove_user_block( $this->test_user_id, 'actor', 'https://nonexistent.com/@user' ) );
	}

	/**
	 * Test removing user blocks with invalid types.
	 *
	 * @covers ::remove_user_block
	 * @covers ::get_user_meta_key_for_type
	 */
	public function test_remove_user_block_invalid_type() {
		$this->assertFalse( Moderation::remove_user_block( $this->test_user_id, 'invalid_type', 'value' ) );
		$this->assertFalse( Moderation::remove_user_block( $this->test_user_id, '', 'value' ) );
		$this->assertFalse( Moderation::remove_user_block( $this->test_user_id, null, 'value' ) );
	}

	/**
	 * Test getting user blocks for empty user.
	 *
	 * @covers ::get_user_blocks
	 */
	public function test_get_user_blocks_empty() {
		$blocks = Moderation::get_user_blocks( $this->test_user_id );

		$this->assertIsArray( $blocks );
		$this->assertArrayHasKey( 'actors', $blocks );
		$this->assertArrayHasKey( 'domains', $blocks );
		$this->assertArrayHasKey( 'keywords', $blocks );
		$this->assertEmpty( $blocks['actors'] );
		$this->assertEmpty( $blocks['domains'] );
		$this->assertEmpty( $blocks['keywords'] );
	}

	/**
	 * Test adding site blocks.
	 *
	 * @covers ::add_site_block
	 * @covers ::get_site_blocks
	 * @covers ::get_site_option_key_for_type
	 */
	public function test_add_site_block() {
		$this->assertTrue( Moderation::add_site_block( 'actor', 'https://bad.example.com/@spammer' ) );
		$this->assertTrue( Moderation::add_site_block( 'domain', 'spam-instance.com' ) );
		$this->assertTrue( Moderation::add_site_block( 'keyword', 'advertisement' ) );

		$blocks = Moderation::get_site_blocks();
		$this->assertContains( 'https://bad.example.com/@spammer', $blocks['actors'] );
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
		$actor = 'https://bad.example.com/@spammer';

		$this->assertNotFalse( Moderation::add_site_block( 'actor', $actor ) );
		$this->assertTrue( Moderation::add_site_block( 'actor', $actor ) );

		$blocks = Moderation::get_site_blocks();
		$this->assertCount( 1, $blocks['actors'] );
	}

	/**
	 * Test removing site blocks.
	 *
	 * @covers ::remove_site_block
	 * @covers ::add_site_block
	 * @covers ::get_site_blocks
	 */
	public function test_remove_site_block() {
		$actor = 'https://bad.example.com/@spammer';

		Moderation::add_site_block( 'actor', $actor );
		$this->assertTrue( Moderation::remove_site_block( 'actor', $actor ) );

		$blocks = Moderation::get_site_blocks();
		$this->assertNotContains( $actor, $blocks['actors'] );
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
		Moderation::add_site_block( 'actor', 'https://spam.example.com/@baduser' );
		Moderation::add_site_block( 'domain', 'spam-instance.com' );
		Moderation::add_site_block( 'keyword', 'buy now' );

		// Test actor blocking.
		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://spam.example.com/@baduser',
			'object' => array(
				'type'    => 'Note',
				'content' => 'Hello world',
			),
		);
		$this->assertTrue( Moderation::activity_is_blocked( $activity ) );

		// Test domain blocking.
		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://spam-instance.com/@anyuser',
			'object' => array(
				'type'    => 'Note',
				'content' => 'Hello world',
			),
		);
		$this->assertTrue( Moderation::activity_is_blocked( $activity ) );

		// Test keyword blocking.
		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://good.example.com/@user',
			'object' => array(
				'type'    => 'Note',
				'content' => 'Check out this product, buy now!',
			),
		);
		$this->assertTrue( Moderation::activity_is_blocked( $activity ) );

		// Test non-blocked activity.
		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://good.example.com/@user',
			'object' => array(
				'type'    => 'Note',
				'content' => 'Hello everyone!',
			),
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
		Moderation::add_user_block( $this->test_user_id, 'actor', 'https://annoying.example.com/@user' );
		Moderation::add_user_block( $this->test_user_id, 'domain', 'noise-instance.com' );
		Moderation::add_user_block( $this->test_user_id, 'keyword', 'politics' );

		// Test activity blocked for specific user but not site-wide.
		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://annoying.example.com/@user',
			'object' => array(
				'type'    => 'Note',
				'content' => 'Hello world',
			),
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
		$actor = 'https://test.example.com/@user';

		// Add site-wide block.
		Moderation::add_site_block( 'actor', $actor );

		$activity = array(
			'type'   => 'Create',
			'actor'  => $actor,
			'object' => array(
				'type'    => 'Note',
				'content' => 'Hello world',
			),
		);

		// Should be blocked site-wide (takes precedence).
		$this->assertTrue( Moderation::activity_is_blocked( $activity ) );
		$this->assertTrue( Moderation::activity_is_blocked( $activity, $this->test_user_id ) );
	}

	/**
	 * Test activity blocking with complex actor formats.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::activity_is_blocked_site_wide
	 * @covers ::add_site_block
	 */
	public function test_activity_blocking_actor_formats() {
		// Test with actor as string.
		Moderation::add_site_block( 'actor', 'https://example.com/@user' );

		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://example.com/@user',
			'object' => array(
				'type'    => 'Note',
				'content' => 'Test',
			),
		);
		$this->assertTrue( Moderation::activity_is_blocked( $activity ) );

		// Test with actor as object.
		$activity = array(
			'type'   => 'Create',
			'actor'  => array(
				'id'   => 'https://example.com/@user',
				'type' => 'Person',
			),
			'object' => array(
				'type'    => 'Note',
				'content' => 'Test',
			),
		);
		$this->assertTrue( Moderation::activity_is_blocked( $activity ) );
	}

	/**
	 * Test edge cases with malformed activity data.
	 *
	 * @covers ::activity_is_blocked
	 */
	public function test_activity_blocking_edge_cases() {
		// Test with empty activity.
		$this->assertFalse( Moderation::activity_is_blocked( array() ) );

		// Test with null activity.
		$this->assertFalse( Moderation::activity_is_blocked( null ) );

		// Test with non-array activity.
		$this->assertFalse( Moderation::activity_is_blocked( 'invalid' ) );

		// Test with missing actor.
		$activity = array(
			'type'   => 'Create',
			'object' => array(
				'type'    => 'Note',
				'content' => 'Test',
			),
		);
		$this->assertFalse( Moderation::activity_is_blocked( $activity ) );

		// Test with empty actor.
		$activity = array(
			'type'   => 'Create',
			'actor'  => '',
			'object' => array(
				'type'    => 'Note',
				'content' => 'Test',
			),
		);
		$this->assertFalse( Moderation::activity_is_blocked( $activity ) );

		// Test with malformed actor object.
		$activity = array(
			'type'   => 'Create',
			'actor'  => array(
				'type' => 'Person',
				// Missing 'id' field.
			),
			'object' => array(
				'type'    => 'Note',
				'content' => 'Test',
			),
		);
		$this->assertFalse( Moderation::activity_is_blocked( $activity ) );
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
			$activity = array(
				'type'   => 'Create',
				'actor'  => $url,
				'object' => array(
					'type'    => 'Note',
					'content' => 'Test',
				),
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
			$activity = array(
				'type'   => 'Create',
				'actor'  => 'https://example.com/@user',
				'object' => array(
					'type'    => 'Note',
					'content' => $content,
				),
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
		$this->assertNotFalse( Moderation::add_user_block( 99999, 'actor', 'https://example.com/@user' ) );
		$this->assertTrue( Moderation::remove_user_block( 99999, 'actor', 'https://example.com/@user' ) );

		// Test with zero user ID - WordPress treats user ID 0 specially, may return false.
		$result = Moderation::add_user_block( 0, 'actor', 'https://example.com/@user' );
		// User ID 0 might be handled differently by WordPress, so we allow both true/false.
		$this->assertFalse( $result );

		// Test with negative user ID.
		$this->assertNotFalse( Moderation::add_user_block( -1, 'actor', 'https://example.com/@user' ) );
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
		$actors = array(
			'https://example.com/@user1',
			'https://example.com/@user2',
			'https://example.com/@user3',
		);

		// Add all actors.
		foreach ( $actors as $actor ) {
			Moderation::add_user_block( $this->test_user_id, 'actor', $actor );
		}

		// Remove middle actor.
		Moderation::remove_user_block( $this->test_user_id, 'actor', $actors[1] );

		$blocks = Moderation::get_user_blocks( $this->test_user_id );

		// Array should be properly re-indexed.
		$this->assertCount( 2, $blocks['actors'] );
		$this->assertContains( $actors[0], $blocks['actors'] );
		$this->assertContains( $actors[2], $blocks['actors'] );
		$this->assertNotContains( $actors[1], $blocks['actors'] );

		// Keys should be sequential.
		$this->assertEquals( array_values( $blocks['actors'] ), $blocks['actors'] );
	}

	/**
	 * Test WordPress comment disallowed list fallback.
	 *
	 * @covers ::activity_is_blocked
	 */
	public function test_wordpress_disallowed_list_fallback() {
		\update_option( 'disallowed_keys', "badword\nspam.example.com" );

		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://good.example.com/@user',
			'object' => array(
				'type'    => 'Note',
				'content' => 'This contains badword in it',
			),
		);

		// Should be blocked by WordPress disallowed list.
		$this->assertTrue( Moderation::activity_is_blocked( $activity ) );

		// Clean up.
		\delete_option( 'disallowed_keys' );
	}
}
