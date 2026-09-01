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
	 * Test that the 'application' identifier is reserved for the Application actor.
	 *
	 * @covers ::blog_identifier
	 */
	public function test_blog_identifier_reserves_application() {
		$result = Sanitize::blog_identifier( 'application' );

		$this->assertEquals( \Activitypub\Model\Blog::get_default_username(), $result );
		$this->assertNotEmpty( get_settings_errors( 'activitypub_blog_identifier' ) );

		// Reset.
		$GLOBALS['wp_settings_errors'] = array();
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
	 * Test that inline styles are stripped from remote content.
	 *
	 * `wp_kses_post()` keeps the global `style` attribute, and core's
	 * `safecss_filter_attr()` allows `background-image:url()` for https targets.
	 * That is a way around the media cache, which exists so the browser never
	 * talks to a remote host while rendering federated content.
	 *
	 * @covers ::content
	 */
	public function test_content_strips_tracking_background_image() {
		$content = '<p style="background-image:url(https://remote.example/track?src=wp)">Hello</p>';
		$result  = Sanitize::content( $content );

		$this->assertStringContainsString( 'Hello', $result );
		$this->assertStringNotContainsString( 'style=', $result );
		$this->assertStringNotContainsString( 'remote.example/track', $result );
	}

	/**
	 * Test that CSS positioning cannot be used to cover the screen.
	 *
	 * A `position:fixed` element with a high `z-index` lets a remote actor put
	 * their own markup over the Reader in wp-admin.
	 *
	 * @covers ::content
	 */
	public function test_content_strips_positioning_overlay() {
		$content = '<div style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:99999;background-color:white">Session expired</div>';
		$result  = Sanitize::content( $content );

		$this->assertStringContainsString( 'Session expired', $result );
		$this->assertStringNotContainsString( 'position:fixed', $result );
		$this->assertStringNotContainsString( 'z-index', $result );
	}

	/**
	 * Test that dropping `style` does not take legitimate markup with it.
	 *
	 * Also pins `srcset`, which core's post allowlist does not carry. Inline images
	 * are rewrapped as `activitypub/image` blocks and the render callback only swaps
	 * the cached URL in for `src`, so a `srcset` candidate pointing somewhere else
	 * would be fetched from the remote host.
	 *
	 * @covers ::content
	 */
	public function test_content_keeps_formatting_without_style() {
		$content = '<p>See <a href="https://remote.example/post" rel="nofollow">this</a> and <img src="https://remote.example/a.png" srcset="https://remote.example/track.png 2x" alt="a picture" /></p><blockquote><p>quoted</p></blockquote>';
		$result  = Sanitize::content( $content );

		$this->assertStringContainsString( 'href="https://remote.example/post"', $result );
		$this->assertStringContainsString( 'rel="nofollow"', $result );
		$this->assertStringContainsString( 'src="https://remote.example/a.png"', $result );
		$this->assertStringContainsString( 'alt="a picture"', $result );
		$this->assertStringContainsString( '<blockquote>', $result );
		$this->assertStringNotContainsString( 'srcset', $result );
	}

	/**
	 * Test that remote content is held to the same interactive-element policy as outgoing content.
	 *
	 * @covers ::content
	 * @covers ::content
	 */
	public function test_content_strips_interactive_elements() {
		$content = '<p>Hello</p><button onclick="steal()">Press</button><dialog open>Modal</dialog><p>World</p>';
		$result  = Sanitize::content( $content );

		$this->assertStringNotContainsString( '<button', $result );
		$this->assertStringNotContainsString( 'Press', $result );
		$this->assertStringNotContainsString( '<dialog', $result );
		$this->assertStringNotContainsString( 'Modal', $result );
		$this->assertStringContainsString( 'Hello', $result );
		$this->assertStringContainsString( 'World', $result );
	}

	/**
	 * Test that plain-text cleaning keeps every character that is not markup.
	 *
	 * @dataProvider text_provider
	 * @covers ::text
	 *
	 * @param string $input    Input value.
	 * @param string $expected Expected output.
	 */
	public function test_text( $input, $expected ) {
		$this->assertSame( $expected, Sanitize::text( $input ) );
	}

	/**
	 * Data provider for text tests.
	 *
	 * The contract is narrow on purpose: no markup out, and nothing escaped or decoded on
	 * the way through, so the value is what a text sink should display.
	 *
	 * @return array Test data with input and expected output.
	 */
	public function text_provider() {
		return array(
			'plain text'          => array( 'Test User', 'Test User' ),
			// Known limitation of strip_tags(): a bare `<` reads as a tag that never closes.
			'bare less-than'      => array( 'A <3 shape carved in wood', 'A' ),
			// Left as characters, not entities, so a text sink can show them as typed.
			'ampersand'           => array( "Ben & Jerry's", "Ben & Jerry's" ),
			// Already-escaped markup stays inert, nothing decodes it back.
			'encoded markup'      => array( '&lt;script&gt;alert(1)&lt;/script&gt;x', '&lt;script&gt;alert(1)&lt;/script&gt;x' ),
			// sanitize_text_field() would drop the %20.
			'percent octet'       => array( 'foo%20bar', 'foo%20bar' ),
			'percent sign'        => array( '50% off', '50% off' ),
			'real tag'            => array( 'Photo<script>alert(1)</script>', 'Photo' ),
			'tag with attributes' => array( '<img src=x onerror=alert(1)>caption', 'caption' ),
			'empty'               => array( '', '' ),
			'non-string'          => array( array( 'nope' ), '' ),
		);
	}

	/**
	 * Data provider for clean_html tests.
	 *
	 * @return array Test data with input and expected output.
	 */
	public function clean_html_provider() {
		return array(
			'empty_string'                    => array( '', '' ),
			'removes_class_from_p'            => array(
				'<p class="wp-block-paragraph">Hello</p>',
				'<p>Hello</p>',
			),
			'preserves_class_on_a'            => array(
				'<a href="https://example.com" class="u-url mention">Link</a>',
				'<a href="https://example.com" class="u-url mention">Link</a>',
			),
			'removes_id'                      => array(
				'<span id="main-content">Content</span>',
				'<span>Content</span>',
			),
			'removes_style'                   => array(
				'<span style="color: red;">Styled</span>',
				'<span>Styled</span>',
			),
			'removes_data_attributes'         => array(
				'<span data-id="123" data-custom="value">Content</span>',
				'<span>Content</span>',
			),
			'strips_loading_decoding'         => array(
				'<img src="image.jpg" loading="lazy" decoding="async" alt="Test" />',
				'<img src="image.jpg" alt="Test" />',
			),
			'preserves_href'                  => array(
				'<a href="https://example.com">Link</a>',
				'<a href="https://example.com">Link</a>',
			),
			'strips_bad_protocol'             => array(
				'<a href="javascript:alert(1)">Link</a>',
				'<a href="alert(1)">Link</a>',
			),
			'preserves_img_essentials'        => array(
				'<img src="image.jpg" alt="Desc" width="300" height="200" />',
				'<img src="image.jpg" alt="Desc" width="300" height="200" />',
			),
			'preserves_title'                 => array(
				'<a href="https://example.com" title="Example">Link</a>',
				'<a href="https://example.com" title="Example">Link</a>',
			),
			'strips_target_from_a'            => array(
				'<a href="https://example.com" rel="me" target="_blank">Link</a>',
				'<a href="https://example.com" rel="me">Link</a>',
			),
			'strips_lang_dir'                 => array(
				'<p lang="en" dir="ltr">Hello</p>',
				'<p>Hello</p>',
			),
			'preserves_cite'                  => array(
				'<blockquote cite="https://example.com">Quote</blockquote>',
				'<blockquote cite="https://example.com">Quote</blockquote>',
			),
			'preserves_video_attrs'           => array(
				'<video src="video.mp4" width="640" height="360" controls poster="thumb.jpg"></video>',
				'<video src="video.mp4" width="640" height="360" controls poster="thumb.jpg"></video>',
			),
			'preserves_audio_attrs'           => array(
				'<audio src="audio.mp3" controls></audio>',
				'<audio src="audio.mp3" controls></audio>',
			),
			'strips_hreflang'                 => array(
				'<a href="https://example.de" hreflang="de">German</a>',
				'<a href="https://example.de">German</a>',
			),
			'strips_details_summary'          => array(
				'<details open><summary>Title</summary>Content</details>',
				'TitleContent',
			),
			'self_closing_tags'               => array(
				'<br class="clear" />',
				'<br />',
			),
			'no_attributes'                   => array(
				'<p>Simple paragraph</p>',
				'<p>Simple paragraph</p>',
			),
			'plain_text'                      => array(
				'Just plain text',
				'Just plain text',
			),
			'complex_wordpress_figure'        => array(
				'<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" width="1024" height="768" src="https://example.com/image.jpg" alt="Test" class="wp-image-123" data-id="123" /><figcaption class="wp-element-caption">Caption</figcaption></figure>',
				'<figure><img width="1024" height="768" src="https://example.com/image.jpg" alt="Test" /><figcaption>Caption</figcaption></figure>',
			),
			'strips_script_tags'              => array(
				'<p>Hello</p><script>alert("xss")</script><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_style_tags'               => array(
				'<p>Hello</p><style>.foo { color: red; }</style><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'preserves_encoded_script_in_pre' => array(
				'<pre><code>&lt;script&gt;alert("hello")&lt;/script&gt;</code></pre>',
				'<pre><code>&lt;script&gt;alert("hello")&lt;/script&gt;</code></pre>',
			),
			'strips_button_tags'              => array(
				'<p>Hello</p><button>Click me</button><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_nav_with_content'         => array(
				'<p>Hello</p><nav><a href="https://example.com">Link</a></nav><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_form_tags'                => array(
				'<p>Hello</p><form action="/submit"><input type="text" /></form><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_self_closing_input'       => array(
				'<p>Hello</p><input type="hidden" /><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_self_closing_embed'       => array(
				'<p>Hello</p><embed src="flash.swf" /><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_dialog_tags'              => array(
				'<p>Hello</p><dialog id="pop1" popover="hint">Copied HTML to 📋</dialog><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_open_dialog_tags'         => array(
				'<p>Hello</p><dialog open>Modal text</dialog><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_template_tags'            => array(
				'<p>Hello</p><template><p>Inert content</p></template><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_datalist_with_options'    => array(
				'<p>Hello</p><datalist id="browsers"><option value="Firefox">Firefox</option></datalist><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_stray_option_tags'        => array(
				'<p>Hello</p><option value="a">Choice A</option><optgroup label="Group"></optgroup><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_unclosed_option_tags'     => array(
				'<p>Hello</p><option value="a">Choice A<option value="b">Choice B<p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_unclosed_optgroup_tags'   => array(
				'<p>Hello</p><optgroup label="Group">Group label<p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'preserves_custom_element_text'   => array(
				'<p>Hello</p><option-picker>Pick one</option-picker><p>World</p>',
				'<p>Hello</p>Pick one<p>World</p>',
			),
			'strips_noscript_tags'            => array(
				'<p>Hello</p><noscript>Please enable JavaScript</noscript><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_canvas_tags'              => array(
				'<p>Hello</p><canvas width="300" height="150">Canvas not supported</canvas><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'strips_obsolete_embed_tags'      => array(
				'<p>Hello</p><applet code="A.class">Applet fallback</applet><noembed>No embed</noembed><noframes>No frames</noframes><p>World</p>',
				'<p>Hello</p><p>World</p>',
			),
			'preserves_mathml'                => array(
				'<math display="block"><mrow><msup><mi>x</mi><mn>2</mn></msup><mo>+</mo><mn>1</mn></mrow></math>',
				'<math display="block"><mrow><msup><mi>x</mi><mn>2</mn></msup><mo>+</mo><mn>1</mn></mrow></math>',
			),
			'preserves_mathml_dir'            => array(
				'<math dir="rtl"><mi>x</mi></math>',
				'<math dir="rtl"><mi>x</mi></math>',
			),
			'strips_annotation_xml'           => array(
				'<math><semantics><mi>x</mi><annotation encoding="application/x-tex">x</annotation><annotation-xml encoding="text/html"><span>x</span></annotation-xml></semantics></math>',
				'<math><semantics><mi>x</mi><annotation encoding="application/x-tex">x</annotation><span>x</span></semantics></math>',
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
