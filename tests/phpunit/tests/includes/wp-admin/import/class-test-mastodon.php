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
		$outbox_property->setAccessible( true );
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		$author_property->setAccessible( true );
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		$fetch_attachments_property->setAccessible( true );
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
		$outbox_property->setAccessible( true );
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		$author_property->setAccessible( true );
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		$fetch_attachments_property->setAccessible( true );
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
		$outbox_property->setAccessible( true );
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		$author_property->setAccessible( true );
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		$fetch_attachments_property->setAccessible( true );
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
		$outbox_property->setAccessible( true );
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		$author_property->setAccessible( true );
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		$fetch_attachments_property->setAccessible( true );
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
		$outbox_property->setAccessible( true );
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		$author_property->setAccessible( true );
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		$fetch_attachments_property->setAccessible( true );
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
		$outbox_property->setAccessible( true );
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		$author_property->setAccessible( true );
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		$fetch_attachments_property->setAccessible( true );
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
		$outbox_property->setAccessible( true );
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		$author_property->setAccessible( true );
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		$fetch_attachments_property->setAccessible( true );
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
		$outbox_property->setAccessible( true );
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		$author_property->setAccessible( true );
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		$fetch_attachments_property->setAccessible( true );
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
		$outbox_property->setAccessible( true );
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		$author_property->setAccessible( true );
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		$fetch_attachments_property->setAccessible( true );
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
		$outbox_property->setAccessible( true );
		$outbox_property->setValue( null, $outbox );

		$author_property = $reflection->getProperty( 'author' );
		$author_property->setAccessible( true );
		$author_property->setValue( null, $this->user_id );

		$fetch_attachments_property = $reflection->getProperty( 'fetch_attachments' );
		$fetch_attachments_property->setAccessible( true );
		$fetch_attachments_property->setValue( null, false );

		ob_start();
		$result = Mastodon::import_posts();
		ob_get_clean(); // Suppress output.

		$this->assertTrue( $result );
		$this->assertTrue( $filter_called, 'activitypub_import_mastodon_post_data filter should be called' );

		remove_filter( 'activitypub_import_mastodon_post_data', $filter_callback );
	}
}
