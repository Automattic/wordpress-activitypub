<?php
/**
 * Test file for Sanitize class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Model\Blog;
use Activitypub\Sanitize;

/**
 * Test class for Sanitize.
 *
 * @coversDefaultClass \Activitypub\Sanitize
 */
class Test_Sanitize extends \WP_UnitTestCase {

	/**
	 * Data provider for URL list tests.
	 *
	 * @return array Test data.
	 */
	public function url_list_provider() {
		return array(
			'duplicate_urls'                  => array(
				array(
					'https://example.com',
					'https://example.com',
					'not-a-url',
					'https://wordpress.org',
				),
				array(
					'https://example.com',
					'http://not-a-url',
					'https://wordpress.org',
				),
			),
			'mixed_urls_in_string_whitespace' => array(
				"https://example.com\nnot-a-url\nhttps://wordpress.org  ",
				array(
					'https://example.com',
					'http://not-a-url',
					'https://wordpress.org',
				),
			),
			'special_characters'              => array(
				array(
					'https://example.com/path with spaces ',
					'https://example.com/über/path',
					'https://example.com/path?param=value&param2=value2#section',
				),
				array(
					'https://example.com/path%20with%20spaces',
					'https://example.com/über/path',
					'https://example.com/path?param=value&param2=value2#section',
				),
			),
			'empty_array'                     => array( array(), array() ),
			'unsupported'                     => array(
				array(
					'',
					false,
					null,
				),
				array(),
			),
		);
	}

	/**
	 * Test url_list with various inputs.
	 *
	 * @dataProvider url_list_provider
	 * @covers ::url_list
	 *
	 * @param mixed $input    Input value.
	 * @param array $expected Expected output.
	 */
	public function test_url_list( $input, $expected ) {
		$this->assertEquals( $expected, Sanitize::url_list( $input ) );
	}

	/**
	 * Data provider for host list tests.
	 *
	 * @return array Test data.
	 */
	public function host_list_provider() {
		return array(
			'single_valid_host'    => array(
				'example.com',
				'example.com',
			),
			'multiple_valid_hosts' => array(
				"ftp://example.com\nhttp://wordpress.org\nhttps://test.example.com",
				"example.com\nwordpress.org\ntest.example.com",
			),
			'mixed_case_hosts'     => array(
				"ExAmPlE.cOm\nWoRdPrEsS.oRg",
				"example.com\nwordpress.org",
			),
			'invalid_hosts'        => array(
				"   not-a-domain\n\nexample.com\n\t@invalid.com",
				"not-a-domain\nexample.com\ninvalid.com",
			),
			'empty_string'         => array(
				'',
				'',
			),
		);
	}

	/**
	 * Test host_list with various inputs.
	 *
	 * @dataProvider host_list_provider
	 * @covers ::host_list
	 *
	 * @param string $input    Input value.
	 * @param string $expected Expected output.
	 */
	public function test_host_list( $input, $expected ) {
		$this->assertEquals( $expected, Sanitize::host_list( $input ) );
	}

	/**
	 * Data provider for blog identifier tests.
	 *
	 * @return array Test data.
	 */
	public function blog_identifier_provider() {
		return array(
			'simple_string' => array( 'test-Blog', 'test-blog' ),
			'with_spaces'   => array( 'test blog', 'test-blog' ),
			'with_dots'     => array( 'test.blog', 'test.blog' ),
			'special_chars' => array( 'test@#$%^&*blog', 'testblog' ),
			'multiple_dots' => array( 'test.blog.name', 'test.blog.name' ),
			'empty_string'  => array( '', Blog::get_default_username() ),
		);
	}

	/**
	 * Test blog_identifier with various inputs.
	 *
	 * @dataProvider blog_identifier_provider
	 * @covers ::blog_identifier
	 *
	 * @param string $input    Input value.
	 * @param string $expected Expected output.
	 */
	public function test_blog_identifier( $input, $expected ) {
		$this->assertEquals( $expected, Sanitize::blog_identifier( $input ) );
	}

	/**
	 * Test blog_identifier with an existing username.
	 *
	 * @covers ::blog_identifier
	 */
	public function test_blog_identifier_with_existing_user() {
		$user_id = self::factory()->user->create(
			array(
				'user_login'    => 'existing-user',
				'user_nicename' => 'test-nicename',
			)
		);

		$result = Sanitize::blog_identifier( 'existing-user' );

		$this->assertEquals( \Activitypub\Model\Blog::get_default_username(), $result );
		$this->assertNotEmpty( get_settings_errors( 'activitypub_blog_identifier' ) );

		// Reset.
		$GLOBALS['wp_settings_errors'] = array();

		$result = Sanitize::blog_identifier( 'test-nicename' );

		$this->assertEquals( \Activitypub\Model\Blog::get_default_username(), $result );
		$this->assertNotEmpty( get_settings_errors( 'activitypub_blog_identifier' ) );

		\wp_delete_user( $user_id );
	}

	/**
	 * Test content sanitization without blocks support.
	 *
	 * @covers ::content
	 */
	public function test_content_without_blocks() {
		// Mock site_supports_blocks to return false.
		add_filter( 'activitypub_site_supports_blocks', '__return_false' );

		$content = '<h1>Test Heading</h1><p>Test paragraph</p>';
		$result  = Sanitize::content( $content );

		// Should not convert to blocks when blocks are not supported.
		$this->assertStringNotContainsString( '<!-- wp:', $result );
		$this->assertStringContainsString( '<h1>Test Heading</h1>', $result );
		$this->assertStringContainsString( '<p>Test paragraph</p>', $result );

		remove_filter( 'activitypub_site_supports_blocks', '__return_false' );
	}

	/**
	 * Test content sanitization with malicious content.
	 *
	 * @covers ::content
	 */
	public function test_content_security() {
		$malicious_content = '<p>Safe content</p><script>alert("XSS")</script><iframe src="evil.com"></iframe>';
		$result            = Sanitize::content( $malicious_content );

		$this->assertStringContainsString( 'Safe content', $result );
		$this->assertStringNotContainsString( 'script', $result );
		$this->assertStringNotContainsString( 'iframe', $result );
		$this->assertStringNotContainsString( 'evil.com', $result );
	}

	/**
	 * Test content sanitization with URLs.
	 *
	 * @covers ::content
	 */
	public function test_content_urls() {
		$content = 'Visit https://example.com for more info';
		$result  = Sanitize::content( $content );

		// Should make URLs clickable.
		$this->assertStringContainsString( '<a href="https://example.com"', $result );
	}

	/**
	 * Test content sanitization preserves existing links with Mastodon-style spans.
	 *
	 * @covers ::content
	 */
	public function test_content_preserves_existing_links() {
		$content = '<p><a href="https://www.example.com/path/to/article?param=value&amp;utm_source=mastodon" target="_blank" rel="nofollow noopener" translate="no"><span class="invisible">https://www.</span><span class="ellipsis">example.com/path/to/art</span><span class="invisible">icle?param=value&amp;utm_source=mastodon</span></a></p>';
		$result  = Sanitize::content( $content );

		// Should preserve existing link structure without double-linking.
		$this->assertSame( 1, \substr_count( $result, '<a ' ), 'Should have exactly one anchor tag' );
		$this->assertStringContainsString( 'href="https://www.example.com/path/', $result );
	}

	/**
	 * Test content sanitization with empty content.
	 *
	 * @covers ::content
	 */
	public function test_content_empty() {
		$this->assertEquals( '', Sanitize::content( '' ) );
		// Whitespace-only content gets processed and becomes empty.
		$this->assertEquals( '', Sanitize::content( '   ' ) );
	}

	/**
	 * Test content sanitization preserves safe HTML.
	 *
	 * @covers ::content
	 */
	public function test_content_preserves_safe_html() {
		$content = '<p><strong>Bold</strong> and <em>italic</em> text</p>';
		$result  = Sanitize::content( $content );

		$this->assertStringContainsString( '<strong>Bold</strong>', $result );
		$this->assertStringContainsString( '<em>italic</em>', $result );
	}
}
