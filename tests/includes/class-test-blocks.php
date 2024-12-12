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
}
