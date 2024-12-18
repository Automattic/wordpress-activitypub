<?php
/**
 * Test file for Blocks class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for Blocks.
 *
 * @coversDefaultClass \Activitypub\Blocks
 */
class Test_Blocks extends \WP_UnitTestCase {

	/**
	 * Test the render_reply_block() method with a valid URL attribute.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_render_reply_block_with_valid_url() {
		$attrs  = array( 'url' => 'https://example.com/post' );
		$output = \Activitypub\Blocks::render_reply_block( $attrs );
		$this->assertStringContainsString( 'u-in-reply-to', $output );
		$this->assertStringContainsString( 'https://example.com/post', $output );
		$this->assertStringContainsString( 'example.com/post', $output );
	}

	/**
	 * Test the render_reply_block() method with a missing URL attribute.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_render_reply_block_with_missing_url() {
		$attrs  = array();
		$output = \Activitypub\Blocks::render_reply_block( $attrs );
		$this->assertEmpty( $output );
	}

	/**
	 * Test the render_reply_block() method with an empty URL attribute.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_render_reply_block_with_empty_url() {
		$attrs  = array( 'url' => '' );
		$output = \Activitypub\Blocks::render_reply_block( $attrs );
		$this->assertEmpty( $output );
	}

	/**
	 * Test the render_reply_block() method with a Mastodon URL that has an embed.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_render_reply_block_with_mastodon_embed() {
		$mock_embed = '<div class="mastodon-embed">Mock Embed Content</div>';
		add_filter(
			'pre_oembed_result',
			function () use ( $mock_embed ) {
				return $mock_embed;
			}
		);

		$attrs  = array( 'url' => 'https://mastodon.social/@user/123456' );
		$output = \Activitypub\Blocks::render_reply_block( $attrs );

		$this->assertStringContainsString( $mock_embed, $output, 'Output should contain the embedded content.' );
		$this->assertStringContainsString( 'u-in-reply-to', $output, 'Output should still contain the reply link.' );
		$this->assertStringContainsString( 'mastodon.social/@user/123456', $output, 'Output should contain the formatted URL.' );
	}

	/**
	 * Test the render_reply_block() method with a URL that has no available embed.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_render_reply_block_with_no_embed() {
		add_filter( 'pre_oembed_result', '__return_false' );

		$attrs  = array( 'url' => 'https://example.com/no-embed' );
		$output = \Activitypub\Blocks::render_reply_block( $attrs );

		$this->assertStringNotContainsString( '<blockquote', $output, 'Output should not contain any embedded content.' );
		$this->assertStringContainsString( 'u-in-reply-to', $output, 'Output should contain the reply link.' );
		$this->assertStringContainsString( 'example.com/no-embed', $output, 'Output should contain the formatted URL.' );
	}
}
