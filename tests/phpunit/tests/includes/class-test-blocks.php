<?php
/**
 * Test file for Blocks class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Blocks;
use Activitypub\Collection\Interactions;

/**
 * Test class for Blocks.
 *
 * @coversDefaultClass \Activitypub\Blocks
 */
class Test_Blocks extends \WP_UnitTestCase {
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

		$pre_filter = function ( $preempt, $args, $url ) use ( $mock_activity ) {
			if ( false !== strpos( $url, 'mastodon.social' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode( $mock_activity ),
				);
			}
			return $preempt;
		};

		// Add filter to mock the HTTP response before Http::get_remote_object is called.
		add_filter( 'pre_http_request', $pre_filter, 10, 3 );

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

		remove_filter( 'pre_http_request', $pre_filter, 10, 3 );
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
	public function test_filter_import_mastodon_post_data_without_inreplyto() {
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
	 * Test the reactions block with deprecated markup.
	 */
	public function test_render_reactions_block_with_deprecated_markup() {
		$post_id = $this->get_post_id_with_reactions();

		$block_markup = '<!-- wp:activitypub/reactions {"title":"What people think about it on the Fediverse!","postId":' . $post_id . '} /-->';
		$output       = do_blocks( $block_markup );
		$expected     = '<h6 class="wp-block-heading">What people think about it on the Fediverse!</h6>';

		$this->assertStringContainsString( $expected, $output );

		$block_markup = '<!-- wp:activitypub/reactions {"postId":' . $post_id . '} /-->';
		$output       = do_blocks( $block_markup );
		$expected     = '<h6 class="wp-block-heading">Fediverse Reactions</h6>';

		$this->assertStringContainsString( $expected, $output );
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
		\add_filter(
			'pre_get_remote_metadata_by_actor',
			function () {
				return array(
					'name'              => 'Test User',
					'preferredUsername' => 'test',
					'id'                => 'https://example.com/users/test',
					'url'               => 'https://example.com/@test',
				);
			}
		);

		\add_filter(
			'pre_comment_approved',
			function () {
				return '1';
			}
		);

		Interactions::add_reaction( $activity );

		// Clean up.
		remove_all_filters( 'pre_get_remote_metadata_by_actor' );
		remove_all_filters( 'pre_comment_approved' );

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
}
