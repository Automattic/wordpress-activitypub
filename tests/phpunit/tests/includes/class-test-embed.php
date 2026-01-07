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

	/**
	 * Test get_html_for_object when author fetch returns WP_Error.
	 *
	 * This test ensures that the code handles WP_Error gracefully when fetching
	 * the author object fails, preventing fatal errors.
	 *
	 * @covers ::get_html_for_object
	 */
	public function test_get_html_for_object_with_author_fetch_error() {
		// Mock Http::get_remote_object to return WP_Error for the author URL.
		$filter = function ( $pre, $url_or_object ) {
			$url = \Activitypub\object_to_uri( $url_or_object );
			if ( 'https://example.com/author/1' === $url ) {
				return new \WP_Error( 'http_request_failed', 'Connection failed' );
			}
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		// Create a test object without avatar_url but with author URL (attributedTo).
		$object = array(
			'id'           => 'https://example.com/post/1',
			'url'          => 'https://example.com/post/1',
			'content'      => 'Test content when author fetch fails.',
			'attributedTo' => 'https://example.com/author/1',
		);

		// This should not throw a fatal error even when author fetch fails.
		$result = Embed::get_html_for_object( $object );

		// The result should still contain the content.
		$this->assertStringContainsString( 'Test content when author fetch fails.', $result );
		// The author URL should be used as webfinger fallback.
		$this->assertStringContainsString( 'https://example.com/author/1', $result );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}

	/**
	 * Test get_html_for_object when author fetch succeeds.
	 *
	 * This test ensures that author data (avatar, name, webfinger) is properly
	 * extracted when the remote author fetch succeeds.
	 *
	 * @covers ::get_html_for_object
	 */
	public function test_get_html_for_object_with_successful_author_fetch() {
		// Mock Http::get_remote_object to return author data.
		$filter = function ( $pre, $url_or_object ) {
			$url = \Activitypub\object_to_uri( $url_or_object );
			if ( 'https://example.com/author/2' === $url ) {
				return array(
					'id'                => 'https://example.com/author/2',
					'type'              => 'Person',
					'name'              => 'Test Author',
					'preferredUsername' => 'testauthor',
					'url'               => 'https://example.com/@testauthor',
					'icon'              => array(
						'type' => 'Image',
						'url'  => 'https://example.com/avatar.png',
					),
				);
			}
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		// Create a test object without avatar_url but with author URL.
		$object = array(
			'id'           => 'https://example.com/post/2',
			'url'          => 'https://example.com/post/2',
			'content'      => 'Test content with successful author fetch.',
			'attributedTo' => 'https://example.com/author/2',
		);

		$result = Embed::get_html_for_object( $object );

		// Should contain the fetched author name.
		$this->assertStringContainsString( 'Test Author', $result );
		// Should contain the fetched avatar URL.
		$this->assertStringContainsString( 'https://example.com/avatar.png', $result );
		// Should contain the constructed webfinger.
		$this->assertStringContainsString( '@testauthor@example.com', $result );
		// Should still contain the content.
		$this->assertStringContainsString( 'Test content with successful author fetch.', $result );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}

	/**
	 * Test get_html_for_object with existing avatar URL (no author fetch needed).
	 *
	 * When the activity object already has an avatar URL, no author fetch should occur.
	 *
	 * @covers ::get_html_for_object
	 */
	public function test_get_html_for_object_with_existing_avatar() {
		$fetch_called = false;

		// Mock to track if fetch is called.
		$filter = function ( $pre ) use ( &$fetch_called ) {
			$fetch_called = true;
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		// Create a test object with avatar_url already set.
		$object = array(
			'id'           => 'https://example.com/post/3',
			'url'          => 'https://example.com/post/3',
			'content'      => 'Test content with existing avatar.',
			'attributedTo' => 'https://example.com/author/3',
			'icon'         => array(
				'url' => 'https://example.com/existing-avatar.png',
			),
		);

		$result = Embed::get_html_for_object( $object );

		// Should contain the existing avatar URL.
		$this->assertStringContainsString( 'https://example.com/existing-avatar.png', $result );
		// Author fetch should not have been called since avatar already exists.
		$this->assertFalse( $fetch_called );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}

	/**
	 * Test get_html_for_object webfinger fallback when author has no preferredUsername.
	 *
	 * @covers ::get_html_for_object
	 */
	public function test_get_html_for_object_webfinger_fallback() {
		// Mock Http::get_remote_object to return author without preferredUsername.
		$filter = function ( $pre, $url_or_object ) {
			$url = \Activitypub\object_to_uri( $url_or_object );
			if ( 'https://example.com/author/4' === $url ) {
				return array(
					'id'   => 'https://example.com/author/4',
					'type' => 'Person',
					'name' => 'Author Without Username',
					'icon' => array(
						'type' => 'Image',
						'url'  => 'https://example.com/avatar4.png',
					),
					// No preferredUsername or url - webfinger should fallback.
				);
			}
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		$object = array(
			'id'           => 'https://example.com/post/4',
			'url'          => 'https://example.com/post/4',
			'content'      => 'Test webfinger fallback.',
			'attributedTo' => 'https://example.com/author/4',
		);

		$result = Embed::get_html_for_object( $object );

		// Should contain the content.
		$this->assertStringContainsString( 'Test webfinger fallback.', $result );
		// Should fallback to author_url for webfinger.
		$this->assertStringContainsString( 'https://example.com/author/4', $result );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}
}
