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
	 * @covers ::register_postmeta
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
	 * Test filter_import_mastodon_post_data with regular paragraphs.
	 *
	 * @covers ::filter_import_mastodon_post_data
	 */
	public function test_filter_import_mastodon_post_data_with_paragraphs() {
		$data = array(
			'post_content' => '<p>First paragraph</p><p>Second paragraph</p>',
		);

		$post = (object) array(
			'object' => (object) array(
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
		$post      = (object) array(
			'object' => (object) array(
				'inReplyTo' => $reply_url,
			),
		);

		$result = Blocks::filter_import_mastodon_post_data( $data, $post );

		$this->assertStringContainsString( '<!-- wp:activitypub/reply {"url":"https://mastodon.social/@user/123456","embedPost":true} /-->', $result['post_content'] );
		$this->assertStringContainsString( "<!-- wp:paragraph -->\n<p>This is a reply</p>\n<!-- /wp:paragraph -->", $result['post_content'] );
	}

	/**
	 * Test the reactions block with deprecated markup.
	 *
	 * @covers ::render_post_reactions_block
	 */
	public function test_render_reactions_block() {
		$block_markup = '<!-- wp:activitypub/reactions -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Fediverse Custom</h3>
<!-- /wp:heading --><div class="activitypub-reactions-block"></div>
<!-- /wp:activitypub/reactions -->';
		$output       = do_blocks( $block_markup );
		$expected     = '<div class="wp-block-activitypub-reactions is-layout-constrained wp-block-reactions-is-layout-constrained" data-attrs="{&quot;postId&quot;:false}">

<h3 hidden class="wp-block-heading">Fediverse Custom</h3>
<div class="activitypub-reactions-block"></div>
</div>';

		$this->assertSame( $expected, $output );

		// Reactions block with reactions.
		$post_id      = $this->get_post_id_with_reactions();
		$block_markup = sprintf(
			'<!-- wp:activitypub/reactions {"postId":%d} -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Fediverse Custom</h3>
<!-- /wp:heading --><div class="activitypub-reactions-block"></div>
<!-- /wp:activitypub/reactions -->',
			$post_id
		);
		$output       = do_blocks( $block_markup );
		$expected     = sprintf(
			'<div class="wp-block-activitypub-reactions is-layout-constrained wp-block-reactions-is-layout-constrained" data-attrs="{&quot;postId&quot;:%d}">

<h3 class="wp-block-heading">Fediverse Custom</h3>
<div class="activitypub-reactions-block"></div>
</div>',
			$post_id
		);

		$this->assertSame( $expected, $output );
	}

	/**
	 * Test the reactions block with deprecated markup.
	 *
	 * @covers ::render_post_reactions_block
	 */
	public function test_render_reactions_block_with_deprecated_markup() {
		$block_markup = '<!-- wp:activitypub/reactions {"title":"What people think about it on the Fediverse!","postId":123} /-->';
		$output       = do_blocks( $block_markup );
		$expected     = '<div class="wp-block-activitypub-reactions is-layout-constrained wp-block-reactions-is-layout-constrained" data-attrs="{&quot;postId&quot;:123}"><h6 hidden class="wp-block-heading">What people think about it on the Fediverse!</h6>
<div class="activitypub-reactions-block"></div></div>';

		$this->assertSame( $expected, $output );

		$block_markup = '<!-- wp:activitypub/reactions {"postId":123} /-->';
		$output       = do_blocks( $block_markup );
		$expected     = '<div class="wp-block-activitypub-reactions is-layout-constrained wp-block-reactions-is-layout-constrained" data-attrs="{&quot;postId&quot;:123}"><h6 hidden class="wp-block-heading">Fediverse Reactions</h6>
<div class="activitypub-reactions-block"></div></div>';

		$this->assertSame( $expected, $output );

		// Reactions block with reactions.
		$post_id      = $this->get_post_id_with_reactions();
		$block_markup = sprintf( '<!-- wp:activitypub/reactions {"postId":%d} /-->', $post_id );
		$output       = do_blocks( $block_markup );
		$expected     = '<div class="wp-block-activitypub-reactions is-layout-constrained wp-block-reactions-is-layout-constrained" data-attrs="{&quot;postId&quot;:' . $post_id . '}"><h6 class="wp-block-heading">Fediverse Reactions</h6>
<div class="activitypub-reactions-block"></div></div>';

		$this->assertSame( $expected, $output );
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
		add_filter(
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

		Interactions::add_reaction( $activity );

		// Clean up.
		remove_all_filters( 'pre_get_remote_metadata_by_actor' );

		return $post_id;
	}
}
