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
	 * Number of remote resolutions performed by the aliased-actor mock.
	 *
	 * @var int
	 */
	private $resolve_count = 0;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		// Create a test user.
		$this->test_user_id = self::factory()->user->create(
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

		// Mock for webfinger testing.
		$webfinger_test_actors = array(
			'acct:spammer@bad.example.com',
			'https://bad.example.com/@spammer',
			'https://bad.example.com/user/spammer',
			'https://bad.example.com/users/spammer',
		);

		if ( \in_array( $url_or_object, $webfinger_test_actors, true ) ) {
			$response = array(
				'id'                => $url_or_object,
				'type'              => 'Person',
				'guid'              => $url_or_object,
				'preferredUsername' => 'spammer',
				'name'              => 'Test Spammer',
			);
		}

		// Mock for is_actor_blocked testing.
		$actor_test_urls = array(
			'https://example.com/@baduser',
			'https://annoying.example.com/@spammer',
			'https://other.example.com/@user3',
		);

		if ( \in_array( $url_or_object, $actor_test_urls, true ) ) {
			$response = array(
				'id'                => $url_or_object,
				'type'              => 'Person',
				'guid'              => $url_or_object,
				'preferredUsername' => 'testactor',
				'name'              => 'Test Actor',
				'inbox'             => 'https://example.com/inbox',
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

		// Clean up actor posts with blocking metadata.
		$actor_posts = \get_posts(
			array(
				'post_type'   => 'ap_actor',
				'numberposts' => -1,
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => Moderation::BLOCKED_ACTORS_META_KEY,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $actor_posts as $post ) {
			\delete_post_meta( $post->ID, Moderation::BLOCKED_ACTORS_META_KEY );
		}

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

		// Test keyword blocking.
		/* @var Activity $activity Activity. */
		$activity = Activity::init_from_array(
			array(
				'type'   => 'Create',
				'actor'  => 'https://good.example.com/@user',
				'object' => array(
					'id'          => 'https://example.com/note/1',
					'type'        => 'Note',
					'content_map' => array(
						'en' => 'Check out this product, buy now!',
						'de' => 'Überprüfe dieses Produkt, kaufe jetzt!',
					),
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

		// A malformed actor with no 'id' should gracefully return false, not trigger a PHP notice.
		$this->assertFalse( Moderation::activity_is_blocked( $activity ) );
	}

	/**
	 * Build a Follow activity from a given actor URI.
	 *
	 * @param string $actor The actor URI.
	 *
	 * @return Activity The activity.
	 */
	private function follow_from( $actor ) {
		return Activity::init_from_array(
			array(
				'type'   => 'Follow',
				'actor'  => $actor,
				'object' => 'https://example.org/author/1',
			)
		);
	}

	/**
	 * Serve one actor document under its canonical id, whatever URI it is asked for.
	 *
	 * Models `Http::get_remote_object()`, which follows a document's declared id and requires it
	 * to self-confirm, so any alias on the host resolves to the same canonical actor.
	 *
	 * @param mixed  $response      The pre-filtered response.
	 * @param string $url_or_object The URL being requested.
	 *
	 * @return mixed The mocked response or the original response.
	 */
	public function mock_aliased_actor( $response, $url_or_object ) {
		// Counted for every URL, so a test asserting that nothing was resolved can actually fail.
		++$this->resolve_count;

		if ( \str_starts_with( (string) $url_or_object, 'https://blocked.example.com/' ) ) {
			return array(
				'id'                => 'https://blocked.example.com/users/evil',
				'type'              => 'Person',
				'guid'              => 'https://blocked.example.com/users/evil',
				'preferredUsername' => 'evil',
				'name'              => 'Blocked Actor',
				'inbox'             => 'https://blocked.example.com/inbox',
			);
		}

		return $response;
	}

	/**
	 * Test that a blocked actor cannot get through by respelling their own URI.
	 *
	 * The signature only binds the actor to a host, so the delivered `actor` string is under the
	 * sender's control. Every spelling below is the same URI, and `id_matches_url()` already
	 * treats them as one identity when confirming an actor document.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::check_activity_against_blocks
	 */
	public function test_actor_block_matches_uri_variants() {
		\add_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_aliased_actor' ), 20, 2 );

		Moderation::add_site_block( 'actor', 'https://blocked.example.com/users/evil' );

		$variants = array(
			'https://blocked.example.com/users/evil',
			'https://blocked.example.com/users/evil/',
			'https://BLOCKED.example.com/users/evil',
			'https://blocked.example.com:443/users/evil',
			'https://blocked.example.com/users/evil#main-key',
		);

		foreach ( $variants as $variant ) {
			$this->assertTrue(
				Moderation::activity_is_blocked( $this->follow_from( $variant ) ),
				"Actor $variant should be blocked"
			);
		}

		\remove_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_aliased_actor' ), 20 );
	}

	/**
	 * Test that an alias resolving to a blocked actor is blocked.
	 *
	 * A different path on the same host is not the same URI, so normalizing the string cannot
	 * catch it. It is only a match once it resolves to the blocked id.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::check_activity_against_blocks
	 */
	public function test_actor_block_matches_resolved_alias() {
		\add_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_aliased_actor' ), 20, 2 );

		Moderation::add_site_block( 'actor', 'https://blocked.example.com/users/evil' );

		$this->assertTrue(
			Moderation::activity_is_blocked( $this->follow_from( 'https://blocked.example.com/@evil?x=1' ) ),
			'An alias that resolves to the blocked actor should be blocked.'
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_aliased_actor' ), 20 );
	}

	/**
	 * Test that resolution is limited to hosts that have a blocked actor.
	 *
	 * No actor on another host can resolve to a blocked id, so an unrelated delivery must not
	 * cost an outbound request.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::check_activity_against_blocks
	 */
	public function test_actor_block_does_not_resolve_unrelated_hosts() {
		\add_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_aliased_actor' ), 20, 2 );

		Moderation::add_site_block( 'actor', 'https://blocked.example.com/users/evil' );

		$this->resolve_count = 0;

		$this->assertFalse(
			Moderation::activity_is_blocked( $this->follow_from( 'https://elsewhere.example.com/users/someone' ) ),
			'An actor on an unblocked host should not be blocked.'
		);
		$this->assertSame( 0, $this->resolve_count, 'An unrelated host should not be resolved.' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_aliased_actor' ), 20 );
	}

	/**
	 * Test that a blocked domain is never contacted to resolve an actor.
	 *
	 * The actor check can go to the network, so it has to run after the domain check: a host the
	 * admin has blocked outright must not receive a request just to decide whether to block it.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::check_activity_against_blocks
	 */
	public function test_blocked_domain_is_not_resolved() {
		\add_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_aliased_actor' ), 20, 2 );

		Moderation::add_site_block( 'actor', 'https://blocked.example.com/users/evil' );
		Moderation::add_site_block( 'domain', 'blocked.example.com' );

		$this->resolve_count = 0;

		$this->assertTrue(
			Moderation::activity_is_blocked( $this->follow_from( 'https://blocked.example.com/@someone-else' ) ),
			'An actor on a blocked domain should be blocked.'
		);
		$this->assertSame( 0, $this->resolve_count, 'A blocked domain should not be contacted.' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'mock_aliased_actor' ), 20 );
	}

	/**
	 * Test that is_actor_blocked() matches URI variants too.
	 *
	 * This is the accessor the admin screens use, and it compares against the same block list,
	 * so it has to agree with the inbox gate about what counts as the same actor.
	 *
	 * @covers ::is_actor_blocked
	 */
	public function test_is_actor_blocked_matches_uri_variants() {
		$actor_uri = 'https://example.com/@baduser';

		Moderation::add_site_block( 'actor', $actor_uri );

		$variants = array(
			$actor_uri,
			'https://example.com/@baduser/',
			'https://EXAMPLE.com/@baduser',
			'https://example.com:443/@baduser',
			'https://example.com/@baduser#main-key',
		);

		foreach ( $variants as $variant ) {
			$this->assertTrue( Moderation::is_actor_blocked( $variant ), "Actor $variant should be blocked" );
			$this->assertTrue( Moderation::is_actor_blocked( $variant, $this->test_user_id ), "Actor $variant should be blocked for the user" );
		}

		// A different path on the same host is a different actor.
		$this->assertFalse( Moderation::is_actor_blocked( 'https://example.com/@gooduser' ) );

		Moderation::remove_site_block( 'actor', $actor_uri );
	}

	/**
	 * Test that is_actor_blocked() folds host case for domain blocks.
	 *
	 * @covers ::is_actor_blocked
	 */
	public function test_is_actor_blocked_domain_is_case_insensitive() {
		Moderation::add_site_block( 'domain', 'spam.example.com' );

		$this->assertTrue( Moderation::is_actor_blocked( 'https://SPAM.example.com/@user' ) );
		$this->assertFalse( Moderation::is_actor_blocked( 'https://good.example.com/@user' ) );
	}

	/**
	 * Test that an acct identifier is matched against blocked domains before it is resolved.
	 *
	 * `wp_parse_url()` finds no host in `acct:user@host`, so without reading the host off the
	 * identifier a domain block is missed whenever the webfinger lookup fails, and the lookup
	 * itself contacts a host the admin has blocked.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::check_activity_against_blocks
	 */
	public function test_acct_actor_matches_blocked_domain_without_resolving() {
		Moderation::add_site_block( 'domain', 'blocked.example.com' );

		$this->assertTrue(
			Moderation::activity_is_blocked( $this->follow_from( 'acct:evil@blocked.example.com' ) ),
			'An acct identifier on a blocked domain should be blocked.'
		);
		$this->assertTrue(
			Moderation::activity_is_blocked( $this->follow_from( 'acct:evil@BLOCKED.example.com' ) ),
			'An acct host should be folded like any other host.'
		);
		$this->assertFalse(
			Moderation::activity_is_blocked( $this->follow_from( 'acct:someone@good.example.com' ) ),
			'An acct identifier on an unblocked domain should not be blocked.'
		);
	}

	/**
	 * Test that only actual handles are read as handles.
	 *
	 * A URI in another scheme can carry an `@` in its path, and reading the host off that would
	 * report the path segment as the host and miss the domain block.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::check_activity_against_blocks
	 */
	public function test_domain_block_matches_non_http_schemes() {
		Moderation::add_site_block( 'domain', 'blocked.example.com' );

		$actors = array(
			'ftp://blocked.example.com/@user',
			'//blocked.example.com/@user',
		);

		foreach ( $actors as $actor ) {
			$this->assertTrue(
				Moderation::activity_is_blocked( $this->follow_from( $actor ) ),
				"Actor $actor should match the blocked domain"
			);
		}
	}

	/**
	 * Test that a handle resolving to a blocked host is blocked.
	 *
	 * WebFinger returns whatever `href` the remote document names, so a handle on an allowed
	 * host can point at an actor on a blocked one.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::check_activity_against_blocks
	 */
	public function test_webfinger_resolving_to_blocked_host_is_blocked() {
		\add_filter( 'pre_http_request', array( $this, 'mock_webfinger_to_blocked_host' ), 10, 3 );

		Moderation::add_site_block( 'domain', 'blocked.example.com' );

		$this->assertTrue(
			Moderation::activity_is_blocked( $this->follow_from( 'acct:evil@good.example.com' ) ),
			'A handle resolving to a blocked host should be blocked.'
		);

		\remove_filter( 'pre_http_request', array( $this, 'mock_webfinger_to_blocked_host' ), 10 );
	}

	/**
	 * Serve a WebFinger document whose self link points at another host.
	 *
	 * @param mixed  $response The pre-empted response.
	 * @param array  $args     The request arguments.
	 * @param string $url      The request URL.
	 *
	 * @return mixed The mocked response or the original value.
	 */
	public function mock_webfinger_to_blocked_host( $response, $args, $url ) {
		if ( ! \str_contains( (string) $url, '/.well-known/webfinger' ) ) {
			return $response;
		}

		return array(
			'headers'  => array(),
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode(
				array(
					'subject' => 'acct:evil@good.example.com',
					'links'   => array(
						array(
							'rel'  => 'self',
							'type' => 'application/activity+json',
							'href' => 'https://blocked.example.com/users/evil',
						),
					),
				)
			),
		);
	}

	/**
	 * Test that domain blocks fold host case.
	 *
	 * @covers ::activity_is_blocked
	 * @covers ::check_activity_against_blocks
	 */
	public function test_domain_blocking_is_case_insensitive() {
		Moderation::add_site_block( 'domain', 'spam-instance.com' );

		$this->assertTrue(
			Moderation::activity_is_blocked( $this->follow_from( 'https://SPAM-Instance.com/@anyuser' ) ),
			'A mixed-case host should match a blocked domain.'
		);
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

		/* @type Activity $activity Activity object */
		$activity = Activity::init_from_array( $data );

		$this->assertEquals( $expected, Moderation::activity_is_blocked( $activity ) );

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

	/**
	 * Test that webfinger actors are resolved to URLs for blocking.
	 *
	 * @covers ::check_activity_against_blocks
	 */
	public function test_webfinger_actor_resolution() {
		// Mock webfinger resolution to return a URL.
		$mock_callback = function ( $response, $args, $url ) {
			if ( strpos( $url, '/.well-known/webfinger' ) !== false ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'subject' => 'acct:spammer@bad.example.com',
							'links'   => array(
								array(
									'rel'  => 'self',
									'type' => 'application/activity+json',
									'href' => 'https://bad.example.com/@spammer',
								),
							),
						)
					),
				);
			}
			return $response;
		};
		add_filter( 'pre_http_request', $mock_callback, 10, 3 );

		// Block the resolved URL.
		Moderation::add_site_block( 'domain', 'bad.example.com' );

		/* @type Activity $activity Activity object */
		$activity = Activity::init_from_array(
			array(
				'type'   => 'Create',
				'actor'  => 'spammer@bad.example.com',
				'object' => array(
					'id'      => 'https://example.com/note/1',
					'type'    => 'Note',
					'content' => 'Test content',
				),
			)
		);

		// Should be blocked because webfinger resolves to blocked domain.
		$this->assertTrue( Moderation::activity_is_blocked( $activity ) );

		// Clean up.
		Moderation::remove_site_block( 'domain', 'bad.example.com' );
		remove_filter( 'pre_http_request', $mock_callback );
	}

	/**
	 * Test is_actor_blocked with site-wide actor blocks.
	 *
	 * @covers ::is_actor_blocked
	 */
	public function test_is_actor_blocked_site_wide_actor() {
		$actor_uri = 'https://example.com/@baduser';

		// Add site-wide actor block.
		$result = Moderation::add_site_block( 'actor', $actor_uri );
		$this->assertTrue( $result, 'Failed to add site-wide actor block' );

		// Debug: check if the block was actually added.
		$site_blocks = Moderation::get_site_blocks();
		$this->assertNotEmpty( $site_blocks['actors'], 'Site blocks actors should not be empty' );
		$this->assertContains( $actor_uri, $site_blocks['actors'], 'Actor URI should be in site blocks' );

		// Should be blocked site-wide.
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri ) );
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri, $this->test_user_id ) );

		// Clean up.
		Moderation::remove_site_block( 'actor', $actor_uri );
	}

	/**
	 * Test is_actor_blocked with site-wide domain blocks.
	 *
	 * @covers ::is_actor_blocked
	 */
	public function test_is_actor_blocked_site_wide_domain() {
		$actor_uri      = 'https://spam.example.com/@anyuser';
		$blocked_domain = 'spam.example.com';

		// Add site-wide domain block.
		Moderation::add_site_block( 'domain', $blocked_domain );

		// Should be blocked site-wide.
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri ) );
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri, $this->test_user_id ) );

		// Different actor on same domain should also be blocked.
		$this->assertTrue( Moderation::is_actor_blocked( 'https://spam.example.com/@anotheruser' ) );

		// Actor on different domain should not be blocked.
		$this->assertFalse( Moderation::is_actor_blocked( 'https://good.example.com/@user' ) );

		// Clean up.
		Moderation::remove_site_block( 'domain', $blocked_domain );
	}

	/**
	 * Test is_actor_blocked with user-specific actor blocks.
	 *
	 * @covers ::is_actor_blocked
	 */
	public function test_is_actor_blocked_user_specific_actor() {
		$actor_uri = 'https://annoying.example.com/@spammer';

		// Add user-specific actor block.
		Moderation::add_user_block( $this->test_user_id, 'actor', $actor_uri );

		// Should be blocked for the specific user.
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri, $this->test_user_id ) );

		// Should not be blocked site-wide.
		$this->assertFalse( Moderation::is_actor_blocked( $actor_uri ) );

		// Should not be blocked for a different user.
		$other_user_id = self::factory()->user->create();
		$this->assertFalse( Moderation::is_actor_blocked( $actor_uri, $other_user_id ) );

		// Clean up.
		Moderation::remove_user_block( $this->test_user_id, 'actor', $actor_uri );
	}

	/**
	 * Test is_actor_blocked with user-specific domain blocks.
	 *
	 * @covers ::is_actor_blocked
	 */
	public function test_is_actor_blocked_user_specific_domain() {
		$actor_uri      = 'https://personal-block.example.com/@user';
		$blocked_domain = 'personal-block.example.com';

		// Add user-specific domain block.
		Moderation::add_user_block( $this->test_user_id, 'domain', $blocked_domain );

		// Should be blocked for the specific user.
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri, $this->test_user_id ) );

		// Should not be blocked site-wide.
		$this->assertFalse( Moderation::is_actor_blocked( $actor_uri ) );

		// Different actor on same domain should also be blocked for the user.
		$this->assertTrue( Moderation::is_actor_blocked( 'https://personal-block.example.com/@another', $this->test_user_id ) );

		// Clean up.
		Moderation::remove_user_block( $this->test_user_id, 'domain', $blocked_domain );
	}

	/**
	 * Test is_actor_blocked with hierarchical priority (site-wide takes precedence).
	 *
	 * @covers ::is_actor_blocked
	 */
	public function test_is_actor_blocked_hierarchical_priority() {
		$actor_uri = 'https://priority-test.example.com/@user';
		$domain    = 'priority-test.example.com';

		// Add site-wide domain block.
		Moderation::add_site_block( 'domain', $domain );

		// Should be blocked site-wide regardless of user-specific settings.
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri ) );
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri, $this->test_user_id ) );

		// Even with no user blocks, site-wide should take effect.
		$other_user_id = self::factory()->user->create();
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri, $other_user_id ) );

		// Clean up.
		Moderation::remove_site_block( 'domain', $domain );
	}

	/**
	 * Test is_actor_blocked with edge cases.
	 *
	 * @covers ::is_actor_blocked
	 */
	public function test_is_actor_blocked_edge_cases() {
		// Empty actor URI.
		$this->assertFalse( Moderation::is_actor_blocked( '' ) );
		$this->assertFalse( Moderation::is_actor_blocked( '', $this->test_user_id ) );

		// Null actor URI.
		$this->assertFalse( Moderation::is_actor_blocked( null ) );
		$this->assertFalse( Moderation::is_actor_blocked( null, $this->test_user_id ) );

		// Invalid user ID.
		$this->assertFalse( Moderation::is_actor_blocked( 'https://example.com/@user', 0 ) );
		$this->assertFalse( Moderation::is_actor_blocked( 'https://example.com/@user', -1 ) );
		$this->assertFalse( Moderation::is_actor_blocked( 'https://example.com/@user', 99999 ) );

		// Malformed URIs.
		$this->assertFalse( Moderation::is_actor_blocked( 'not-a-url' ) );
		$this->assertFalse( Moderation::is_actor_blocked( 'https://' ) );
		$this->assertFalse( Moderation::is_actor_blocked( 'ftp://example.com/@user' ) );
	}

	/**
	 * Test is_actor_blocked with various URL formats.
	 *
	 * @covers ::is_actor_blocked
	 */
	public function test_is_actor_blocked_url_formats() {
		$blocked_domain = 'blocked.example.com';
		Moderation::add_site_block( 'domain', $blocked_domain );

		// Test various URL formats.
		$test_cases = array(
			// Should be blocked.
			'https://blocked.example.com/@user'      => true,
			'http://blocked.example.com/@user'       => true,
			'https://blocked.example.com/users/test' => true,
			'https://blocked.example.com/'           => true,

			// Should not be blocked (different domains).
			'https://www.blocked.example.com/@user'  => false,
			'https://sub.blocked.example.com/@user'  => false,
			'https://blocked-example.com/@user'      => false,
			'https://notblocked.example.com/@user'   => false,
		);

		foreach ( $test_cases as $actor_uri => $expected ) {
			$result = Moderation::is_actor_blocked( $actor_uri );
			$this->assertEquals( $expected, $result, "Failed for URI: $actor_uri" );
		}

		// Clean up.
		Moderation::remove_site_block( 'domain', $blocked_domain );
	}

	/**
	 * Test is_actor_blocked with mixed blocking scenarios.
	 *
	 * @covers ::is_actor_blocked
	 */
	public function test_is_actor_blocked_mixed_scenarios() {
		$actor_uri1 = 'https://mixed.example.com/@user1';
		$actor_uri2 = 'https://mixed.example.com/@user2';
		$actor_uri3 = 'https://other.example.com/@user3';
		$domain     = 'mixed.example.com';

		// Add site-wide domain block.
		Moderation::add_site_block( 'domain', $domain );

		// Add user-specific actor block for different domain.
		Moderation::add_user_block( $this->test_user_id, 'actor', $actor_uri3 );

		// Test site-wide domain block.
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri1 ) );
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri2 ) );
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri1, $this->test_user_id ) );
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri2, $this->test_user_id ) );

		// Test user-specific actor block.
		$this->assertFalse( Moderation::is_actor_blocked( $actor_uri3 ) );
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri3, $this->test_user_id ) );

		// Create another user.
		$other_user_id = self::factory()->user->create();

		// Other user should only be affected by site-wide blocks.
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri1, $other_user_id ) );
		$this->assertTrue( Moderation::is_actor_blocked( $actor_uri2, $other_user_id ) );
		$this->assertFalse( Moderation::is_actor_blocked( $actor_uri3, $other_user_id ) );

		// Clean up.
		Moderation::remove_site_block( 'domain', $domain );
		Moderation::remove_user_block( $this->test_user_id, 'actor', $actor_uri3 );
	}
}
