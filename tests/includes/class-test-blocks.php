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
	 * Test the reply block with a Mastodon URL that has an embed.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_render_reply_block_with_mastodon_embed() {
		$mock_embed = '<div class="mastodon-embed">Mock Embed Content</div>';

		// Create a proper REST response object with the mock embed.
		$response = new \WP_REST_Response(
			(object) array(
				'html' => $mock_embed,
			)
		);

		$pre_dispatch_function = function ( $result, $server, $request ) use ( $response ) {
			if ( '/oembed/1.0/proxy' === $request->get_route() ) {
				return $response;
			}
			return $result;
		};

		// Mock the REST Server dispatch to return our response.
		add_filter( 'rest_pre_dispatch', $pre_dispatch_function, 10, 3 );

		$block_markup = '<!-- wp:activitypub/reply {"url":"https://mastodon.social/@user/123456","embedPost":true} /-->';
		$output       = do_blocks( $block_markup );

		remove_filter( 'rest_pre_dispatch', $pre_dispatch_function );

		$this->assertStringContainsString( $mock_embed, $output, 'Output should contain the embedded content.' );
		$this->assertStringContainsString( 'u-in-reply-to', $output, 'Output should still contain the reply link.' );
		$this->assertStringContainsString( 'mastodon.social/@user/123456', $output, 'Output should contain the Mastodon URL.' );
	}

	/**
	 * Test the reply block with a URL that has no available embed.
	 *
	 * @covers ::render_reply_block
	 */
	public function test_render_reply_block_with_no_embed() {
		add_filter( 'pre_oembed_result', '__return_false' );

		$block_markup = '<!-- wp:activitypub/reply {"url":"https://example.com/no-embed","embedPost":true} /-->';
		$output       = do_blocks( $block_markup );

		$this->assertStringNotContainsString( '<blockquote', $output, 'Output should not contain any embedded content.' );
		$this->assertStringContainsString( 'u-in-reply-to', $output, 'Output should contain the reply link.' );
		$this->assertStringContainsString( 'example.com/no-embed', $output, 'Output should contain the formatted URL.' );
	}
}
