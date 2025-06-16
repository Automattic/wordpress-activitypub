<?php
/**
 * Test the Embed class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Embed;

/**
 * Test the Embed class.
 *
 * @coversDefaultClass \ActivityPub\Embed
 */
class Test_Embed extends \WP_UnitTestCase {
	/**
	 * Test the has_real_oembed method with a URL that has a real oEmbed.
	 *
	 * @covers ::has_real_oembed
	 */
	public function test_has_real_oembed_with_real_oembed() {
		// Define the filter function.
		$iframe_filter = function () {
			return '<iframe src="https://example.com/embed"></iframe>';
		};

		// Add our filter.
		add_filter( 'pre_oembed_result', $iframe_filter, 9, 1 );

		// Call the method.
		$result = Embed::has_real_oembed( 'https://example.com/post' );

		// Remove our filter.
		remove_filter( 'pre_oembed_result', $iframe_filter, 9 );

		$this->assertTrue( $result );
	}

	/**
	 * Test the has_real_oembed method with a URL that doesn't have a real oEmbed.
	 *
	 * @covers ::has_real_oembed
	 */
	public function test_has_real_oembed_without_real_oembed() {
		// Add our filter.
		add_filter( 'pre_oembed_result', '__return_false', 9, 1 );

		// Call the method.
		$result = Embed::has_real_oembed( 'https://example.com/post' );

		// Remove our filter.
		remove_filter( 'pre_oembed_result', '__return_false', 9 );

		$this->assertFalse( $result );
	}

	/**
	 * Test the maybe_use_activitypub_embed method when a result is already provided.
	 *
	 * @covers ::maybe_use_activitypub_embed
	 */
	public function test_maybe_use_activitypub_embed_with_result() {
		// Call the method with a non-null result.
		$result = Embed::maybe_use_activitypub_embed( '<iframe src="https://example.com/embed"></iframe>', 'https://example.com/post', array() );

		$this->assertEquals( '<iframe src="https://example.com/embed"></iframe>', $result );
	}

	/**
	 * Test the maybe_use_activitypub_embed method when no result is provided but a real oEmbed is found.
	 *
	 * @covers ::maybe_use_activitypub_embed
	 */
	public function test_maybe_use_activitypub_embed_with_real_oembed() {
		// Create a test double for Embed that returns true for has_real_oembed.
		$embed = $this->getMockBuilder( Embed::class )
			->setMethods( array( 'has_real_oembed' ) )
			->getMock();

		$embed->method( 'has_real_oembed' )
			->willReturn( true );

		// Call the method.
		$result = $embed::maybe_use_activitypub_embed( null, 'https://example.com/post', array() );

		$this->assertNull( $result );
	}

	/**
	 * Test the handle_filtered_oembed_result method when HTML is already provided.
	 *
	 * @covers ::handle_filtered_oembed_result
	 */
	public function test_handle_filtered_oembed_result_with_html() {
		// Call the method with HTML already provided.
		$result = Embed::handle_filtered_oembed_result( '<iframe src="https://example.com/embed"></iframe>', (object) array(), 'https://example.com/post' );

		$this->assertEquals( '<iframe src="https://example.com/embed"></iframe>', $result );
	}

	/**
	 * Test the handle_filtered_oembed_result method when the data type is not rich or video.
	 *
	 * @covers ::handle_filtered_oembed_result
	 */
	public function test_handle_filtered_oembed_result_with_non_rich_data() {
		// Call the method with a non-rich data type.
		$result = Embed::handle_filtered_oembed_result(
			'',
			(object) array(
				'type' => 'photo',
			),
			'https://example.com/post'
		);

		$this->assertEquals( '', $result );
	}

	/**
	 * Test the handle_filtered_oembed_result method when there's no HTML in the data.
	 *
	 * @covers ::handle_filtered_oembed_result
	 */
	public function test_handle_filtered_oembed_result_without_html() {
		// Call the method with no HTML in the data.
		$result = Embed::handle_filtered_oembed_result(
			'',
			(object) array(
				'type' => 'rich',
			),
			'https://example.com/post'
		);

		$this->assertEquals( '', $result );
	}

	/**
	 * Test the get_html_for_object method.
	 *
	 * @covers ::get_html_for_object
	 */
	public function test_get_html_for_object() {
		// Create a test object.
		$object = array(
			'id'         => 'https://example.com/post',
			'url'        => 'https://example.com/post',
			'content'    => 'This is a test post.',
			'attachment' => array(
				array(
					'type'      => 'Document',
					'url'       => 'https://example.com/image1.jpg',
					'mediaType' => 'image/jpeg',
				),
				array(
					'type'      => 'Image',
					'url'       => 'https://example.com/image2.jpg',
					'mediaType' => 'image/jpeg',
				),
			),
		);

		// Call the method.
		$result = Embed::get_html_for_object( $object );

		$this->assertStringContainsString( 'https://example.com/image1.jpg', $result );
		$this->assertStringContainsString( 'https://example.com/image2.jpg', $result );
	}
}
