<?php
/**
 * Test Mastodon import class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\WP_Admin\Import;

use Activitypub\WP_Admin\Import\Mastodon;
use ReflectionClass;

/**
 * Test Mastodon import class.
 */
class Test_Mastodon extends \WP_UnitTestCase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		// Create a test user for imports.
		$this->user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
	}

	/**
	 * Test that import_posts() handles stdClass objects correctly.
	 *
	 * This reproduces the bug reported in:
	 * https://wordpress.org/support/topic/import-mastodon-beta/page/2/#post-18701387
	 *
	 * The bug occurs because:
	 * 1. Mastodon::import() uses json_decode() without the associative flag (line 236)
	 * 2. This creates stdClass objects instead of arrays
	 * 3. get_object_vars() is used on line 287 to convert top-level to array
	 * 4. But nested 'object' property remains a stdClass
	 * 5. extract_recipients_from_activity_property() fails with "Cannot use object of type stdClass as array"
	 */
	public function test_import_posts_with_stdclass_objects() {
		// Create a realistic Mastodon outbox.json structure.
		$outbox_json = wp_json_encode(
			array(
				'@context'     => 'https://www.w3.org/ns/activitystreams',
				'id'           => 'https://mastodon.social/users/example/outbox',
				'type'         => 'OrderedCollection',
				'orderedItems' => array(
					// Public Create activity with nested recipients.
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'cc'        => array( 'https://mastodon.social/users/example/followers' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Hello world from Mastodon!</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'cc'        => array( 'https://mastodon.social/users/example/followers' ),
							'tag'       => array(),
						),
					),
					// Activity with only nested recipients (no top-level to/cc).
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/2/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T11:00:00Z',
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/2',
							'type'      => 'Note',
							'content'   => '<p>Another public post</p>',
							'published' => '2024-01-15T11:00:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		/*
		 * Simulate what Mastodon import does: json_decode WITH associative flag.
		 * This ensures all data is arrays, not stdClass objects.
		 */
		$outbox = json_decode( $outbox_json, true );

		// Use reflection to set the private static properties.
		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		/*
		 * Call the import_posts method.
		 * This should NOT throw a fatal error "Cannot use object of type stdClass as array".
		 */
		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();

		// If we get here without a fatal error, the bug is fixed!
		$this->assertTrue( $result, 'import_posts should return true on success' );
		$this->assertStringContainsString( 'Imported 2 posts', $output, 'Should output import count' );

		// Verify posts were created.
		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 2, $posts, 'Should import 2 public posts' );
	}

	/**
	 * Test that private posts are skipped during import.
	 */
	public function test_import_posts_skips_private_posts() {
		// Create outbox with both public and private posts.
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					// Public post.
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Public post</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
					// Private post (no public recipient).
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/2/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T11:00:00Z',
						'to'        => array( 'https://mastodon.social/users/alice' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/2',
							'type'      => 'Note',
							'content'   => '<p>Private message</p>',
							'published' => '2024-01-15T11:00:00Z',
							'to'        => array( 'https://mastodon.social/users/alice' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Imported 1 post', $output, 'Should output import count' );

		// Should only import the public post, not the private one.
		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 1, $posts, 'Should only import 1 public post, skipping the private one' );
	}

	/**
	 * Test that Announce activities (boosts) are skipped.
	 */
	public function test_import_posts_skips_announce_activities() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					// Public Create activity.
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Original post</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
					// Announce activity (boost/reblog).
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/2/activity',
						'type'      => 'Announce',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T11:00:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => 'https://mastodon.social/users/other/statuses/123',
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Imported 1 post', $output, 'Should output import count' );

		// Should only import the Create activity, not the Announce.
		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 1, $posts, 'Should only import 1 Create activity, skipping Announce' );
	}

	/**
	 * Test importing posts with hashtags.
	 */
	public function test_import_posts_with_hashtags() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Post with #hashtag and #another</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(
								array(
									'type' => 'Hashtag',
									'name' => '#hashtag',
									'href' => 'https://mastodon.social/tags/hashtag',
								),
								array(
									'type' => 'Hashtag',
									'name' => '#another',
									'href' => 'https://mastodon.social/tags/another',
								),
							),
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Imported', $output, 'Should output import message' );

		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 1, $posts, 'Should import 1 post with hashtags' );

		// Check that tags were added.
		$tags = wp_get_post_tags( $posts[0]->ID, array( 'fields' => 'names' ) );
		$this->assertContains( 'hashtag', $tags, 'Should have hashtag tag' );
		$this->assertContains( 'another', $tags, 'Should have another tag' );
	}

	/**
	 * Test importing posts with summary (content warning).
	 */
	public function test_import_posts_with_summary() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'summary'   => 'Content Warning',
							'content'   => '<p>Sensitive content here</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Imported', $output, 'Should output import message' );

		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 1, $posts, 'Should import 1 post with summary' );
		$this->assertSame( 'Content Warning', $posts[0]->post_excerpt, 'Should use summary as excerpt' );
		// Content should be converted to blocks by the filter hook.
		$this->assertStringContainsString( '<p>Sensitive content here</p>', $posts[0]->post_content, 'Should have content' );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $posts[0]->post_content, 'Should be converted to blocks' );
	}

	/**
	 * Test importing posts without tags array.
	 */
	public function test_import_posts_without_tags() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Post without tags</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							// No 'tag' field at all.
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		// Should not throw an error about missing 'tag' key.
		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Imported', $output, 'Should output import message' );

		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 1, $posts, 'Should import 1 post without tags' );
	}

	/**
	 * Test that post metadata is set correctly.
	 */
	public function test_import_posts_sets_metadata() {
		$source_id = 'https://mastodon.social/users/example/statuses/123456';

		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/123456/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => $source_id,
							'type'      => 'Note',
							'content'   => '<p>Test post</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Imported', $output, 'Should output import message' );

		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 1, $posts );

		// Check metadata.
		$post_source_id = get_post_meta( $posts[0]->ID, '_source_id', true );
		$this->assertSame( $source_id, $post_source_id, 'Should set _source_id meta' );

		// Check post format.
		$post_format = get_post_format( $posts[0]->ID );
		$this->assertSame( 'status', $post_format, 'Should set post format to status' );
	}

	/**
	 * Test that duplicate posts are skipped.
	 */
	public function test_import_posts_skips_duplicates() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Unique content for duplicate test</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		// First import.
		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();
		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Imported 1 post', $output, 'Should output import count' );

		$posts_after_first = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 1, $posts_after_first );

		// Second import with same data.
		$outbox_property->setValue( null, $outbox );
		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();
		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Skipped posts', $output, 'Should output skipped message' );
		$this->assertStringContainsString( 'Imported 0 posts', $output, 'Should output zero imports' );

		$posts_after_second = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		// Should still be 1 post, not 2.
		$this->assertCount( 1, $posts_after_second, 'Should skip duplicate posts' );
	}

	/**
	 * Test posts with different recipient field combinations.
	 */
	public function test_import_posts_with_different_recipient_fields() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					// Post with 'to' field.
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Public via to</p>',
							'published' => '2024-01-15T10:30:00Z',
							'tag'       => array(),
						),
					),
					// Post with 'cc' field.
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/2/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:31:00Z',
						'cc'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/2',
							'type'      => 'Note',
							'content'   => '<p>Public via cc</p>',
							'published' => '2024-01-15T10:31:00Z',
							'tag'       => array(),
						),
					),
					// Post with nested object 'to'.
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/3/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:32:00Z',
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/3',
							'type'      => 'Note',
							'content'   => '<p>Public via object.to</p>',
							'published' => '2024-01-15T10:32:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Imported 3 posts', $output, 'Should output import count' );

		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		// All 3 should be imported as they're all public.
		$this->assertCount( 3, $posts, 'Should import all 3 public posts with different recipient fields' );
	}

	/**
	 * Test that self-replies are imported as comments.
	 */
	public function test_import_self_replies_as_comments() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					// Root post.
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Root post</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
					// Self-reply (thread continuation).
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/2/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:35:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/2',
							'type'      => 'Note',
							'content'   => '<p>Thread continuation</p>',
							'published' => '2024-01-15T10:35:00Z',
							'inReplyTo' => 'https://mastodon.social/users/example/statuses/1',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();

		$this->assertTrue( $result );

		// Should have 1 post.
		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 1, $posts, 'Should import 1 root post' );
		$this->assertStringContainsString( 'Root post', $posts[0]->post_content );

		// Should have 1 comment on that post.
		$comments = get_comments( array( 'post_id' => $posts[0]->ID ) );
		$this->assertCount( 1, $comments, 'Should import 1 self-reply as comment' );
		$this->assertStringContainsString( 'Thread continuation', $comments[0]->comment_content );

		// Check comment metadata.
		$source_id = get_comment_meta( $comments[0]->comment_ID, 'source_id', true );
		$this->assertSame( 'https://mastodon.social/users/example/statuses/2', $source_id );

		// Verify output messages.
		$this->assertStringContainsString( 'Imported 1 post', $output );
		$this->assertStringContainsString( 'Imported 1 comment', $output );
	}

	/**
	 * Test that script markup in an archived self-reply is stripped before storage.
	 *
	 * Covers scripts and inline styles. Self-replies are committed with
	 * wp_insert_comment(), which does not run the `pre_comment_*` filter chain, so nothing
	 * else on this path would filter the content. Core prints `comment_content` unescaped
	 * on the front end and in the Dashboard "Activity" widget, so a `position:fixed`
	 * comment would cover the page.
	 */
	public function test_import_self_replies_sanitizes_comment_content() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Root post</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/2/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:35:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/2',
							'type'      => 'Note',
							'content'   => '<div style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:99999;background-image:url(https://remote.example/track)">Reply<script>alert(1)</script><iframe srcdoc="x"></iframe><img src=x onerror="alert(1)"></div>',
							'published' => '2024-01-15T10:35:00Z',
							'inReplyTo' => 'https://mastodon.social/users/example/statuses/1',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$this->set_up_import( json_decode( $outbox_json, true ) );

		ob_start();
		Mastodon::import_posts();
		ob_get_clean();

		$posts    = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);
		$comments = get_comments( array( 'post_id' => $posts[0]->ID ) );

		$this->assertCount( 1, $comments, 'Should import 1 self-reply as comment' );

		$content = $comments[0]->comment_content;

		$this->assertStringNotContainsString( '<script', $content, 'Script tags must not be stored.' );
		$this->assertStringNotContainsString( '<iframe', $content, 'Iframes must not be stored.' );
		$this->assertStringNotContainsString( 'onerror', $content, 'Event handlers must not be stored.' );
		$this->assertStringNotContainsString( 'style=', $content, 'Inline styles must not be stored.' );
		$this->assertStringNotContainsString( 'position:fixed', $content, 'CSS positioning must not survive.' );
		$this->assertStringNotContainsString( 'remote.example/track', $content, 'A CSS background URL must not be stored.' );
		$this->assertStringContainsString( 'Reply', $content, 'Legitimate content should survive.' );
	}

	/**
	 * Test that script markup in an archived post is stripped before storage.
	 *
	 * Covers scripts and inline styles. The import runs as a user with `unfiltered_html`,
	 * so kses filters are not installed for the request and wp_insert_post() stores the
	 * archive's content as-is. These posts are published publicly, so nothing else filters
	 * them before display.
	 *
	 * The style assertions are on the excerpt. `post_content` is rebuilt from the bare
	 * `<p>` matches by {@see \Activitypub\Blocks::filter_import_mastodon_post_data()}, so
	 * a styled element there is dropped for an unrelated reason and would pass whether or
	 * not the sanitizer does anything.
	 */
	public function test_import_posts_sanitizes_post_content() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'summary'   => '<span style="background-image:url(https://remote.example/track)">Heads up</span><script>alert(1)</script>',
							'content'   => '<p>Post<script>alert(1)</script><iframe srcdoc="x"></iframe><img src=x onerror="alert(1)"></p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$this->set_up_import( json_decode( $outbox_json, true ) );

		ob_start();
		Mastodon::import_posts();
		ob_get_clean();

		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 1, $posts, 'Should import 1 post' );

		$this->assertStringNotContainsString( '<script', $posts[0]->post_content, 'Script tags must not be stored.' );
		$this->assertStringNotContainsString( '<iframe', $posts[0]->post_content, 'Iframes must not be stored.' );
		$this->assertStringNotContainsString( 'onerror', $posts[0]->post_content, 'Event handlers must not be stored.' );
		$this->assertStringContainsString( 'Post', $posts[0]->post_content, 'Legitimate content should survive.' );

		$this->assertStringNotContainsString( '<script', $posts[0]->post_excerpt, 'Script tags must not be stored in the excerpt.' );
		$this->assertStringNotContainsString( 'style=', $posts[0]->post_excerpt, 'Inline styles must not be stored in the excerpt.' );
		$this->assertStringNotContainsString( 'remote.example/track', $posts[0]->post_excerpt, 'A CSS background URL must not be stored.' );
		$this->assertStringContainsString( 'Heads up', $posts[0]->post_excerpt, 'Legitimate excerpt text should survive.' );
	}

	/**
	 * Test that re-importing an archive does not duplicate posts whose content changed.
	 *
	 * De-duplication used to key on an exact `post_content` match, so any change to what
	 * the importer stores (a new sanitizer, say) made previously imported posts stop
	 * matching and come back as duplicates. `_source_id` is the archive's own identifier
	 * and does not move.
	 */
	public function test_import_posts_does_not_duplicate_when_stored_content_differs() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Post</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		// Model an older import: same source id, content stored the way a previous version did.
		$existing_id = self::factory()->post->create(
			array(
				'post_author'  => $this->user_id,
				'post_content' => '<p style="color:red">Post</p>',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);
		\update_post_meta( $existing_id, '_source_id', 'https://mastodon.social/users/example/statuses/1' );

		$this->set_up_import( $outbox );

		ob_start();
		Mastodon::import_posts();
		ob_get_clean();

		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 1, $posts, 'A post already imported under this source id must not be imported again.' );
		$this->assertSame( $existing_id, $posts[0]->ID, 'The existing post should be the one that is kept.' );
	}

	/**
	 * Test that replies to an already-imported post are still threaded onto it.
	 *
	 * Pass 3 maps self-replies onto their parent through the id pass 2 returns. A post
	 * matched by `_source_id` is reported as skipped, but it is still the parent, so
	 * dropping it from the mapping would silently discard every reply to it.
	 */
	public function test_import_posts_threads_replies_onto_an_already_imported_post() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Root post</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/2/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:35:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/2',
							'type'      => 'Note',
							'content'   => '<p>Thread continuation</p>',
							'published' => '2024-01-15T10:35:00Z',
							'inReplyTo' => 'https://mastodon.social/users/example/statuses/1',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		// An older import of the root post, with content a previous version stored.
		$existing_id = self::factory()->post->create(
			array(
				'post_author'  => $this->user_id,
				'post_content' => '<p style="color:red">Root post</p>',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);
		\update_post_meta( $existing_id, '_source_id', 'https://mastodon.social/users/example/statuses/1' );

		$this->set_up_import( json_decode( $outbox_json, true ) );

		ob_start();
		Mastodon::import_posts();
		ob_get_clean();

		$comments = get_comments( array( 'post_id' => $existing_id ) );

		$this->assertCount( 1, $comments, 'The reply should be threaded onto the post that was already imported.' );
		$this->assertStringContainsString( 'Thread continuation', $comments[0]->comment_content );
	}

	/**
	 * Test that a trashed import is still matched, rather than re-created.
	 *
	 * `post_exists()` takes no status argument here, so before the source-id lookup existed
	 * it matched a trashed post on content and skipped it. `post_status => 'any'` excludes
	 * statuses flagged `exclude_from_search`, and trash is one, so the lookup that replaced
	 * it was narrower on exactly that axis.
	 */
	public function test_import_posts_matches_a_trashed_import() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Post</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		// An older import, trashed by the user, whose stored content a newer sanitizer changed.
		$existing_id = self::factory()->post->create(
			array(
				'post_author'  => $this->user_id,
				'post_content' => '<p style="color:red">Post</p>',
				'post_status'  => 'trash',
				'post_type'    => 'post',
			)
		);
		\update_post_meta( $existing_id, '_source_id', 'https://mastodon.social/users/example/statuses/1' );

		$this->set_up_import( json_decode( $outbox_json, true ) );

		ob_start();
		Mastodon::import_posts();
		ob_get_clean();

		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 0, $posts, 'A trashed import must not come back as a fresh published copy.' );
	}

	/**
	 * Set up the importer static state for an outbox fixture.
	 *
	 * @param array $outbox The decoded outbox.
	 */
	private function set_up_import( $outbox ) {
		/*
		 * Model the real import: it runs in wp-admin as the importing user, who is an
		 * administrator. `kses_init()` installs no kses filters for a user with
		 * `unfiltered_html`, which is the condition the sanitization under test exists
		 * for. Without this the suite runs as user 0, kses is active, and the tests
		 * would pass whether or not the importer sanitizes anything.
		 */
		\wp_set_current_user( $this->user_id );
		\kses_init();

		$reflection = new ReflectionClass( Mastodon::class );

		foreach ( array(
			'outbox'            => $outbox,
			'author'            => $this->user_id,
			'fetch_attachments' => false,
		) as $name => $value ) {
			$property = $reflection->getProperty( $name );
			if ( \PHP_VERSION_ID < 80100 ) {
				$property->setAccessible( true );
			}
			$property->setValue( null, $value );
		}
	}

	/**
	 * Test that external replies are imported as posts (not comments).
	 */
	public function test_import_external_replies_as_posts() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					// Reply to external post.
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Reply to someone else</p>',
							'published' => '2024-01-15T10:30:00Z',
							'inReplyTo' => 'https://other.server/users/someone/statuses/999',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();

		$this->assertTrue( $result );
		$this->assertStringContainsString( 'Imported 1 post', $output );

		// Should import as a post, not a comment.
		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 1, $posts, 'External reply should be imported as a post' );
		$this->assertStringContainsString( 'Reply to someone else', $posts[0]->post_content );

		// Should not create any comments.
		$comments = get_comments( array( 'post_id' => $posts[0]->ID ) );
		$this->assertCount( 0, $comments, 'Should not create comments for external replies' );
	}

	/**
	 * Test nested self-reply threading (A → B → C).
	 */
	public function test_import_nested_self_replies() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					// Root post (A).
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:00:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Post A - Root</p>',
							'published' => '2024-01-15T10:00:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
					// Self-reply to A (B).
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/2/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:05:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/2',
							'type'      => 'Note',
							'content'   => '<p>Post B - Reply to A</p>',
							'published' => '2024-01-15T10:05:00Z',
							'inReplyTo' => 'https://mastodon.social/users/example/statuses/1',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
					// Self-reply to B (C).
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/3/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:10:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/3',
							'type'      => 'Note',
							'content'   => '<p>Post C - Reply to B</p>',
							'published' => '2024-01-15T10:10:00Z',
							'inReplyTo' => 'https://mastodon.social/users/example/statuses/2',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();

		$this->assertTrue( $result );

		// Should have 1 post.
		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 1, $posts, 'Should import 1 root post' );

		// Should have 2 comments (B and C).
		$comments = get_comments(
			array(
				'post_id' => $posts[0]->ID,
				'orderby' => 'comment_date',
				'order'   => 'ASC',
			)
		);

		$this->assertCount( 2, $comments, 'Should import 2 self-replies as comments' );

		// Comment B should be a top-level comment.
		$this->assertEquals( 0, $comments[0]->comment_parent, 'First comment should be top-level' );
		$this->assertStringContainsString( 'Post B', $comments[0]->comment_content );

		// Comment C should be a reply to B.
		$this->assertEquals( $comments[0]->comment_ID, $comments[1]->comment_parent, 'Second comment should be reply to first' );
		$this->assertStringContainsString( 'Post C', $comments[1]->comment_content );

		// Verify output.
		$this->assertStringContainsString( 'Imported 2 comments', $output );
	}

	/**
	 * Test that orphaned self-replies are skipped.
	 */
	public function test_import_orphaned_self_replies_are_skipped() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					// Self-reply without a parent in the import (orphaned).
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/2/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:35:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/2',
							'type'      => 'Note',
							'content'   => '<p>Reply to missing parent</p>',
							'published' => '2024-01-15T10:35:00Z',
							'inReplyTo' => 'https://mastodon.social/users/example/statuses/1',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		ob_start();
		$result = Mastodon::import_posts();
		$output = ob_get_clean();

		$this->assertTrue( $result );

		// Should not import any posts (the self-reply's parent is missing).
		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 0, $posts, 'Should not import any posts' );

		// Should report the skipped orphan.
		$this->assertStringContainsString( 'Skipped comments', $output );
		$this->assertStringContainsString( 'statuses/2', $output );
	}

	/**
	 * Test self-reply to an external reply (becomes comment on that post).
	 */
	public function test_self_reply_to_external_reply() {
		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					// External reply (imported as post).
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:00:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Reply to external post</p>',
							'published' => '2024-01-15T10:00:00Z',
							'inReplyTo' => 'https://other.server/users/someone/statuses/999',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
					// Self-reply to the external reply (becomes comment).
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/2/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:05:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/2',
							'type'      => 'Note',
							'content'   => '<p>Adding more context</p>',
							'published' => '2024-01-15T10:05:00Z',
							'inReplyTo' => 'https://mastodon.social/users/example/statuses/1',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		ob_start();
		$result = Mastodon::import_posts();
		ob_get_clean();

		$this->assertTrue( $result );

		// External reply should be imported as post.
		$posts = get_posts(
			array(
				'author'      => $this->user_id,
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$this->assertCount( 1, $posts, 'External reply should be imported as post' );

		// Self-reply to external reply should be a comment on that post.
		$comments = get_comments( array( 'post_id' => $posts[0]->ID ) );
		$this->assertCount( 1, $comments, 'Self-reply should become comment on external reply post' );
		$this->assertStringContainsString( 'Adding more context', $comments[0]->comment_content );
	}

	/**
	 * Test that the filter hook is called.
	 */
	public function test_import_posts_calls_filter_hook() {
		$filter_called = false;

		$filter_callback = function ( $data, $post ) use ( &$filter_called ) {
			$filter_called = true;
			$this->assertIsArray( $data, 'Filter should receive array as first parameter' );
			$this->assertIsArray( $post, 'Filter should receive array as second parameter' );
			$this->assertArrayHasKey( 'post_content', $data, 'Data should have post_content key' );
			$this->assertArrayHasKey( 'type', $post, 'Post should have type key' );
			return $data;
		};

		add_filter( 'activitypub_import_mastodon_post_data', $filter_callback, 10, 2 );

		$outbox_json = wp_json_encode(
			array(
				'orderedItems' => array(
					array(
						'id'        => 'https://mastodon.social/users/example/statuses/1/activity',
						'type'      => 'Create',
						'actor'     => 'https://mastodon.social/users/example',
						'published' => '2024-01-15T10:30:00Z',
						'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
						'object'    => array(
							'id'        => 'https://mastodon.social/users/example/statuses/1',
							'type'      => 'Note',
							'content'   => '<p>Test post</p>',
							'published' => '2024-01-15T10:30:00Z',
							'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
							'tag'       => array(),
						),
					),
				),
			)
		);

		$outbox = json_decode( $outbox_json, true );

		$reflection = new ReflectionClass( Mastodon::class );

		$outbox_property = $reflection->getProperty( 'outbox' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$outbox_property->setAccessible( true );
		}
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$author_property->setAccessible( true );
		}
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$fetch_attachments_property->setAccessible( true );
		}
		$fetch_attachments_property->setValue( null, false );

		ob_start();
		$result = Mastodon::import_posts();
		ob_get_clean(); // Suppress output.

		$this->assertTrue( $result );
		$this->assertTrue( $filter_called, 'activitypub_import_mastodon_post_data filter should be called' );

		remove_filter( 'activitypub_import_mastodon_post_data', $filter_callback );
	}

	/**
	 * Test maybe_unwrap_archive() unwraps nested folder structure.
	 */
	public function test_maybe_unwrap_archive_with_nested_folder() {
		\WP_Filesystem();
		global $wp_filesystem;

		// Create temp directory structure: archive/nested-folder/outbox.json.
		$temp_dir   = \get_temp_dir() . 'activitypub-test-' . \uniqid();
		$nested_dir = $temp_dir . '/nested-folder';

		\wp_mkdir_p( $nested_dir );
		$wp_filesystem->put_contents( $nested_dir . '/outbox.json', '{}' );

		// Set self::$archive via Reflection.
		$reflection = new ReflectionClass( Mastodon::class );

		$archive_property = $reflection->getProperty( 'archive' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$archive_property->setAccessible( true );
		}
		$archive_property->setValue( null, $temp_dir );

		// Call private method via Reflection.
		$method = $reflection->getMethod( 'maybe_unwrap_archive' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$method->invoke( null );

		// Assert archive path was updated to nested folder.
		$this->assertSame( $nested_dir, $archive_property->getValue() );

		// Cleanup.
		$wp_filesystem->delete( $temp_dir, true );
	}
}
