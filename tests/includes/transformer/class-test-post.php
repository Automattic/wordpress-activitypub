<?php
/**
 * Test file for Post transformer.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Activity\Base_Object;
use Activitypub\Transformer\Post;

/**
 * Test class for Post Transformer.
 *
 * @coversDefaultClass \Activitypub\Transformer\Post
 */
class Test_Post extends \WP_UnitTestCase {
	/**
	 * Reflection method for testing protected method.
	 *
	 * @var \ReflectionMethod
	 */
	private $reflection_method;

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'activitypub_object_type', 'wordpress-post-format' );

		// Set up reflection method.
		$reflection              = new \ReflectionClass( Post::class );
		$this->reflection_method = $reflection->getMethod( 'get_type' );
		$this->reflection_method->setAccessible( true );
	}

	/**
	 * Tear down the test case.
	 */
	public function tear_down() {
		// Reset options after each test.
		delete_option( 'activitypub_object_type' );

		parent::tear_down();
	}

	/**
	 * Test that the get_type method returns the configured type when the option is set.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_returns_configured_type_when_option_set() {
		update_option( 'activitypub_object_type', 'Article' );

		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Test content that is longer than the note length limit',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Article', $type );
	}

	/**
	 * Test get_type method with various scenarios.
	 *
	 * @dataProvider get_type_provider
	 * @covers ::get_type
	 *
	 * @param array  $post_data      The post data to create.
	 * @param string $post_format    The post format to set (or null).
	 * @param string $expected_type  The expected ActivityPub type.
	 * @param string $description    Description of the test case.
	 */
	public function test_get_type( $post_data, $post_format, $expected_type, $description ) {
		$post_id = $this->factory->post->create( $post_data );

		if ( $post_format ) {
			set_post_format( $post_id, $post_format );
		}

		$post = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( $expected_type, $type, $description );
	}

	/**
	 * Data provider for get_type tests.
	 *
	 * @return array Test cases with post data, post format, expected type, and description.
	 */
	public function get_type_provider() {
		$long_content = str_repeat( 'Long content. ', 100 );

		return array(
			'short_content'        => array(
				array(
					'post_title'   => 'Test Post',
					'post_content' => 'Short content',
				),
				null,
				'Note',
				'Should return Note for short content',
			),
			'no_title'             => array(
				array(
					'post_title'   => '',
					'post_content' => $long_content,
				),
				null,
				'Note',
				'Should return Note for posts without title',
			),
			'standard_post_format' => array(
				array(
					'post_title'   => 'Test Post',
					'post_content' => $long_content,
					'post_type'    => 'post',
				),
				'standard',
				'Article',
				'Should return Article for standard post format',
			),
			'page_post_type'       => array(
				array(
					'post_title'   => 'Test Page',
					'post_content' => $long_content,
					'post_type'    => 'page',
				),
				null,
				'Page',
				'Should return Page for page post type',
			),
			'aside_post_format'    => array(
				array(
					'post_title'   => 'Test Post',
					'post_content' => $long_content,
					'post_type'    => 'post',
				),
				'aside',
				'Note',
				'Should return Note for non-standard post format',
			),
			'default_post_format'  => array(
				array(
					'post_title'   => 'Test Post',
					'post_content' => $long_content,
					'post_type'    => 'post',
				),
				null,
				'Article',
				'Should return Article for default post format',
			),
		);
	}

	/**
	 * Test that the get_type method returns note for post type without title support.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_respects_post_type_title_support() {
		// Create custom post type without title support.
		register_post_type(
			'no_title_type',
			array(
				'public'   => true,
				'supports' => array( 'editor' ), // Explicitly exclude 'title'.
			)
		);

		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_type'    => 'no_title_type',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Note', $type );

		// Clean up.
		unregister_post_type( 'no_title_type' );
	}

	/**
	 * Test that the get_type method returns article for custom post type with post format support.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type_respects_post_format_support() {
		// Create custom post type without title support.
		register_post_type(
			'no_title_type',
			array(
				'public'   => true,
				'supports' => array( 'editor', 'title', 'post-formats' ), // Needs to include 'title'.
			)
		);

		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_type'    => 'no_title_type',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$type        = $this->reflection_method->invoke( $transformer );

		$this->assertSame( 'Article', $type );

		// Clean up.
		unregister_post_type( 'no_title_type' );
	}

	/**
	 * Test the to_array method.
	 *
	 * @covers ::to_object
	 */
	public function test_to_object() {
		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'test',
			)
		);

		$permalink = \get_permalink( $post );

		$activitypub_post = Post::transform( get_post( $post ) )->to_object();

		$this->assertEquals( $permalink, $activitypub_post->get_id() );

		\wp_trash_post( $post );

		$activitypub_post = Post::transform( get_post( $post ) )->to_object();

		$this->assertEquals( $permalink, $activitypub_post->get_id() );

		$cached = \get_post_meta( $post, '_activitypub_canonical_url', true );

		$this->assertEquals( $cached, $activitypub_post->get_id() );
	}

	/**
	 * Test content visibility.
	 *
	 * @covers ::to_object
	 */
	public function test_content_visibility() {
		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'test content visibility',
			)
		);

		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );

		$this->assertFalse( \Activitypub\is_post_disabled( $post_id ) );
		$object = Post::transform( get_post( $post_id ) )->to_object();
		$this->assertContains( 'https://www.w3.org/ns/activitystreams#Public', $object->get_to() );

		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC );

		$this->assertFalse( \Activitypub\is_post_disabled( $post_id ) );
		$object = Post::transform( get_post( $post_id ) )->to_object();
		$this->assertContains( 'https://www.w3.org/ns/activitystreams#Public', $object->get_cc() );

		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );

		$this->assertTrue( \Activitypub\is_post_disabled( $post_id ) );
		$object = Post::transform( get_post( $post_id ) )->to_object();
		$this->assertEmpty( $object->get_to() );
		$this->assertEmpty( $object->get_cc() );
	}

	/**
	 * Test different variations of Attachment parsing.
	 *
	 * @covers ::to_object
	 */
	public function test_block_attachments_with_fallback() {
		$attachment_id  = $this->create_upload_object( dirname( __DIR__, 2 ) . '/assets/test.jpg' );
		$attachment_src = \wp_get_attachment_image_src( $attachment_id );

		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => sprintf(
					'<!-- wp:image {"id": %1$d,"sizeSlug":"large"} --><figure class="wp-block-image"><img src="%2$s" alt="" class="wp-image-%1$d"/></figure><!-- /wp:image -->',
					$attachment_id,
					$attachment_src[0]
				),
				'post_status'  => 'publish',
			)
		);

		$object = Post::transform( get_post( $post_id ) )->to_object();

		$this->assertEquals(
			array(
				array(
					'type'      => 'Image',
					'url'       => $attachment_src[0],
					'mediaType' => 'image/jpeg',
				),
			),
			$object->get_attachment()
		);

		$post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => sprintf(
					'<p>this is a photo</p><p><img src="%2$s" alt="" class="wp-image-%1$d"/></p>',
					$attachment_id,
					$attachment_src[0]
				),
				'post_status'  => 'publish',
			)
		);

		$object = Post::transform( get_post( $post_id ) )->to_object();

		$this->assertEquals(
			array(
				array(
					'type'      => 'Image',
					'url'       => $attachment_src[0],
					'mediaType' => 'image/jpeg',
				),
			),
			$object->get_attachment()
		);

		\wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Test get_media_from_blocks adds alt text to existing images.
	 *
	 * @covers ::get_media_from_blocks
	 */
	public function test_get_media_from_blocks_adds_alt_text_to_existing_images() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:image {"id":123} --><figure class="wp-block-image"><img src="test.jpg" alt="Test alt text" /></figure><!-- /wp:image -->',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$media       = array(
			'image' => array(
				array(
					'id'  => 123,
					'alt' => '',
				),
			),
			'audio' => array(),
			'video' => array(),
		);

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_media_from_blocks' );
		$method->setAccessible( true );

		$blocks = parse_blocks( $post->post_content );
		$result = $method->invoke( $transformer, $blocks, $media );

		$this->assertSame( 'Test alt text', $result['image'][0]['alt'] );
		$this->assertSame( 123, $result['image'][0]['id'] );
	}

	/**
	 * Test get_attachments with zero max_media_attachments.
	 *
	 * @covers ::get_attachment
	 */
	public function test_get_attachments_with_zero_max_media_attachments() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:image {"id":123} --><figure class="wp-block-image"><img src="test.jpg" alt="Test alt text" /></figure><!-- /wp:image -->',
			)
		);

		\update_post_meta( $post_id, 'activitypub_max_image_attachments', 0 );
		$post = get_post( $post_id );

		$transformer = new Post( $post );

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_attachment' );
		$method->setAccessible( true );

		$result = $method->invoke( $transformer );

		$this->assertEmpty( $result );
		$this->assertFalse( (bool) \did_filter( 'activitypub_attachment_ids' ) );

		\delete_post_meta( $post_id, 'activitypub_max_image_attachments' );

		$result = $method->invoke( $transformer );
		$this->assertTrue( (bool) \did_filter( 'activitypub_attachment_ids' ) );

		\wp_delete_post( $post_id );
	}

	/**
	 * Test get_media_from_blocks adds new image when none exist.
	 *
	 * @covers ::get_media_from_blocks
	 */
	public function test_get_media_from_blocks_adds_new_image() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:image {"id":123} --><figure class="wp-block-image"><img src="test.jpg" alt="Test alt text" /></figure><!-- /wp:image -->',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$media       = array(
			'image' => array(),
			'audio' => array(),
			'video' => array(),
		);

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_media_from_blocks' );
		$method->setAccessible( true );

		$blocks = parse_blocks( $post->post_content );
		$result = $method->invoke( $transformer, $blocks, $media );

		$this->assertCount( 1, $result['image'] );
		$this->assertSame( 123, $result['image'][0]['id'] );
		$this->assertSame( 'Test alt text', $result['image'][0]['alt'] );
	}

	/**
	 * Test get_media_from_blocks handles multiple blocks correctly.
	 *
	 * @covers ::get_media_from_blocks
	 */
	public function test_get_media_from_blocks_handles_multiple_blocks() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:image {"id":123} --><figure class="wp-block-image"><img src="test1.jpg" alt="Test alt 1" /></figure><!-- /wp:image --><!-- wp:image {"id":456} --><figure class="wp-block-image"><img src="test2.jpg" alt="Test alt 2" /></figure><!-- /wp:image -->',
			)
		);
		$post    = get_post( $post_id );

		$transformer = new Post( $post );
		$media       = array(
			'image' => array(),
			'audio' => array(),
			'video' => array(),
		);

		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_media_from_blocks' );
		$method->setAccessible( true );

		$blocks = parse_blocks( $post->post_content );
		$result = $method->invoke( $transformer, $blocks, $media );

		$this->assertCount( 2, $result['image'] );
		$this->assertSame( 123, $result['image'][0]['id'] );
		$this->assertSame( 'Test alt 1', $result['image'][0]['alt'] );
		$this->assertSame( 456, $result['image'][1]['id'] );
		$this->assertSame( 'Test alt 2', $result['image'][1]['alt'] );
	}

	/**
	 * Test get_icon method.
	 *
	 * @covers ::get_icon
	 */
	public function test_get_icon() {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Test content',
			)
		);
		$post    = get_post( $post_id );

		// Create test image.
		$attachment_id = $this->create_upload_object( dirname( __DIR__, 2 ) . '/assets/test.jpg' );

		// Set up reflection method.
		$reflection = new \ReflectionClass( Post::class );
		$method     = $reflection->getMethod( 'get_icon' );
		$method->setAccessible( true );

		// Test with featured image.
		set_post_thumbnail( $post_id, $attachment_id );

		$transformer = new Post( $post );
		$icon        = $method->invoke( $transformer );

		$this->assertIsArray( $icon );
		$this->assertEquals( 'Image', $icon['type'] );
		$this->assertArrayHasKey( 'url', $icon );
		$this->assertArrayHasKey( 'mediaType', $icon );
		$this->assertEquals( get_post_mime_type( $attachment_id ), $icon['mediaType'] );

		// Test with site icon.
		delete_post_thumbnail( $post_id );
		update_option( 'site_icon', $attachment_id );

		$icon = $method->invoke( $transformer );

		$this->assertIsArray( $icon );
		$this->assertEquals( 'Image', $icon['type'] );
		$this->assertArrayHasKey( 'url', $icon );
		$this->assertArrayHasKey( 'mediaType', $icon );
		$this->assertEquals( get_post_mime_type( $attachment_id ), $icon['mediaType'] );

		// Test with alt text.
		$alt_text = 'Test Alt Text';
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

		$icon = $method->invoke( $transformer );

		$this->assertIsArray( $icon );
		$this->assertEquals( 'Image', $icon['type'] );
		$this->assertArrayHasKey( 'name', $icon );
		$this->assertEquals( $alt_text, $icon['name'] );

		// Test without any images.
		delete_post_thumbnail( $post_id );
		delete_option( 'site_icon' );
		delete_post_meta( $attachment_id, '_wp_attachment_image_alt' );

		$icon = $method->invoke( $transformer );
		$this->assertNull( $icon );

		// Test with invalid image.
		set_post_thumbnail( $post_id, 99999 );
		$icon = $method->invoke( $transformer );
		$this->assertNull( $icon );

		// Cleanup.
		wp_delete_post( $post_id, true );
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Saves an attachment.
	 *
	 * @param string $file      The file name to create attachment object for.
	 * @param int    $parent_id ID of the post to attach the file to.
	 * @return int|\WP_Error The attachment ID on success. The value 0 or WP_Error on failure.
	 */
	public function create_upload_object( $file, $parent_id = 0 ) {
		if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
			require ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}

		$dest = dirname( $file ) . DIRECTORY_SEPARATOR . 'test-temp.jpg';
		$fs   = new \WP_Filesystem_Direct( array() );
		$fs->copy( $file, $dest );

		$file = $dest;

		$file_array = array(
			'name'     => wp_basename( $file ),
			'tmp_name' => $file,
		);

		$upload = wp_handle_sideload( $file_array, array( 'test_form' => false ) );

		$type = '';
		if ( ! empty( $upload['type'] ) ) {
			$type = $upload['type'];
		} else {
			$mime = wp_check_filetype( $upload['file'] );
			if ( $mime ) {
				$type = $mime['type'];
			}
		}

		$attachment = array(
			'post_title'     => wp_basename( $upload['file'] ),
			'post_content'   => '',
			'post_type'      => 'attachment',
			'post_parent'    => $parent_id,
			'post_mime_type' => $type,
			'guid'           => $upload['url'],
		);

		// Save the data.
		$id = wp_insert_attachment( $attachment, $upload['file'], $parent_id );
		wp_update_attachment_metadata( $id, @wp_generate_attachment_metadata( $id, $upload['file'] ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return $id;
	}

	/**
	 * Test preview property generation.
	 *
	 * @covers ::get_preview
	 */
	public function test_preview_property() {
		// Create a test post of type "Article".
		$post = $this->factory->post->create_and_get(
			array(
				'post_title'   => 'Test Article',
				'post_content' => str_repeat( 'Long content. ', 100 ),
				'post_status'  => 'publish',
			)
		);

		$transformer = new Post( $post );
		$preview     = $transformer->get_preview();

		// Check if the preview for an Article is correctly generated.
		$this->assertIsArray( $preview );
		$this->assertEquals( 'Note', $preview['type'] );
		$this->assertArrayHasKey( 'content', $preview );
		$this->assertNotEmpty( $preview['content'] );

		// Create a test post of type "Note" (short content).
		$note_post = $this->factory->post->create_and_get(
			array(
				'post_title'   => '',
				'post_content' => 'Short note content',
				'post_status'  => 'publish',
			)
		);

		$note_transformer = new Post( $note_post );
		$note_preview     = $note_transformer->get_preview();

		// Check if the preview for a Note is null.
		$this->assertNull( $note_preview );
	}

	/**
	 * Test reply link generation.
	 *
	 * Pleroma prepends `acct:` to the webfinger identifier, which we'd want to normalize.
	 *
	 * @covers ::generate_reply_link
	 */
	public function test_generate_reply_link() {
		\add_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'filter_pleroma_object' ), 10, 2 );

		$transformer = new Post( self::factory()->post->create_and_get() );
		$this->setExpectedDeprecated( 'Activitypub\Transformer\Post::generate_reply_link' );
		$reply_link = $transformer->generate_reply_link( '', array( 'attrs' => array( 'url' => 'https://devs.live/notice/AQ8N0Xl57y8bUQAb6e' ) ) );

		$this->assertSame( '<p class="ap-reply-mention"><a rel="mention ugc" href="https://devs.live/notice/AQ8N0Xl57y8bUQAb6e" title="tester@devs.live">@tester</a></p>', $reply_link );

		\remove_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'filter_pleroma_object' ) );
	}

	/**
	 * Filter pleroma object.
	 *
	 * @param array|string|null $response The response.
	 * @param array|string|null $url      The Object URL.
	 * @return string[]
	 */
	public function filter_pleroma_object( $response, $url ) {
		if ( 'https://devs.live/notice/AQ8N0Xl57y8bUQAb6e' === $url ) {
			$response = array(
				'type'         => 'Note',
				'attributedTo' => 'https://devs.live/users/tester',
				'content'      => 'Cake day it is',
			);
		}
		if ( 'https://devs.live/users/tester' === $url ) {
			$response = array(
				'id'                => 'https://devs.live/users/tester',
				'type'              => 'Person',
				'preferredUsername' => 'tester',
				'url'               => 'https://devs.live/users/tester',
				'webfinger'         => 'acct:tester@devs.live',
			);
		}

		return $response;
	}

	/**
	 * Test get_content method.
	 *
	 * @covers ::get_content
	 */
	public function test_get_content() {
		$follow_me = '<!-- wp:activitypub/follow-me -->
<div class="wp-block-activitypub-follow-me"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Follow</a></div>
<!-- /wp:button --></div>
<!-- /wp:activitypub/follow-me -->';

		$followers = '<!-- wp:activitypub/followers -->
<div class="wp-block-activitypub-followers"><!-- wp:heading {"level":3,"placeholder":"Fediverse Followers"} -->
<h3 class="wp-block-heading">Fediverse Followers</h3>
<!-- /wp:heading --></div>
<!-- /wp:activitypub/followers -->';

		$reactions = '<!-- wp:activitypub/reactions -->
<div class="wp-block-activitypub-reactions"><!-- wp:heading {"level":3,"placeholder":"Fediverse Reactions"} -->
<h3 class="wp-block-heading">Fediverse Reactions</h3>
<!-- /wp:heading --></div>
<!-- /wp:activitypub/reactions -->';

		$post = self::factory()->post->create_and_get(
			array(
				'post_content' => implode( PHP_EOL, array( $follow_me, $followers, $reactions ) ),
				'post_title'   => '',
			)
		);

		$object      = new Base_Object();
		$get_content = new \ReflectionMethod( Post::class, 'transform_object_properties' );

		$get_content->setAccessible( true );

		$object = $get_content->invoke( new Post( $post ), $object );

		$this->assertEmpty( $object->get_content() );
	}

	/**
	 * Test that reply blocks get transformed into mention links when they are the first block in a post.
	 *
	 * @covers ::to_object
	 * @covers ::get_content
	 */
	public function test_reply_block_transforms_to_mention_link_when_first_block() {
		// Set up a filter to intercept HTTP requests for remote objects.
		$filter_remote_object = function ( $pre, $url ) {
			if ( 'https://example.com/posts/123' === $url ) {
				return array(
					'attributedTo' => 'https://example.com/users/author',
				);
			} elseif ( 'https://example.com/users/author' === $url ) {
				return array(
					'preferredUsername' => 'author',
					'url'               => 'https://example.com/users/author',
				);
			}
			return $pre;
		};

		add_filter( 'activitypub_pre_http_get_remote_object', $filter_remote_object, 10, 2 );

		// Create a post with a reply block as the first block.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Reply Post',
				'post_content' => '<!-- wp:activitypub/reply {"url":"https://example.com/posts/123"} /-->' . PHP_EOL .
									'<!-- wp:paragraph --><p>This is a test post with a reply block first.</p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
			)
		);

		// Transform the post to an ActivityPub object.
		$post   = get_post( $post_id );
		$object = Post::transform( $post )->to_object();

		// Assert that the reply block was transformed into a mention link.
		$this->assertStringContainsString( '<p class="ap-reply-mention"><a rel="mention ugc" href="https://example.com/posts/123" title="@author@example.com">@author</a></p>', $object->get_content() );

		// Clean up.
		remove_filter( 'activitypub_pre_http_get_remote_object', $filter_remote_object );
	}

	/**
	 * Test that reply blocks do not get transformed into mention links when they are not the first block in a post.
	 *
	 * @covers ::to_object
	 * @covers ::get_content
	 */
	public function test_reply_block_not_transformed_when_not_first_block() {
		// Create a post with a reply block that is not the first block.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Reply Post',
				'post_content' => '<!-- wp:paragraph --><p>This is a test post with a reply block that is not first.</p><!-- /wp:paragraph -->' . PHP_EOL .
									'<!-- wp:activitypub/reply {"url":"https://example.com/posts/123"} /-->',
				'post_status'  => 'publish',
			)
		);

		// Transform the post to an ActivityPub object.
		$post   = get_post( $post_id );
		$object = Post::transform( $post )->to_object();

		// Get the content from the object.
		$content = $object->get_content();

		// Assert that the reply block was not transformed into a mention link.
		$this->assertStringContainsString( '<div class="activitypub-reply-block wp-block-activitypub-reply" aria-label="Reply" data-in-reply-to="https://example.com/posts/123"><p><a title="This post is a response to the referenced content." aria-label="This post is a response to the referenced content." href="https://example.com/posts/123" class="u-in-reply-to" target="_blank">&#8620;example.com/posts/123</a></p></div>', $content );
	}

	/**
	 * Test that when multiple reply blocks exist, only the first one gets transformed to @-mention.
	 *
	 * @covers ::to_object
	 * @covers ::get_content
	 */
	public function test_multiple_reply_blocks_only_first_becomes_mention() {
		// Set up a filter to intercept HTTP requests for remote objects.
		$filter_remote_object = function ( $pre, $url ) {
			if ( 'https://example.com/posts/123' === $url ) {
				return array(
					'attributedTo' => 'https://example.com/users/author1',
				);
			} elseif ( 'https://example.com/users/author1' === $url ) {
				return array(
					'preferredUsername' => 'author1',
					'url'               => 'https://example.com/users/author1',
				);
			} elseif ( 'https://other.site/posts/456' === $url ) {
				return array(
					'attributedTo' => 'https://other.site/users/author2',
				);
			} elseif ( 'https://other.site/users/author2' === $url ) {
				return array(
					'preferredUsername' => 'author2',
					'url'               => 'https://other.site/users/author2',
				);
			}
			return $pre;
		};

		add_filter( 'activitypub_pre_http_get_remote_object', $filter_remote_object, 10, 2 );

		// Create a post with two reply blocks - first one should become @-mention, second should remain as link.
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Test Multiple Reply Post',
				'post_content' => '<!-- wp:activitypub/reply {"url":"https://example.com/posts/123"} /-->' . PHP_EOL .
									'<!-- wp:paragraph --><p>This is a response to the first post, but also references another post.</p><!-- /wp:paragraph -->' . PHP_EOL .
									'<!-- wp:activitypub/reply {"url":"https://other.site/posts/456"} /-->',
				'post_status'  => 'publish',
			)
		);

		// Transform the post to an ActivityPub object.
		$post   = get_post( $post_id );
		$object = Post::transform( $post )->to_object();

		// Get the content from the object.
		$content = $object->get_content();

		// Assert that the first reply block was transformed into a mention link.
		$this->assertStringContainsString( '<p class="ap-reply-mention"><a rel="mention ugc" href="https://example.com/posts/123" title="@author1@example.com">@author1</a></p>', $content );

		// Assert that the second reply block was NOT transformed into a mention link (should remain as regular reply block).
		$this->assertStringContainsString( '<div class="activitypub-reply-block wp-block-activitypub-reply" aria-label="Reply" data-in-reply-to="https://other.site/posts/456"><p><a title="This post is a response to the referenced content." aria-label="This post is a response to the referenced content." href="https://other.site/posts/456" class="u-in-reply-to" target="_blank">&#8620;other.site/posts/456</a></p></div>', $content );

		// Clean up.
		remove_filter( 'activitypub_pre_http_get_remote_object', $filter_remote_object );
	}

	/*
	 * =========================
	 * get_interaction_policy()
	 * =========================
	 */

	/**
	 * Helper to create a published post with a fresh author.
	 *
	 * @return \WP_Post
	 */
	private function create_test_post() {
		$user_id = self::factory()->user->create();
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Interaction Policy Test',
				'post_content' => 'Content',
				'post_status'  => 'publish',
				'post_author'  => $user_id,
			)
		);
		return get_post( $post_id );
	}

	/**
	 * Test policy generation for the 'anyone' permission.
	 *
	 * @covers ::get_interaction_policy
	 */
	public function test_get_interaction_policy_anyone() {
		$post = $this->create_test_post();
		\update_post_meta( $post->ID, 'activitypub_interaction_policy_quote', ACTIVITYPUB_INTERACTION_POLICY_ANYONE );

		$stored = \get_post_meta( $post->ID, 'activitypub_interaction_policy_quote', true );
		$this->assertEmpty( $stored, 'Meta value not stored as expected.' );

		$transformer = new Post( $post );
		$policy      = $transformer->get_interaction_policy();

		$this->assertIsArray( $policy, 'Policy should be array.' );
		$this->assertArrayHasKey( 'canQuote', $policy );
		$this->assertSame(
			array(
				'automaticApproval' => 'https://www.w3.org/ns/activitystreams#Public',
				'always'            => 'https://www.w3.org/ns/activitystreams#Public',
			),
			$policy['canQuote'],
			"'anyone' permission should map to public policy."
		);
	}

	/**
	 * Test fallback to 'anyone' when no quote permission meta is set.
	 *
	 * @covers ::get_interaction_policy
	 */
	public function test_get_interaction_policy_no_meta_fallback() {
		$post        = $this->create_test_post();
		$transformer = new Post( $post );
		$policy      = $transformer->get_interaction_policy();

		$this->assertIsArray( $policy, 'Should fall back to anyone policy when no meta set.' );
		$this->assertArrayHasKey( 'canQuote', $policy );
		$this->assertSame(
			array(
				'automaticApproval' => 'https://www.w3.org/ns/activitystreams#Public',
				'always'            => 'https://www.w3.org/ns/activitystreams#Public',
			),
			$policy['canQuote'],
			'No meta should fall back to anyone (public) policy.'
		);
	}

	/**
	 * Test policy generation for the 'followers' permission.
	 *
	 * @covers ::get_interaction_policy
	 */
	public function test_get_interaction_policy_followers() {
		$post = $this->create_test_post();
		update_post_meta( $post->ID, 'activitypub_interaction_policy_quote', ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS );

		$transformer = new Post( $post );
		$policy      = $transformer->get_interaction_policy();

		$this->assertIsArray( $policy );
		$this->assertArrayHasKey( 'canQuote', $policy );
		$this->assertArrayHasKey( 'automaticApproval', $policy['canQuote'] );
		$this->assertStringContainsString( '/followers', $policy['canQuote']['automaticApproval'], 'Followers permission should point to followers collection.' );
	}

	/**
	 * Test policy generation for the 'me' permission across actor modes.
	 *
	 * @covers ::get_interaction_policy
	 */
	public function test_get_interaction_policy_me_actor_modes() {
		$post = $this->create_test_post();
		update_post_meta( $post->ID, 'activitypub_interaction_policy_quote', ACTIVITYPUB_INTERACTION_POLICY_ME );

		$actor_modes = array(
			ACTIVITYPUB_ACTOR_MODE,
			ACTIVITYPUB_BLOG_MODE,
			ACTIVITYPUB_ACTOR_AND_BLOG_MODE,
		);

		foreach ( $actor_modes as $mode ) {
			update_option( 'activitypub_actor_mode', $mode );
			$transformer = new Post( get_post( $post->ID ) ); // fresh instance.
			$policy      = $transformer->get_interaction_policy();

			$this->assertIsArray( $policy, 'Policy should be array for mode ' . $mode );
			$this->assertArrayHasKey( 'canQuote', $policy );
			$this->assertArrayHasKey( 'automaticApproval', $policy['canQuote'] );

			$auto = $policy['canQuote']['automaticApproval'];
			if ( ACTIVITYPUB_ACTOR_AND_BLOG_MODE === $mode ) {
				$this->assertIsArray( $auto, 'Actor+Blog mode should return an array of IDs.' );
				$this->assertCount( 2, $auto, 'Actor+Blog mode should supply two IDs.' );
			} else {
				$this->assertIsString( $auto, 'Single mode should return a single ID string.' );
			}
		}

		// Cleanup.
		delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Ensure invalid permission values fall back to 'anyone' policy.
	 *
	 * @covers ::get_interaction_policy
	 */
	public function test_get_interaction_policy_invalid_value_returns_null() {
		$post = $this->create_test_post();
		\update_post_meta( $post->ID, 'activitypub_interaction_policy_quote', 'not-a-valid-permission' );

		$transformer = new Post( $post );
		$policy      = $transformer->get_interaction_policy();

		$this->assertIsArray( $policy, 'Invalid permission should fall back to anyone policy.' );
		$this->assertArrayHasKey( 'canQuote', $policy );
		$this->assertSame(
			array(
				'automaticApproval' => 'https://www.w3.org/ns/activitystreams#Public',
				'always'            => 'https://www.w3.org/ns/activitystreams#Public',
			),
			$policy['canQuote'],
			'Invalid permission should fall back to anyone (public) policy.'
		);
	}
}
