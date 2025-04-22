<?php
/**
 * Test file for Blocks class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Blocks;
use WP_UnitTestCase;

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
}
