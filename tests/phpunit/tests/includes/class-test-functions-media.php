<?php
/**
 * Test file for Media Functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for Media Functions.
 *
 * @coversDefaultClass \Activitypub
 */
class Test_Functions_Media extends \WP_UnitTestCase {

	/**
	 * Test process_remote_images wraps remote images.
	 *
	 * @covers \Activitypub\process_remote_images
	 */
	public function test_process_remote_images_wraps_remote_images() {
		$content = '<p>Text</p><img src="https://remote.example.com/image.jpg" alt="Test" />';
		$result  = \Activitypub\process_remote_images( $content );

		$this->assertStringContainsString( '<!-- wp:activitypub/image', $result );
		$this->assertStringContainsString( 'https://remote.example.com/image.jpg', $result );
		$this->assertStringContainsString( '<!-- /wp:activitypub/image -->', $result );
	}

	/**
	 * Test process_remote_images handles different attribute orders.
	 *
	 * @covers \Activitypub\process_remote_images
	 */
	public function test_process_remote_images_attribute_order() {
		// Test with alt before src.
		$content = '<img alt="Test" src="https://remote.example.com/image.jpg" />';
		$result  = \Activitypub\process_remote_images( $content );

		$this->assertStringContainsString( '<!-- wp:activitypub/image', $result );
		$this->assertStringContainsString( 'https://remote.example.com/image.jpg', $result );

		// Test with multiple attributes before src.
		$content = '<img class="foo" alt="Test" width="100" src="https://remote.example.com/image2.jpg" height="50" />';
		$result  = \Activitypub\process_remote_images( $content );

		$this->assertStringContainsString( '<!-- wp:activitypub/image', $result );
		$this->assertStringContainsString( 'https://remote.example.com/image2.jpg', $result );
	}

	/**
	 * Test process_remote_images does not double-wrap already wrapped images.
	 *
	 * @covers \Activitypub\process_remote_images
	 */
	public function test_process_remote_images_no_double_wrapping() {
		$content = '<!-- wp:activitypub/image {"url":"https://remote.example.com/image.jpg"} --><img src="https://remote.example.com/image.jpg" alt="Test" /><!-- /wp:activitypub/image -->';
		$result  = \Activitypub\process_remote_images( $content );

		// Count occurrences of the opening block comment.
		$count = substr_count( $result, '<!-- wp:activitypub/image' );
		$this->assertSame( 1, $count, 'Should not wrap already-wrapped images' );
	}

	/**
	 * Test process_remote_images does not wrap local images.
	 *
	 * @covers \Activitypub\process_remote_images
	 */
	public function test_process_remote_images_skips_local_images() {
		$content = '<img src="' . home_url( '/image.jpg' ) . '" alt="Local" />';
		$result  = \Activitypub\process_remote_images( $content );

		$this->assertStringNotContainsString( '<!-- wp:activitypub/image', $result );
	}

	/**
	 * Test process_remote_images appends attachments not in content.
	 *
	 * @covers \Activitypub\process_remote_images
	 */
	public function test_process_remote_images_appends_attachments() {
		$content     = '<p>Just text</p>';
		$attachments = array(
			array(
				'url' => 'https://remote.example.com/attachment.jpg',
				'alt' => 'Attached Image',
			),
		);

		$result = \Activitypub\process_remote_images( $content, $attachments );

		$this->assertStringContainsString( '<!-- wp:activitypub/image', $result );
		$this->assertStringContainsString( 'https://remote.example.com/attachment.jpg', $result );
		$this->assertStringContainsString( 'alt="Attached Image"', $result );
	}

	/**
	 * Test process_remote_images does not duplicate attachment already in content.
	 *
	 * @covers \Activitypub\process_remote_images
	 */
	public function test_process_remote_images_no_duplicate_attachments() {
		$url         = 'https://remote.example.com/image.jpg';
		$content     = '<p>Text</p><img src="' . $url . '" alt="Inline" />';
		$attachments = array(
			array(
				'url' => $url,
				'alt' => 'Same Image',
			),
		);

		$result = \Activitypub\process_remote_images( $content, $attachments );

		// Count img tags - should be exactly 1.
		$img_count = \preg_match_all( '/<img\s/', $result );
		$this->assertSame( 1, $img_count, 'Should have exactly one img tag' );
	}

	/**
	 * Test process_remote_images handles empty alt text.
	 *
	 * @covers \Activitypub\process_remote_images
	 */
	public function test_process_remote_images_empty_alt() {
		$attachments = array(
			array(
				'url' => 'https://remote.example.com/image.jpg',
				'alt' => '',
			),
		);

		$result = \Activitypub\process_remote_images( '', $attachments );

		// Should not have alt="" attribute when alt is empty.
		$this->assertStringNotContainsString( 'alt=""', $result );
		$this->assertStringContainsString( '<img src=', $result );
	}

	/**
	 * Test generate_image_block helper function.
	 *
	 * @covers \Activitypub\generate_image_block
	 */
	public function test_generate_image_block() {
		$url      = 'https://example.com/image.jpg';
		$img_html = '<img src="https://example.com/image.jpg" alt="Test" />';

		$result = \Activitypub\generate_image_block( $url, $img_html );

		$this->assertStringStartsWith( '<!-- wp:activitypub/image', $result );
		$this->assertStringEndsWith( '<!-- /wp:activitypub/image -->', $result );
		$this->assertStringContainsString( $img_html, $result );
		$this->assertStringContainsString( '"url":"https:\/\/example.com\/image.jpg"', $result );
	}

	/**
	 * Test generate_audio_block helper function.
	 *
	 * @covers \Activitypub\generate_audio_block
	 */
	public function test_generate_audio_block() {
		$url    = 'https://example.com/audio.mp3';
		$result = \Activitypub\generate_audio_block( $url );

		$this->assertStringStartsWith( '<!-- wp:activitypub/audio', $result );
		$this->assertStringEndsWith( '<!-- /wp:activitypub/audio -->', $result );
		$this->assertStringContainsString( '<figure class="wp-block-audio">', $result );
		$this->assertStringContainsString( '<audio controls src="' . $url . '"></audio>', $result );
		$this->assertStringContainsString( '"url":"https:\/\/example.com\/audio.mp3"', $result );
	}

	/**
	 * Test generate_video_block helper function.
	 *
	 * @covers \Activitypub\generate_video_block
	 */
	public function test_generate_video_block() {
		$url    = 'https://example.com/video.mp4';
		$result = \Activitypub\generate_video_block( $url );

		$this->assertStringStartsWith( '<!-- wp:activitypub/video', $result );
		$this->assertStringEndsWith( '<!-- /wp:activitypub/video -->', $result );
		$this->assertStringContainsString( '<figure class="wp-block-video">', $result );
		$this->assertStringContainsString( '<video controls src="' . $url . '"></video>', $result );
		$this->assertStringContainsString( '"url":"https:\/\/example.com\/video.mp4"', $result );
	}

	/**
	 * Test process_remote_media appends video attachments.
	 *
	 * @covers \Activitypub\process_remote_media
	 */
	public function test_process_remote_media_appends_video() {
		$content     = '<p>Text content</p>';
		$attachments = array(
			array(
				'url'  => 'https://remote.example.com/video.mp4',
				'alt'  => '',
				'type' => 'video',
			),
		);

		$result = \Activitypub\process_remote_media( $content, $attachments );

		$this->assertStringContainsString( '<!-- wp:activitypub/video', $result );
		$this->assertStringContainsString( 'https://remote.example.com/video.mp4', $result );
		$this->assertStringContainsString( '<!-- /wp:activitypub/video -->', $result );
	}

	/**
	 * Test process_remote_media appends audio attachments.
	 *
	 * @covers \Activitypub\process_remote_media
	 */
	public function test_process_remote_media_appends_audio() {
		$content     = '<p>Text content</p>';
		$attachments = array(
			array(
				'url'  => 'https://remote.example.com/audio.mp3',
				'alt'  => '',
				'type' => 'audio',
			),
		);

		$result = \Activitypub\process_remote_media( $content, $attachments );

		$this->assertStringContainsString( '<!-- wp:activitypub/audio', $result );
		$this->assertStringContainsString( 'https://remote.example.com/audio.mp3', $result );
		$this->assertStringContainsString( '<!-- /wp:activitypub/audio -->', $result );
	}

	/**
	 * Test process_remote_media handles mixed attachment types.
	 *
	 * @covers \Activitypub\process_remote_media
	 */
	public function test_process_remote_media_mixed_attachments() {
		$content     = '<p>Text content</p>';
		$attachments = array(
			array(
				'url'  => 'https://remote.example.com/image.jpg',
				'alt'  => 'An image',
				'type' => 'image',
			),
			array(
				'url'  => 'https://remote.example.com/video.mp4',
				'alt'  => '',
				'type' => 'video',
			),
			array(
				'url'  => 'https://remote.example.com/audio.mp3',
				'alt'  => '',
				'type' => 'audio',
			),
		);

		$result = \Activitypub\process_remote_media( $content, $attachments );

		// Image should be wrapped in image block.
		$this->assertStringContainsString( '<!-- wp:activitypub/image', $result );
		$this->assertStringContainsString( 'https://remote.example.com/image.jpg', $result );

		// Video should be in video block.
		$this->assertStringContainsString( '<!-- wp:activitypub/video', $result );
		$this->assertStringContainsString( 'https://remote.example.com/video.mp4', $result );

		// Audio should be in audio block.
		$this->assertStringContainsString( '<!-- wp:activitypub/audio', $result );
		$this->assertStringContainsString( 'https://remote.example.com/audio.mp3', $result );
	}

	/**
	 * Test process_remote_media deduplicates URLs already in content.
	 *
	 * @covers \Activitypub\process_remote_media
	 */
	public function test_process_remote_media_deduplicates() {
		$url     = 'https://remote.example.com/video.mp4';
		$content = '<p>Text with ' . $url . ' already</p>';

		$attachments = array(
			array(
				'url'  => $url,
				'alt'  => '',
				'type' => 'video',
			),
		);

		$result = \Activitypub\process_remote_media( $content, $attachments );

		// Should not append a video block since URL is already in content.
		$this->assertStringNotContainsString( '<!-- wp:activitypub/video', $result );
	}
}
