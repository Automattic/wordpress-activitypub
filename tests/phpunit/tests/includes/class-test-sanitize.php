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
		self::factory()->user->create(
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

	/**
	 * Data provider for strip_whitespace tests.
	 *
	 * @return array Test data with input and expected output.
	 */
	public function strip_whitespace_provider() {
		return array(
			'removes_newlines_between_tags'     => array(
				"<p>Hello</p>\n<p>World</p>",
				'<p>Hello</p><p>World</p>',
			),
			'removes_tabs_between_tags'         => array(
				"<p>Hello</p>\t\t<p>World</p>",
				'<p>Hello</p><p>World</p>',
			),
			'removes_carriage_returns'          => array(
				"<p>Hello</p>\r\n<p>World</p>",
				'<p>Hello</p><p>World</p>',
			),
			'removes_mixed_whitespace'          => array(
				"<div>\n\t<p>Text</p>\n</div>",
				'<div><p>Text</p></div>',
			),
			'preserves_spaces_between_tags'     => array(
				'<span>Hello</span> <span>World</span>',
				'<span>Hello</span> <span>World</span>',
			),
			'preserves_whitespace_in_text'      => array(
				"<p>Hello\nWorld</p>",
				"<p>Hello\nWorld</p>",
			),
			'preserves_pre_content'             => array(
				"<pre>function test() {\n    return true;\n}</pre>",
				"<pre>function test() {\n    return true;\n}</pre>",
			),
			'preserves_code_content'            => array(
				"<code>const x = 1;\nconst y = 2;</code>",
				"<code>const x = 1;\nconst y = 2;</code>",
			),
			'complex_html_with_pre'             => array(
				"<p>Some text</p>\n<pre>code line 1\ncode line 2</pre>\n<p>More text</p>",
				"<p>Some text</p><pre>code line 1\ncode line 2</pre><p>More text</p>",
			),
			'trims_leading_trailing_whitespace' => array(
				"\n\n<p>Hello</p>\n\n",
				'<p>Hello</p>',
			),
			'empty_string'                      => array(
				'',
				'',
			),
			'whitespace_only'                   => array(
				"\n\t\r\n",
				'',
			),
			'nested_tags_with_whitespace'       => array(
				"<div>\n\t<ul>\n\t\t<li>Item</li>\n\t</ul>\n</div>",
				'<div><ul><li>Item</li></ul></div>',
			),
			'self_closing_hr_between_tags'      => array(
				"<p>Before</p>\n<hr />\n<p>After</p>",
				'<p>Before</p><hr /><p>After</p>',
			),
			'self_closing_br_between_tags'      => array(
				"<p>Line 1</p>\n<br>\n<p>Line 2</p>",
				'<p>Line 1</p><br><p>Line 2</p>',
			),
			'br_inside_paragraph'               => array(
				"<p>Line 1<br>\nLine 2</p>",
				"<p>Line 1<br>\nLine 2</p>",
			),
			'hr_with_xhtml_syntax'              => array(
				"<div>\n<hr/>\n</div>",
				'<div><hr/></div>',
			),
			'deeply_nested_divs'                => array(
				"<div>\n\t<div>\n\t\t<div>\n\t\t\t<p>Deep</p>\n\t\t</div>\n\t</div>\n</div>",
				'<div><div><div><p>Deep</p></div></div></div>',
			),
			'mixed_self_closing_and_nested'     => array(
				"<div>\n\t<p>Text</p>\n\t<hr />\n\t<p>More</p>\n</div>",
				'<div><p>Text</p><hr /><p>More</p></div>',
			),
			'img_self_closing'                  => array(
				"<p>Text</p>\n<img src=\"test.jpg\" />\n<p>More</p>",
				'<p>Text</p><img src="test.jpg" /><p>More</p>',
			),
			'preserves_spaces_with_newlines'    => array(
				"<p>Hello</p> \n <p>World</p>",
				"<p>Hello</p> \n <p>World</p>",
			),
			'preserves_space_after_newline'     => array(
				"<span>A</span>\n <span>B</span>",
				"<span>A</span>\n <span>B</span>",
			),
			'preserves_space_before_newline'    => array(
				"<span>A</span> \n<span>B</span>",
				"<span>A</span> \n<span>B</span>",
			),
			'multiple_spaces_with_newlines'     => array(
				"<div>A</div>  \n\t  <div>B</div>",
				"<div>A</div>  \n\t  <div>B</div>",
			),
		);
	}

	/**
	 * Test strip_whitespace with various inputs.
	 *
	 * @dataProvider strip_whitespace_provider
	 * @covers ::strip_whitespace
	 *
	 * @param string $input    Input value.
	 * @param string $expected Expected output.
	 */
	public function test_strip_whitespace( $input, $expected ) {
		$this->assertSame( $expected, Sanitize::strip_whitespace( $input ) );
	}

	/**
	 * Data provider for clean_html tests.
	 *
	 * @return array Test data with input and expected output.
	 */
	public function clean_html_provider() {
		return array(
			'empty_string'             => array( '', '' ),
			'removes_class_from_p'     => array(
				'<p class="wp-block-paragraph">Hello</p>',
				'<p>Hello</p>',
			),
			'preserves_class_on_a'     => array(
				'<a href="https://example.com" class="u-url mention">Link</a>',
				'<a href="https://example.com" class="u-url mention">Link</a>',
			),
			'removes_id'               => array(
				'<span id="main-content">Content</span>',
				'<span>Content</span>',
			),
			'removes_style'            => array(
				'<span style="color: red;">Styled</span>',
				'<span>Styled</span>',
			),
			'removes_data_attributes'  => array(
				'<span data-id="123" data-custom="value">Content</span>',
				'<span>Content</span>',
			),
			'strips_loading_decoding'  => array(
				'<img src="image.jpg" loading="lazy" decoding="async" alt="Test" />',
				'<img src="image.jpg" alt="Test" />',
			),
			'preserves_href'           => array(
				'<a href="https://example.com">Link</a>',
				'<a href="https://example.com">Link</a>',
			),
			'strips_bad_protocol'      => array(
				'<a href="javascript:alert(1)">Link</a>',
				'<a href="alert(1)">Link</a>',
			),
			'preserves_img_essentials' => array(
				'<img src="image.jpg" alt="Desc" width="300" height="200" />',
				'<img src="image.jpg" alt="Desc" width="300" height="200" />',
			),
			'preserves_title'          => array(
				'<a href="https://example.com" title="Example">Link</a>',
				'<a href="https://example.com" title="Example">Link</a>',
			),
			'preserves_rel_and_target' => array(
				'<a href="https://example.com" rel="me" target="_blank">Link</a>',
				'<a href="https://example.com" rel="me" target="_blank">Link</a>',
			),
			'strips_lang_dir'          => array(
				'<p lang="en" dir="ltr">Hello</p>',
				'<p>Hello</p>',
			),
			'preserves_cite'           => array(
				'<blockquote cite="https://example.com">Quote</blockquote>',
				'<blockquote cite="https://example.com">Quote</blockquote>',
			),
			'preserves_video_attrs'    => array(
				'<video src="video.mp4" width="640" height="360" controls poster="thumb.jpg"></video>',
				'<video src="video.mp4" width="640" height="360" controls poster="thumb.jpg"></video>',
			),
			'preserves_audio_attrs'    => array(
				'<audio src="audio.mp3" controls></audio>',
				'<audio src="audio.mp3" controls></audio>',
			),
			'strips_hreflang'          => array(
				'<a href="https://example.de" hreflang="de">German</a>',
				'<a href="https://example.de">German</a>',
			),
			'preserves_details_open'   => array(
				'<details open><summary>Title</summary></details>',
				'<details open><summary>Title</summary></details>',
			),
			'self_closing_tags'        => array(
				'<br class="clear" />',
				'<br />',
			),
			'no_attributes'            => array(
				'<p>Simple paragraph</p>',
				'<p>Simple paragraph</p>',
			),
			'plain_text'               => array(
				'Just plain text',
				'Just plain text',
			),
			'complex_wordpress_figure' => array(
				'<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1024" height="768" src="https://example.com/image.jpg" alt="Test" class="wp-image-123" data-id="123" /><figcaption class="wp-element-caption">Caption</figcaption></figure>',
				'<figure><img width="1024" height="768" src="https://example.com/image.jpg" alt="Test" /><figcaption>Caption</figcaption></figure>',
			),
		);
	}

	/**
	 * Test clean_html with various inputs.
	 *
	 * @dataProvider clean_html_provider
	 * @covers ::clean_html
	 *
	 * @param string $input    Input value.
	 * @param string $expected Expected output.
	 */
	public function test_clean_html( $input, $expected ) {
		$this->assertSame( $expected, Sanitize::clean_html( $input ) );
	}

	/**
	 * Test that null input returns null.
	 *
	 * @covers ::clean_html
	 */
	public function test_clean_html_null() {
		$this->assertNull( Sanitize::clean_html( null ) );
	}

	/**
	 * Test the activitypub_allowed_html filter.
	 *
	 * @covers ::clean_html
	 */
	public function test_allowed_html_filter() {
		$allowed_html_filter = static function ( $allowed_html ) {
			// Add data-custom attribute to span.
			$allowed_html['span']['data-custom'] = true;

			return $allowed_html;
		};
		\add_filter( 'activitypub_allowed_html', $allowed_html_filter );

		$input    = '<span data-custom="allowed" data-other="removed">Content</span>';
		$expected = '<span data-custom="allowed">Content</span>';
		$this->assertSame( $expected, Sanitize::clean_html( $input ) );

		\remove_filter( 'activitypub_allowed_html', $allowed_html_filter );
	}

	/**
	 * Test that rel attribute is preserved on anchors.
	 *
	 * @covers ::clean_html
	 */
	public function test_rel_attribute_preserved() {
		$input    = '<a href="https://example.com" rel="mention">Link</a>';
		$expected = '<a href="https://example.com" rel="mention">Link</a>';
		$this->assertSame( $expected, Sanitize::clean_html( $input ) );

		$input    = '<a href="https://example.com" rel="nofollow">Link</a>';
		$expected = '<a href="https://example.com" rel="nofollow">Link</a>';
		$this->assertSame( $expected, Sanitize::clean_html( $input ) );
	}

	/**
	 * Test redirect_uri preserves custom protocol schemes.
	 *
	 * @covers ::redirect_uri
	 */
	public function test_redirect_uri_custom_scheme() {
		$uri = 'com.example.app:/oauth/callback';
		$this->assertEquals( $uri, Sanitize::redirect_uri( $uri ) );

		$uri = 'myapp://callback?code=test';
		$this->assertEquals( $uri, Sanitize::redirect_uri( $uri ) );
	}

	/**
	 * Test redirect_uri handles standard schemes.
	 *
	 * @covers ::redirect_uri
	 */
	public function test_redirect_uri_standard_schemes() {
		$uri = 'https://example.com/callback';
		$this->assertEquals( $uri, Sanitize::redirect_uri( $uri ) );

		$uri = 'http://localhost:8080/callback';
		$this->assertEquals( $uri, Sanitize::redirect_uri( $uri ) );
	}

	/**
	 * Test redirect_uri returns empty for no scheme.
	 *
	 * @covers ::redirect_uri
	 */
	public function test_redirect_uri_no_scheme() {
		$this->assertEquals( '', Sanitize::redirect_uri( 'no-scheme' ) );
	}
}
