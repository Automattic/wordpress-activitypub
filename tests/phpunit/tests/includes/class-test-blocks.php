<?php
/**
 * Test file for Blocks class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Blocks;
use Activitypub\Collection\Extra_Fields;
use Activitypub\Collection\Interactions;

use function Activitypub\object_to_uri;

/**
 * Test class for Blocks.
 *
 * @coversDefaultClass \Activitypub\Blocks
 */
class Test_Blocks extends \WP_UnitTestCase {

	/**
	 * User ID for Extra Fields block tests.
	 *
	 * @var int
	 */
	private static $extra_fields_user_id;

	/**
	 * Set up before class.
	 *
	 * @param \WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		// Create test user for Extra Fields block tests.
		self::$extra_fields_user_id = $factory->user->create(
			array(
				'user_login' => 'extra_fields_user',
				'user_email' => 'extrafields@example.com',
			)
		);

		// Create some extra fields for the user.
		$factory->post->create(
			array(
				'post_type'    => Extra_Fields::USER_POST_TYPE,
				'post_title'   => 'Website',
				'post_content' => '<!-- wp:paragraph --><p><a href="https://example.com" rel="me">example.com</a></p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
				'post_author'  => self::$extra_fields_user_id,
				'menu_order'   => 10,
			)
		);

		$factory->post->create(
			array(
				'post_type'    => Extra_Fields::USER_POST_TYPE,
				'post_title'   => 'Location',
				'post_content' => '<!-- wp:paragraph --><p>San Francisco, CA</p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
				'post_author'  => self::$extra_fields_user_id,
				'menu_order'   => 20,
			)
		);

		$factory->post->create(
			array(
				'post_type'    => Extra_Fields::USER_POST_TYPE,
				'post_title'   => 'Pronouns',
				'post_content' => '<!-- wp:paragraph --><p>they/them</p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
				'post_author'  => self::$extra_fields_user_id,
				'menu_order'   => 30,
			)
		);

		// Create extra fields for blog.
		$factory->post->create(
			array(
				'post_type'    => Extra_Fields::BLOG_POST_TYPE,
				'post_title'   => 'Blog Website',
				'post_content' => '<!-- wp:paragraph --><p><a href="https://blog.example.com" rel="me">blog.example.com</a></p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
				'post_author'  => self::$extra_fields_user_id,
				'menu_order'   => 10,
			)
		);
	}

	/**
	 * Test register_post_meta.
	 *
	 * @covers \Activitypub\Post_Types::register_activitypub_post_meta
	 */
	public function test_register_post_meta() {
		// Empty option should not trigger _doing_it_wrong() notice.
		\update_option( 'activitypub_max_image_attachments', '' );

		\register_post_meta(
			'post',
			'activitypub_max_image_attachments',
			array(
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => \get_option( 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS ),
				'sanitize_callback' => 'absint',
			)
		);

		$this->expectedDeprecated();
		$this->assertSame( ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS, \get_option( 'activitypub_max_image_attachments' ) );
	}

	/**
	 * Test the reply block with a valid URL attribute.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_render_reply_block_with_valid_url() {
		$block_markup = '<!-- wp:activitypub/reply {"url":"https://example.com/post","embedPost":false} /-->';
		$output       = do_blocks( $block_markup );

		$this->assertStringContainsString( 'u-in-reply-to', $output );
		$this->assertStringContainsString( 'https://example.com/post', $output );
		$this->assertStringContainsString( 'example.com/post', $output );
	}

	/**
	 * Test the reply block with a missing URL attribute.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_render_reply_block_with_missing_url() {
		$block_markup = '<!-- wp:activitypub/reply /-->';
		$output       = do_blocks( $block_markup );

		$this->assertEmpty( $output );
	}

	/**
	 * Test the reply block with an empty URL attribute.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_render_reply_block_with_empty_url() {
		$block_markup = '<!-- wp:activitypub/reply {"url":""} /-->';
		$output       = do_blocks( $block_markup );

		$this->assertEmpty( $output );
	}

	/**
	 * Test render_reply_block with Mastodon embed.
	 */
	public function test_render_reply_block_with_mastodon_embed() {
		$url = 'https://mastodon.social/@Gargron/109924476225391570';

		// Mock the ActivityPub object that would be returned by Http::get_remote_object.
		$mock_activity = array(
			'id'           => $url,
			'type'         => 'Note',
			'attributedTo' => 'https://mastodon.social/users/Gargron',
			'content'      => 'Test toot content',
			'published'    => '2023-03-23T12:34:56Z',
			'name'         => 'Test Toot',
			'icon'         => array(
				'url' => 'https://files.mastodon.social/accounts/avatars/000/000/001/original/avatar.jpg',
			),
		);

		$pre_filter = function ( $pre, $url_or_object ) use ( $mock_activity ) {
			$url = object_to_uri( $url_or_object );
			if ( false !== strpos( $url, 'mastodon.social' ) ) {
				return $mock_activity;
			}

			return $pre;
		};

		// Add filter to mock the HTTP response before Http::get_remote_object is called.
		add_filter( 'activitypub_pre_http_get_remote_object', $pre_filter, 10, 2 );

		$block_markup = sprintf(
			'<!-- wp:activitypub/reply {"url":"%s","embedPost":true} /-->',
			$url
		);

		$output = do_blocks( $block_markup );

		// Test the wrapper and microformats.
		$this->assertStringContainsString( 'wp-block-activitypub-reply', $output );
		$this->assertStringContainsString( 'activitypub-embed', $output );
		$this->assertStringContainsString( 'h-cite', $output );

		// Test the embed content.
		$this->assertStringContainsString( 'Test toot content', $output );
		$this->assertStringContainsString( 'Test Toot', $output );
		$this->assertStringContainsString( $url, $output );

		// Test author info.
		$this->assertStringContainsString( 'https://mastodon.social/users/Gargron', $output );
		$this->assertStringContainsString( 'accounts/avatars/000/000/001/original/avatar.jpg', $output );

		// Test microformats classes.
		$this->assertStringContainsString( 'p-author', $output );
		$this->assertStringContainsString( 'h-card', $output );
		$this->assertStringContainsString( 'u-photo', $output );
		$this->assertStringContainsString( 'p-name', $output );
		$this->assertStringContainsString( 'u-url', $output );

		remove_filter( 'activitypub_pre_http_get_remote_object', $pre_filter );
	}

	/**
	 * Test the reply block with a URL that has no available embed.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_render_reply_block_with_no_embed() {
		add_filter( 'pre_oembed_result', '__return_false' );

		$block_markup = '<!-- wp:activitypub/reply {"url":"https://example.com/no-embed","embedPost":false} /-->';
		$output       = do_blocks( $block_markup );

		$this->assertStringNotContainsString( '<blockquote', $output, 'Output should not contain any embedded content.' );
		$this->assertStringContainsString( 'u-in-reply-to', $output, 'Output should contain the reply link.' );
		$this->assertStringContainsString( 'example.com/no-embed', $output, 'Output should contain the formatted URL.' );
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

		$reply_link = Blocks::generate_reply_link( '', array( 'attrs' => array( 'url' => 'https://devs.live/notice/AQ8N0Xl57y8bUQAb6e' ) ) );

		$this->assertSame( '<p class="ap-reply-mention"><a rel="mention ugc" href="https://devs.live/notice/AQ8N0Xl57y8bUQAb6e" title="tester@devs.live">@tester</a></p>', $reply_link );

		\remove_filter( 'activitypub_pre_http_get_remote_object', array( $this, 'filter_pleroma_object' ) );
	}

	/**
	 * Feed renders of the reply block should produce the simple mention link
	 * instead of the embed card, which depends on plugin CSS that feeds don't load.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_feed_renders_reply_block_as_mention_link() {
		$reply_url = 'https://devs.live/notice/AQ8N0Xl57y8bUQAb6e';
		$pre_http  = function ( $response, $url ) use ( $reply_url ) {
			if ( $reply_url === $url ) {
				return array(
					'id'           => $reply_url,
					'type'         => 'Note',
					'attributedTo' => 'https://devs.live/users/tester',
					'content'      => 'Cake day it is',
					'published'    => '2026-01-01T00:00:00Z',
				);
			}
			if ( 'https://devs.live/users/tester' === $url ) {
				return array(
					'id'                => 'https://devs.live/users/tester',
					'type'              => 'Person',
					'preferredUsername' => 'tester',
					'url'               => 'https://devs.live/users/tester',
					'webfinger'         => 'acct:tester@devs.live',
				);
			}
			return $response;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $pre_http, 10, 2 );

		$block_markup = '<!-- wp:activitypub/reply {"url":"' . $reply_url . '","embedPost":true} /-->';

		// Frontend pass: is_feed() is false, so the full embed card is kept.
		$this->go_to( \home_url( '/' ) );
		$this->assertFalse( \is_feed(), 'Precondition: home request must not be a feed.' );
		$front_output = \do_blocks( $block_markup );

		$this->assertStringNotContainsString( 'ap-reply-mention', $front_output, 'Frontend rendering must keep the full embed card.' );
		$this->assertStringContainsString( 'wp-block-activitypub-reply', $front_output, 'Frontend rendering should still emit the embed wrapper.' );

		// Feed pass: is_feed() is true, so the reply block is swapped for the mention link.
		$this->go_to( \home_url( '/?feed=rss2' ) );
		$this->assertTrue( \is_feed(), 'Precondition: feed query.' );
		$feed_output = \do_blocks( $block_markup );

		$this->assertStringContainsString( 'ap-reply-mention', $feed_output, 'Feed rendering should swap the embed for a mention link.' );
		$this->assertStringContainsString( '@tester', $feed_output, 'Feed rendering should include the @username mention.' );
		$this->assertStringNotContainsString( 'wp-block-activitypub-reply', $feed_output, 'Feed rendering should drop the embed card wrapper.' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $pre_http );
	}

	/**
	 * Test filter_import_mastodon_post_data with regular paragraphs.
	 *
	 * @covers ::filter_import_mastodon_post_data
	 */
	public function test_filter_import_mastodon_post_data_with_paragraphs() {
		$data = array(
			'post_content' => '<p>First paragraph</p><p>Second paragraph</p>',
		);

		$post = array(
			'object' => array(
				'inReplyTo' => null,
			),
		);

		$result = Blocks::filter_import_mastodon_post_data( $data, $post );

		$this->assertSame( "<!-- wp:paragraph -->\n<p>First paragraph</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Second paragraph</p>\n<!-- /wp:paragraph -->", $result['post_content'] );
	}

	/**
	 * Test filter_import_mastodon_post_data with a reply post.
	 *
	 * @covers ::filter_import_mastodon_post_data
	 */
	public function test_filter_import_mastodon_post_data_with_reply() {
		$data = array(
			'post_content' => '<p>This is a reply</p>',
		);

		$reply_url = 'https://mastodon.social/@user/123456';
		$post      = array(
			'object' => array(
				'inReplyTo' => $reply_url,
			),
		);

		$result = Blocks::filter_import_mastodon_post_data( $data, $post );

		$this->assertStringContainsString( '<!-- wp:activitypub/reply {"url":"https://mastodon.social/@user/123456","embedPost":true} /-->', $result['post_content'] );
		$this->assertStringContainsString( "<!-- wp:paragraph -->\n<p>This is a reply</p>\n<!-- /wp:paragraph -->", $result['post_content'] );
	}

	/**
	 * Test filter_import_mastodon_post_data without inReplyTo field.
	 *
	 * @covers ::filter_import_mastodon_post_data
	 */
	public function test_filter_import_mastodon_post_data_without_in_reply_to() {
		$data = array(
			'post_content' => '<p>Regular post without reply</p>',
		);

		$post = array(
			'object' => array(
				// No inReplyTo field.
			),
		);

		$result = Blocks::filter_import_mastodon_post_data( $data, $post );

		$this->assertStringNotContainsString( 'wp:activitypub/reply', $result['post_content'], 'Should not add reply block when no inReplyTo' );
		$this->assertStringContainsString( "<!-- wp:paragraph -->\n<p>Regular post without reply</p>\n<!-- /wp:paragraph -->", $result['post_content'] );
	}

	/**
	 * Test filter_import_mastodon_post_data with multiple paragraphs and a reply.
	 *
	 * @covers ::filter_import_mastodon_post_data
	 */
	public function test_filter_import_mastodon_post_data_with_multiple_paragraphs_and_reply() {
		$data = array(
			'post_content' => '<p>First paragraph</p><p>Second paragraph</p><p>Third paragraph</p>',
		);

		$reply_url = 'https://mastodon.social/@alice/789';
		$post      = array(
			'object' => array(
				'inReplyTo' => $reply_url,
			),
		);

		$result = Blocks::filter_import_mastodon_post_data( $data, $post );

		// Should have reply block at the start.
		$this->assertStringStartsWith( '<!-- wp:activitypub/reply', $result['post_content'], 'Reply block should be at the start' );

		// Should have all three paragraphs as blocks.
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $result['post_content'] );
		$this->assertSame( 3, substr_count( $result['post_content'], '<!-- wp:paragraph -->' ), 'Should have 3 paragraph blocks' );
		$this->assertSame( 3, substr_count( $result['post_content'], '<!-- /wp:paragraph -->' ), 'Should close 3 paragraph blocks' );
	}

	/**
	 * Test filter_import_mastodon_post_data with empty content.
	 *
	 * @covers ::filter_import_mastodon_post_data
	 */
	public function test_filter_import_mastodon_post_data_with_empty_content() {
		$data = array(
			'post_content' => '',
		);

		$post = array(
			'object' => array(
				'inReplyTo' => null,
			),
		);

		$result = Blocks::filter_import_mastodon_post_data( $data, $post );

		// Should handle empty content gracefully.
		$this->assertSame( '', $result['post_content'], 'Should return empty string for empty content' );
	}

	/**
	 * Test filter_import_mastodon_post_data with content but no paragraph tags.
	 *
	 * @covers ::filter_import_mastodon_post_data
	 */
	public function test_filter_import_mastodon_post_data_with_non_paragraph_content() {
		$data = array(
			'post_content' => 'Plain text without paragraph tags',
		);

		$post = array(
			'object' => array(
				'inReplyTo' => null,
			),
		);

		$result = Blocks::filter_import_mastodon_post_data( $data, $post );

		// Should handle content without <p> tags.
		$this->assertSame( '', $result['post_content'], 'Should return empty string when no paragraphs found' );
	}

	/**
	 * Test filter_import_mastodon_post_data preserves data keys.
	 *
	 * @covers ::filter_import_mastodon_post_data
	 */
	public function test_filter_import_mastodon_post_data_preserves_other_data() {
		$data = array(
			'post_content' => '<p>Test content</p>',
			'post_author'  => 123,
			'post_date'    => '2024-01-15T10:30:00Z',
			'post_excerpt' => 'Test excerpt',
			'meta_input'   => array( '_source_id' => 'test-id' ),
		);

		$post = array(
			'object' => array(
				'inReplyTo' => null,
			),
		);

		$result = Blocks::filter_import_mastodon_post_data( $data, $post );

		// Should preserve all other data keys.
		$this->assertArrayHasKey( 'post_author', $result, 'Should preserve post_author' );
		$this->assertSame( 123, $result['post_author'], 'Should preserve post_author value' );
		$this->assertArrayHasKey( 'post_date', $result, 'Should preserve post_date' );
		$this->assertSame( '2024-01-15T10:30:00Z', $result['post_date'], 'Should preserve post_date value' );
		$this->assertArrayHasKey( 'post_excerpt', $result, 'Should preserve post_excerpt' );
		$this->assertArrayHasKey( 'meta_input', $result, 'Should preserve meta_input' );

		// Should only modify post_content.
		$this->assertNotSame( '<p>Test content</p>', $result['post_content'], 'Should modify post_content' );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $result['post_content'], 'Should add block markup' );
	}

	/**
	 * Test filter_import_mastodon_post_data with nested HTML in paragraphs.
	 *
	 * @covers ::filter_import_mastodon_post_data
	 */
	public function test_filter_import_mastodon_post_data_with_nested_html() {
		$data = array(
			'post_content' => '<p>Text with <a href="https://example.com">a link</a> and <strong>bold text</strong></p>',
		);

		$post = array(
			'object' => array(
				'inReplyTo' => null,
			),
		);

		$result = Blocks::filter_import_mastodon_post_data( $data, $post );

		// Should preserve nested HTML.
		$this->assertStringContainsString( '<a href="https://example.com">a link</a>', $result['post_content'], 'Should preserve links' );
		$this->assertStringContainsString( '<strong>bold text</strong>', $result['post_content'], 'Should preserve strong tags' );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $result['post_content'], 'Should add block markup' );
	}

	/**
	 * Test filter_import_mastodon_post_data integration with array-based post data.
	 *
	 * @covers ::filter_import_mastodon_post_data
	 */
	public function test_filter_import_mastodon_post_data_with_complete_activity() {
		$data = array(
			'post_content' => '<p>Complete test</p>',
		);

		// Realistic Mastodon activity structure.
		$post = array(
			'id'        => 'https://mastodon.social/users/example/statuses/123/activity',
			'type'      => 'Create',
			'actor'     => 'https://mastodon.social/users/example',
			'published' => '2024-01-15T10:30:00Z',
			'to'        => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object'    => array(
				'id'        => 'https://mastodon.social/users/example/statuses/123',
				'type'      => 'Note',
				'content'   => '<p>Complete test</p>',
				'published' => '2024-01-15T10:30:00Z',
				'inReplyTo' => 'https://mastodon.social/@other/456',
			),
		);

		$result = Blocks::filter_import_mastodon_post_data( $data, $post );

		// Should work with complete activity structure.
		$this->assertIsArray( $result, 'Should return array' );
		$this->assertArrayHasKey( 'post_content', $result, 'Should have post_content key' );
		$this->assertStringContainsString( 'wp:activitypub/reply', $result['post_content'], 'Should add reply block' );
		$this->assertStringContainsString( '<!-- wp:paragraph -->', $result['post_content'], 'Should add paragraph block' );
	}

	/**
	 * Test the reactions block with v1 deprecated markup (title attribute, no HTML content).
	 *
	 * Block v1 (plugin 1.0.0): Dynamic block with title attribute, self-closing.
	 */
	public function test_render_reactions_block_with_v1_markup() {
		$post_id = $this->get_post_id_with_reactions();

		// v1 with custom title.
		$block_markup = '<!-- wp:activitypub/reactions {"title":"What people think about it on the Fediverse!","postId":' . $post_id . '} /-->';
		$output       = do_blocks( $block_markup );
		$expected     = '<h6 class="wp-block-heading">What people think about it on the Fediverse!</h6>';

		$this->assertStringContainsString( $expected, $output );

		// v1 with default title.
		$block_markup = '<!-- wp:activitypub/reactions {"postId":' . $post_id . '} /-->';
		$output       = do_blocks( $block_markup );
		$expected     = '<h6 class="wp-block-heading">Fediverse Reactions</h6>';

		$this->assertStringContainsString( $expected, $output );
	}

	/**
	 * Test the reactions block with v2 deprecated markup (fragment with separate div).
	 *
	 * Block v2 (plugin 2.0.0): Fragment with InnerBlocks and separate div, no wp-block- prefix.
	 */
	public function test_render_reactions_block_with_v2_markup() {
		$post_id = $this->get_post_id_with_reactions();

		// v2 format: fragment with heading innerBlock and separate div (no wp-block- prefix).
		$block_markup = '<!-- wp:activitypub/reactions {"postId":' . $post_id . '} -->
<!-- wp:heading {"level":6} -->
<h6 class="wp-block-heading">Fediverse reactions</h6>
<!-- /wp:heading -->
<div class="activitypub-reactions-block"></div>
<!-- /wp:activitypub/reactions -->';

		$output = do_blocks( $block_markup );

		// Should render the heading from innerBlocks.
		$this->assertStringContainsString( 'Fediverse reactions', $output );
		// Should have the wrapper with wp-block- prefix (from get_block_wrapper_attributes).
		$this->assertStringContainsString( 'wp-block-activitypub-reactions', $output );
		// Should have reaction content.
		$this->assertStringContainsString( 'activitypub-reactions', $output );
	}

	/**
	 * Test the reactions block with v3 markup (useBlockProps with wp-block- prefix).
	 *
	 * Block v3 (plugin 3.0.0+): Uses useBlockProps.save() with wp-block- prefix class.
	 */
	public function test_render_reactions_block_with_v3_markup() {
		$post_id = $this->get_post_id_with_reactions();

		// v3 format: div with useBlockProps (has wp-block- prefix) wrapping InnerBlocks.
		$block_markup = '<!-- wp:activitypub/reactions {"postId":' . $post_id . '} -->
<div class="wp-block-activitypub-reactions activitypub-reactions-block"><!-- wp:heading {"level":6} -->
<h6 class="wp-block-heading">Fediverse reactions</h6>
<!-- /wp:heading --></div>
<!-- /wp:activitypub/reactions -->';

		$output = do_blocks( $block_markup );

		// Should render the heading from innerBlocks.
		$this->assertStringContainsString( 'Fediverse reactions', $output );
		// Should have the wrapper with wp-block- prefix.
		$this->assertStringContainsString( 'wp-block-activitypub-reactions', $output );
		// Should have reaction content.
		$this->assertStringContainsString( 'activitypub-reactions', $output );
	}

	/**
	 * Test the reactions block with facepile style shows avatars.
	 */
	public function test_render_reactions_block_facepile_style_shows_avatars() {
		$post_id = $this->get_post_id_with_reactions();

		$block_markup = '<!-- wp:activitypub/reactions {"postId":' . $post_id . ',"className":"is-style-facepile","displayStyle":"facepile"} /-->';
		$output       = do_blocks( $block_markup );

		$this->assertStringContainsString( 'is-style-facepile', $output );
		$this->assertStringContainsString( 'reaction-avatars', $output );
	}

	/**
	 * Test the reactions block with compact style hides avatars.
	 */
	public function test_render_reactions_block_compact_style_hides_avatars() {
		$post_id = $this->get_post_id_with_reactions();

		$block_markup = '<!-- wp:activitypub/reactions {"postId":' . $post_id . ',"className":"is-style-compact","displayStyle":"compact"} /-->';
		$output       = do_blocks( $block_markup );

		$this->assertStringContainsString( 'is-style-compact', $output );
		$this->assertStringNotContainsString( 'reaction-avatars', $output );
	}

	/**
	 * Test the reactions block defaults to facepile when avatars are enabled.
	 */
	public function test_render_reactions_block_defaults_to_facepile_with_avatars_enabled() {
		\update_option( 'show_avatars', true );

		$post_id = $this->get_post_id_with_reactions();

		// Block without explicit style class.
		$block_markup = '<!-- wp:activitypub/reactions {"postId":' . $post_id . '} /-->';
		$output       = do_blocks( $block_markup );

		$this->assertStringContainsString( 'is-style-facepile', $output );
		$this->assertStringContainsString( 'reaction-avatars', $output );
	}

	/**
	 * Test the reactions block defaults to compact when avatars are disabled.
	 */
	public function test_render_reactions_block_defaults_to_compact_with_avatars_disabled() {
		\update_option( 'show_avatars', false );

		$post_id = $this->get_post_id_with_reactions();

		// Block without explicit style class.
		$block_markup = '<!-- wp:activitypub/reactions {"postId":' . $post_id . '} /-->';
		$output       = do_blocks( $block_markup );

		$this->assertStringContainsString( 'is-style-compact', $output );
		$this->assertStringNotContainsString( 'reaction-avatars', $output );

		// Restore default.
		\update_option( 'show_avatars', true );
	}

	/**
	 * Test the reactions block with no reactions returns empty comment.
	 */
	public function test_render_reactions_block_with_no_reactions() {
		$post_id = self::factory()->post->create();

		$block_markup = '<!-- wp:activitypub/reactions {"postId":' . $post_id . '} /-->';
		$output       = do_blocks( $block_markup );

		$this->assertStringContainsString( '<!-- Reactions block: No reactions found. -->', $output );
	}

	/**
	 * Get a post ID with reactions.
	 *
	 * @return int Post ID.
	 */
	private function get_post_id_with_reactions() {
		$post_id = self::factory()->post->create();

		$activity = array(
			'type'   => 'Like',
			'actor'  => 'https://example.com/users/test',
			'object' => get_permalink( $post_id ),
			'id'     => 'https://example.com/activities/like/123',
		);

		// Mock actor metadata.
		$mock_actor_metadata = function () {
			return array(
				'name'              => 'Test User',
				'preferredUsername' => 'test',
				'id'                => 'https://example.com/users/test',
				'url'               => 'https://example.com/@test',
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata );

		$approve_comment = function () {
			return '1';
		};
		\add_filter( 'pre_comment_approved', $approve_comment );

		Interactions::add_reaction( $activity );

		// Clean up.
		remove_filter( 'pre_get_remote_metadata_by_actor', $mock_actor_metadata );
		remove_filter( 'pre_comment_approved', $approve_comment );

		return $post_id;
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
	 * Test Extra Fields block rendering with blog user.
	 *
	 * @covers ::get_user_id
	 * @covers \Activitypub\Collection\Extra_Fields::get_actor_fields
	 * @covers \Activitypub\Collection\Extra_Fields::get_formatted_content
	 */
	public function test_render_extra_fields_block_with_blog_user() {
		$block_markup = '<!-- wp:activitypub/extra-fields {"selectedUser":"blog"} /-->';
		$output       = do_blocks( $block_markup );

		$this->assertStringContainsString( 'activitypub-extra-fields-block-wrapper', $output );
		$this->assertStringContainsString( 'Blog Website', $output );
		$this->assertStringContainsString( 'blog.example.com', $output );
	}

	/**
	 * Test Extra Fields block rendering with specific user ID.
	 *
	 * @covers ::get_user_id
	 * @covers \Activitypub\Collection\Extra_Fields::get_actor_fields
	 * @covers \Activitypub\Collection\Extra_Fields::get_formatted_content
	 */
	public function test_render_extra_fields_block_with_specific_user() {
		$block_markup = sprintf(
			'<!-- wp:activitypub/extra-fields {"selectedUser":"%d"} /-->',
			self::$extra_fields_user_id
		);
		$output       = do_blocks( $block_markup );

		$this->assertStringContainsString( 'Website', $output );
		$this->assertStringContainsString( 'example.com', $output );
		$this->assertStringContainsString( 'Location', $output );
		$this->assertStringContainsString( 'San Francisco, CA', $output );
		$this->assertStringContainsString( 'Pronouns', $output );
		$this->assertStringContainsString( 'they/them', $output );
	}

	/**
	 * Test Extra Fields block maxFields attribute limits output.
	 *
	 * @covers ::get_user_id
	 * @covers \Activitypub\Collection\Extra_Fields::get_actor_fields
	 */
	public function test_render_extra_fields_block_with_max_fields() {
		$block_markup = sprintf(
			'<!-- wp:activitypub/extra-fields {"selectedUser":"%d","maxFields":2} /-->',
			self::$extra_fields_user_id
		);
		$output       = do_blocks( $block_markup );

		// Should contain first two fields.
		$this->assertStringContainsString( 'Website', $output );
		$this->assertStringContainsString( 'Location', $output );

		// Should not contain third field.
		$this->assertStringNotContainsString( 'Pronouns', $output );
		$this->assertStringNotContainsString( 'they/them', $output );
	}

	/**
	 * Test Extra Fields block with no extra fields returns empty.
	 *
	 * @covers ::get_user_id
	 * @covers \Activitypub\Collection\Extra_Fields::get_actor_fields
	 */
	public function test_render_extra_fields_block_with_no_fields() {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'empty_user',
				'user_email' => 'empty@example.com',
			)
		);

		// Prevent default extra fields from being created.
		$prevent_extra_fields = function ( $fields, $uid ) use ( $user_id ) {
			if ( $uid === $user_id ) {
				return array();
			}
			return $fields;
		};
		add_filter( 'activitypub_get_actor_extra_fields', $prevent_extra_fields, 10, 2 );

		$block_markup = sprintf(
			'<!-- wp:activitypub/extra-fields {"selectedUser":"%d"} /-->',
			$user_id
		);
		$output       = do_blocks( $block_markup );

		$this->assertEmpty( $output );

		remove_filter( 'activitypub_get_actor_extra_fields', $prevent_extra_fields );
	}

	/**
	 * Test Extra Fields block with cards style and background color.
	 *
	 * @covers ::get_user_id
	 * @covers \Activitypub\Collection\Extra_Fields::get_actor_fields
	 */
	public function test_render_extra_fields_block_with_cards_style() {
		$block_markup = sprintf(
			'<!-- wp:activitypub/extra-fields {"selectedUser":"%d","className":"is-style-cards","backgroundColor":"primary"} /-->',
			self::$extra_fields_user_id
		);
		$output       = do_blocks( $block_markup );

		$this->assertStringContainsString( 'is-style-cards', $output );
		$this->assertStringContainsString( 'var(--wp--preset--color--primary)', $output );
	}

	/**
	 * Test the reply block with embedPost uses proper width for same-site embeds.
	 *
	 * When rendering same-site embeds, WordPress Core's get_oembed_response_data()
	 * clamps width to min=200 if no width is provided. This test verifies that
	 * render_reply_block() passes an explicit width to wp_oembed_get() to avoid
	 * the 200x200 minimum dimension issue.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_render_reply_block_embed_uses_proper_width() {
		// Create a post to embed.
		$post_id  = self::factory()->post->create(
			array(
				'post_title'   => 'Test Embed Post',
				'post_content' => 'Test content for embedding.',
				'post_status'  => 'publish',
			)
		);
		$post_url = get_permalink( $post_id );

		// Track the width passed to wp_oembed_get via the pre_oembed_result filter.
		$captured_width = null;
		$capture_width  = function ( $result, $url, $args ) use ( &$captured_width, $post_url ) {
			if ( false !== strpos( $url, $post_url ) || false !== strpos( $post_url, $url ) ) {
				$captured_width = isset( $args['width'] ) ? $args['width'] : null;
			}
			// Return a mock embed to avoid actual HTTP request.
			return '<iframe src="' . esc_url( $url ) . '" width="600" height="338"></iframe>';
		};

		add_filter( 'pre_oembed_result', $capture_width, 5, 3 ); // Priority 5 to run before wp_filter_pre_oembed_result.

		$block_markup = sprintf(
			'<!-- wp:activitypub/reply {"url":"%s","embedPost":true} /-->',
			esc_url( $post_url )
		);

		do_blocks( $block_markup );

		remove_filter( 'pre_oembed_result', $capture_width, 5 );

		// Width should be set (not null) and should be a reasonable value (not 0).
		$this->assertNotNull( $captured_width, 'Width should be passed to wp_oembed_get()' );
		$this->assertGreaterThan( 200, $captured_width, 'Width should be greater than the 200px minimum to avoid squished embeds' );
	}

	/**
	 * Test the reply block embed respects content_width global when set.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_render_reply_block_embed_respects_content_width() {
		// Set a custom content_width.
		$original_content_width   = isset( $GLOBALS['content_width'] ) ? $GLOBALS['content_width'] : null;
		$GLOBALS['content_width'] = 800;

		// Create a post to embed.
		$post_id  = self::factory()->post->create(
			array(
				'post_title'   => 'Test Content Width Post',
				'post_content' => 'Test content.',
				'post_status'  => 'publish',
			)
		);
		$post_url = get_permalink( $post_id );

		// Track the width passed to wp_oembed_get.
		$captured_width = null;
		$capture_width  = function ( $result, $url, $args ) use ( &$captured_width, $post_url ) {
			if ( false !== strpos( $url, $post_url ) || false !== strpos( $post_url, $url ) ) {
				$captured_width = isset( $args['width'] ) ? $args['width'] : null;
			}
			return '<iframe src="' . esc_url( $url ) . '" width="800" height="450"></iframe>';
		};

		add_filter( 'pre_oembed_result', $capture_width, 5, 3 );

		$block_markup = sprintf(
			'<!-- wp:activitypub/reply {"url":"%s","embedPost":true} /-->',
			esc_url( $post_url )
		);

		do_blocks( $block_markup );

		remove_filter( 'pre_oembed_result', $capture_width, 5 );

		// Restore original content_width.
		if ( null === $original_content_width ) {
			unset( $GLOBALS['content_width'] );
		} else {
			$GLOBALS['content_width'] = $original_content_width;
		}

		// Width should match the content_width we set.
		$this->assertSame( 800, $captured_width, 'Width should use $content_width when available' );
	}

	/**
	 * Data provider for convert_from_html tests.
	 *
	 * @return array[] Each entry: [ html, expected_output ].
	 */
	public function data_convert_from_html() {
		return array(
			'empty string'        => array(
				'',
				'',
			),
			'single paragraph'    => array(
				'<p>Hello world</p>',
				'<!-- wp:paragraph --><p>Hello world</p><!-- /wp:paragraph -->',
			),
			'two paragraphs'      => array(
				'<p>First</p><p>Second</p>',
				'<!-- wp:paragraph --><p>First</p><!-- /wp:paragraph -->'
				. '<!-- wp:paragraph --><p>Second</p><!-- /wp:paragraph -->',
			),
			'heading h1'          => array(
				'<h1>Title</h1>',
				'<!-- wp:heading --><h1>Title</h1><!-- /wp:heading -->',
			),
			'heading h3'          => array(
				'<h3>Subtitle</h3>',
				'<!-- wp:heading --><h3>Subtitle</h3><!-- /wp:heading -->',
			),
			'unordered list'      => array(
				'<ul><li>One</li><li>Two</li></ul>',
				'<!-- wp:list --><ul><li>One</li><li>Two</li></ul><!-- /wp:list -->',
			),
			'ordered list'        => array(
				'<ol><li>First</li><li>Second</li></ol>',
				'<!-- wp:list {"ordered":true} --><ol><li>First</li><li>Second</li></ol><!-- /wp:list -->',
			),
			'blockquote'          => array(
				'<blockquote><p>A quote</p></blockquote>',
				'<!-- wp:quote --><blockquote><p>A quote</p></blockquote><!-- /wp:quote -->',
			),
			'separator'           => array(
				'<hr>',
				'<!-- wp:separator --><hr><!-- /wp:separator -->',
			),
			'image'               => array(
				'<img src="https://example.com/photo.jpg" alt="A photo">',
				'<!-- wp:image --><img src="https://example.com/photo.jpg" alt="A photo"><!-- /wp:image -->',
			),
			'figure with caption' => array(
				'<figure><img src="https://example.com/photo.jpg"><figcaption>Caption</figcaption></figure>',
				'<!-- wp:image --><figure><img src="https://example.com/photo.jpg"><figcaption>Caption</figcaption></figure><!-- /wp:image -->',
			),
			'inline in paragraph' => array(
				'<p>Visit <a href="https://example.com">my site</a> and <strong>enjoy</strong></p>',
				'<!-- wp:paragraph --><p>Visit <a href="https://example.com">my site</a> and <strong>enjoy</strong></p><!-- /wp:paragraph -->',
			),
			'skips br'            => array(
				'<p>Hello</p><br><p>World</p>',
				'<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->'
				. '<!-- wp:paragraph --><p>World</p><!-- /wp:paragraph -->',
			),
			'nested list'         => array(
				'<ul><li>Parent<ul><li>Child</li></ul></li></ul>',
				'<!-- wp:list --><ul><li>Parent<ul><li>Child</li></ul></li></ul><!-- /wp:list -->',
			),
			'bare inline span'    => array(
				'<span>Some text</span>',
				'<!-- wp:paragraph --><span>Some text</span><!-- /wp:paragraph -->',
			),
			'unknown tag'         => array(
				'<div>Custom content</div>',
				'<!-- wp:html --><div>Custom content</div><!-- /wp:html -->',
			),
			'mixed content'       => array(
				'<h2>Title</h2><p>Text</p><ul><li>Item</li></ul><hr><blockquote><p>Quote</p></blockquote>',
				'<!-- wp:heading --><h2>Title</h2><!-- /wp:heading -->'
				. '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->'
				. '<!-- wp:list --><ul><li>Item</li></ul><!-- /wp:list -->'
				. '<!-- wp:separator --><hr><!-- /wp:separator -->'
				. '<!-- wp:quote --><blockquote><p>Quote</p></blockquote><!-- /wp:quote -->',
			),
		);
	}

	/**
	 * Test convert_from_html.
	 *
	 * @dataProvider data_convert_from_html
	 * @covers ::convert_from_html
	 *
	 * @param string $html     The input HTML.
	 * @param string $expected The expected block markup.
	 */
	public function test_convert_from_html( $html, $expected ) {
		$this->assertSame( $expected, Blocks::convert_from_html( $html ) );
	}

	/**
	 * Test Extra Fields block preserves HTML in field content.
	 *
	 * @covers ::get_user_id
	 * @covers \Activitypub\Collection\Extra_Fields::get_actor_fields
	 * @covers \Activitypub\Collection\Extra_Fields::get_formatted_content
	 */
	public function test_render_extra_fields_block_preserves_html() {
		self::factory()->post->create(
			array(
				'post_type'    => Extra_Fields::USER_POST_TYPE,
				'post_title'   => 'Rich Content',
				'post_content' => '<!-- wp:paragraph --><p>Visit <strong>my site</strong> at <a href="https://test.com">test.com</a></p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
				'post_author'  => self::$extra_fields_user_id,
				'menu_order'   => 40,
			)
		);

		$block_markup = sprintf(
			'<!-- wp:activitypub/extra-fields {"selectedUser":"%d"} /-->',
			self::$extra_fields_user_id
		);
		$output       = do_blocks( $block_markup );

		$this->assertStringContainsString( '<strong>my site</strong>', $output );
		$this->assertStringContainsString( '<a href="https://test.com"', $output );
	}

	/**
	 * Test add_stats_image_attachment adds image for stats block.
	 *
	 * @covers ::add_stats_image_attachment
	 */
	public function test_add_stats_image_attachment() {
		if ( ! \Activitypub\Cache\Stats_Image::is_available() ) {
			$this->markTestSkipped( 'GD library is not available.' );
		}

		// Seed stats so get_url() can generate the image.
		\update_option(
			'activitypub_stats_0_2025_annual',
			array(
				'posts_count'          => 10,
				'followers_start'      => 0,
				'followers_end'        => 5,
				'followers_net_change' => 5,
				'most_active_month'    => 1,
				'top_multiplicator'    => null,
				'top_posts'            => array(),
				'compiled_at'          => \gmdate( 'Y-m-d H:i:s' ),
				'like_count'           => 5,
				'repost_count'         => 2,
				'comment_count'        => 1,
				'quote_count'          => 0,
			),
			false
		);

		$post = self::factory()->post->create_and_get(
			array(
				'post_content' => '<!-- wp:activitypub/stats {"selectedUser":"blog","year":2025} /-->',
				'post_status'  => 'publish',
			)
		);

		$attachments = Blocks::add_stats_image_attachment( array(), $post );

		$this->assertCount( 1, $attachments );
		$this->assertSame( 'Image', $attachments[0]['type'] );
		$this->assertStringContainsString( 'stats', $attachments[0]['url'] );
		$this->assertStringContainsString( '2025', $attachments[0]['name'] );
	}

	/**
	 * Test add_stats_image_attachment with no stats block.
	 *
	 * @covers ::add_stats_image_attachment
	 */
	public function test_add_stats_image_attachment_no_block() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_content' => '<!-- wp:paragraph --><p>Hello world</p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
			)
		);

		$attachments = Blocks::add_stats_image_attachment( array(), $post );

		$this->assertCount( 0, $attachments );
	}

	/**
	 * Test add_stats_image_attachment preserves existing attachments.
	 *
	 * @covers ::add_stats_image_attachment
	 */
	public function test_add_stats_image_attachment_preserves_existing() {
		if ( ! \Activitypub\Cache\Stats_Image::is_available() ) {
			$this->markTestSkipped( 'GD library is not available.' );
		}

		// Seed stats.
		\update_option(
			'activitypub_stats_0_2025_annual',
			array(
				'posts_count'          => 10,
				'followers_start'      => 0,
				'followers_end'        => 5,
				'followers_net_change' => 5,
				'most_active_month'    => 1,
				'top_multiplicator'    => null,
				'top_posts'            => array(),
				'compiled_at'          => \gmdate( 'Y-m-d H:i:s' ),
				'like_count'           => 5,
				'repost_count'         => 2,
				'comment_count'        => 1,
				'quote_count'          => 0,
			),
			false
		);

		$post = self::factory()->post->create_and_get(
			array(
				'post_content' => '<!-- wp:activitypub/stats {"selectedUser":"blog","year":2025} /-->',
				'post_status'  => 'publish',
			)
		);

		$existing = array(
			array(
				'type' => 'Image',
				'url'  => 'https://example.com/photo.jpg',
			),
		);

		$attachments = Blocks::add_stats_image_attachment( $existing, $post );

		$this->assertCount( 2, $attachments );
		$this->assertSame( 'https://example.com/photo.jpg', $attachments[0]['url'] );
		$this->assertStringContainsString( 'stats', $attachments[1]['url'] );
	}

	/**
	 * Test add_stats_image_attachment with user ID.
	 *
	 * @covers ::add_stats_image_attachment
	 */
	public function test_add_stats_image_attachment_with_user_id() {
		if ( ! \Activitypub\Cache\Stats_Image::is_available() ) {
			$this->markTestSkipped( 'GD library is not available.' );
		}

		// Seed stats for user 1.
		\update_option(
			'activitypub_stats_1_2024_annual',
			array(
				'posts_count'          => 10,
				'followers_start'      => 0,
				'followers_end'        => 5,
				'followers_net_change' => 5,
				'most_active_month'    => 1,
				'top_multiplicator'    => null,
				'top_posts'            => array(),
				'compiled_at'          => \gmdate( 'Y-m-d H:i:s' ),
				'like_count'           => 5,
				'repost_count'         => 2,
				'comment_count'        => 1,
				'quote_count'          => 0,
			),
			false
		);

		$post = self::factory()->post->create_and_get(
			array(
				'post_content' => '<!-- wp:activitypub/stats {"selectedUser":"1","year":2024} /-->',
				'post_status'  => 'publish',
			)
		);

		$attachments = Blocks::add_stats_image_attachment( array(), $post );

		$this->assertCount( 1, $attachments );
		$this->assertStringContainsString( 'stats', $attachments[0]['url'] );
		$this->assertStringContainsString( '2024', $attachments[0]['name'] );
	}

	/**
	 * Test Stats_Image::get_url generates valid URL.
	 *
	 * @covers \Activitypub\Cache\Stats_Image::get_url
	 */
	public function test_get_stats_image_url() {
		$url = \Activitypub\Cache\Stats_Image::get_url( 0, 2025 );

		if ( \is_wp_error( $url ) ) {
			// GD not available; fall back to REST endpoint URL.
			$url = \get_rest_url( null, ACTIVITYPUB_REST_NAMESPACE . '/stats/image/0/2025' );
		}

		// URL contains the stats path (either cached file or REST endpoint).
		$this->assertStringContainsString( 'stats', $url );
		$this->assertStringContainsString( '2025', $url );
	}

	/**
	 * Test Stats_Image::get_url works with plain permalinks.
	 *
	 * @covers \Activitypub\Cache\Stats_Image::get_url
	 */
	public function test_get_stats_image_url_plain_permalinks() {
		$original = \get_option( 'permalink_structure' );
		\update_option( 'permalink_structure', '' );

		$url = \Activitypub\Cache\Stats_Image::get_url( 1, 2024 );

		if ( \is_wp_error( $url ) ) {
			// GD not available; fall back to REST endpoint URL.
			$url = \get_rest_url( null, ACTIVITYPUB_REST_NAMESPACE . '/stats/image/1/2024' );
		}

		$this->assertStringContainsString( 'stats', $url );
		$this->assertStringContainsString( '2024', $url );

		\update_option( 'permalink_structure', $original );
	}

	/**
	 * Admin main queries must not attach the reply-exclusion filter, even when ?filter=posts is set.
	 *
	 * @covers ::filter_query_loop_vars
	 */
	public function test_filter_query_loop_vars_does_not_touch_admin_queries() {
		\remove_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );

		// Land on the frontend so $GLOBALS['wp_query'] is a real main query.
		$this->go_to( \home_url( '/' ) );
		\remove_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );

		$_GET['filter'] = 'posts';
		\set_current_screen( 'edit-post' );
		$this->assertTrue( \is_admin(), 'Precondition: set_current_screen must make is_admin() true.' );

		Blocks::filter_query_loop_vars( $GLOBALS['wp_query'] );

		$attached = false !== \has_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );

		unset( $_GET['filter'] );
		\remove_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );
		\set_current_screen( 'front' );

		$this->assertFalse( $attached, 'Admin queries must never attach the posts_where exclusion filter.' );
	}

	/**
	 * Feed queries must not attach the reply-exclusion filter.
	 *
	 * @covers ::filter_query_loop_vars
	 */
	public function test_filter_query_loop_vars_does_not_touch_feed_queries() {
		\remove_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );

		$this->go_to( \home_url( '/?feed=rss2' ) );
		\remove_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );

		$_GET['filter'] = 'posts';

		$this->assertTrue( $GLOBALS['wp_query']->is_feed(), 'Precondition: the main query must be a feed query.' );

		Blocks::filter_query_loop_vars( $GLOBALS['wp_query'] );

		$attached = false !== \has_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );

		unset( $_GET['filter'] );
		\remove_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );

		$this->assertFalse( $attached, 'Feed queries must never attach the posts_where exclusion filter.' );
	}

	/**
	 * Frontend main queries must not attach the exclusion filter without an explicit ?filter=posts opt-in.
	 *
	 * @covers ::filter_query_loop_vars
	 */
	public function test_filter_query_loop_vars_skips_without_explicit_filter_param() {
		\remove_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );

		$this->go_to( \home_url( '/' ) );
		\remove_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );

		unset( $_GET['filter'] );
		Blocks::filter_query_loop_vars( $GLOBALS['wp_query'] );
		$attached_default = false !== \has_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );
		\remove_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );

		$_GET['filter'] = 'posts-and-replies';
		Blocks::filter_query_loop_vars( $GLOBALS['wp_query'] );
		$attached_all = false !== \has_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );

		unset( $_GET['filter'] );
		\remove_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );

		$this->assertFalse( $attached_default, 'Without ?filter the exclusion filter must not be attached.' );
		$this->assertFalse( $attached_all, 'With ?filter=posts-and-replies the exclusion filter must not be attached.' );
	}

	/**
	 * An explicit ?filter=posts opt-in attaches the filter and hides reply-block posts.
	 *
	 * @covers ::filter_query_loop_vars
	 * @covers ::exclude_replies_where
	 */
	public function test_filter_query_loop_vars_applies_on_explicit_posts_filter() {
		$reply_post = self::factory()->post->create(
			array(
				'post_title'   => 'Reply post',
				'post_content' => '<!-- wp:activitypub/reply {"url":"https://example.com/c"} /-->',
				'post_status'  => 'publish',
			)
		);
		$plain_post = self::factory()->post->create(
			array(
				'post_title'   => 'Plain post',
				'post_content' => '<!-- wp:paragraph --><p>Hi.</p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
			)
		);

		$this->go_to( \home_url( '/?filter=posts' ) );

		$ids = \wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );

		\remove_filter( 'posts_where', array( Blocks::class, 'exclude_replies_where' ) );
		\wp_delete_post( $reply_post, true );
		\wp_delete_post( $plain_post, true );

		$this->assertContains( $plain_post, $ids, 'Plain posts must stay visible under ?filter=posts.' );
		$this->assertNotContains( $reply_post, $ids, 'Reply-block posts must be hidden under ?filter=posts.' );
	}
}
