<?php
/**
 * Test file for Functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for Functions.
 */
class Test_Functions extends \WP_UnitTestCase {

	/**
	 * Test get_masked_wp_version function.
	 *
	 * @covers \Activitypub\get_masked_wp_version
	 * @dataProvider provide_wp_versions
	 *
	 * @param string $input    The input version.
	 * @param string $expected The expected masked version.
	 */
	public function test_get_masked_wp_version( $input, $expected ) {
		global $wp_version;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_version = $input;

		$this->assertEquals(
			$expected,
			\Activitypub\get_masked_wp_version(),
			sprintf( 'Version %s should be masked to %s', $input, $expected )
		);
	}

	/**
	 * Data provider for WordPress versions.
	 *
	 * @return array[] Array of test cases.
	 */
	public function provide_wp_versions() {
		return array(
			'standard version'                   => array(
				'6.4.2',
				'6.4',
			),
			'alpha version'                      => array(
				'6.4.2-alpha',
				'6.4',
			),
			'different alpha version'            => array(
				'6.4-alpha',
				'6.4',
			),
			'alpha version with patch'           => array(
				'6.4.2-alpha-59438',
				'6.4',
			),
			'different alpha version with patch' => array(
				'6.5-alpha-59438',
				'6.5',
			),
			'beta version'                       => array(
				'6.4.2-beta1',
				'6.4',
			),
			'RC version'                         => array(
				'6.4.2-RC1',
				'6.4',
			),
			'no patch version'                   => array(
				'6.4',
				'6.4',
			),
			'triple zero'                        => array(
				'6.0.0',
				'6.0',
			),
			'double digit'                       => array(
				'10.5',
				'10.5',
			),
			'single number'                      => array(
				'6',
				'6',
			),
		);
	}

	/**
	 * Data provider for camel to snake case and snake to camel case tests.
	 *
	 * @return array
	 */
	public function camel_snake_case_provider() {
		return array(
			'SimpleCamelCase'    => array( 'SimpleCamelCase', 'simple_camel_case' ),
			'camelCase'          => array( 'camelCase', 'camel_case' ),
			'XMLHttpRequest'     => array( 'XMLHttpRequest', 'x_m_l_http_request' ),
			'already_snake_case' => array( 'already_snake_case', 'already_snake_case' ),
			'with_numbers123'    => array( 'withNumbers123', 'with_numbers123' ),
			'leadingUpperCase'   => array( 'LeadingUpperCase', 'leading_upper_case' ),
			'singleletter'       => array( 'a', 'a' ),
			'emptyString'        => array( '', '' ),
			'nonStringInput'     => array( 12345, '12345' ),
			'CreateActivity'     => array( 'CreateActivity', 'create_activity' ),
			'Follow'             => array( 'Follow', 'follow' ),
			'QuoteRequest'       => array( 'QuoteRequest', 'quote_request' ),
		);
	}

	/**
	 * Test camel_to_snake_case function.
	 *
	 * @dataProvider camel_snake_case_provider
	 *
	 * @param string $original The original string.
	 * @param string $expected The expected result.
	 */
	public function test_camel_to_snake_case( $original, $expected ) {
		$this->assertSame( $expected, \Activitypub\camel_to_snake_case( $original ) );
	}

	/**
	 * Data provider for esc_hashtag tests.
	 *
	 * @return array Test cases with input and expected output.
	 */
	public function esc_hashtag_provider() {
		return array(
			'simple_word'              => array( 'test', '#test' ),
			'word_with_spaces'         => array( 'test tag', '#testTag' ),
			'multiple_spaces'          => array( 'test  multiple   spaces', '#testMultipleSpaces' ),
			'with_special_chars'       => array( 'test@tag!', '#testTag' ),
			'with_underscores'         => array( 'test_tag', '#testTag' ),
			'with_capitals'            => array( 'TestTag', '#TestTag' ),
			'with_capitals_underscore' => array( 'Test_Tag', '#TestTag' ),
			'with_leading_hashtag'     => array( '#test', '#Test' ),
			'with_multiple_hashtags'   => array( '##test', '#Test' ),
			'with_leading_hyphen'      => array( '-test', '#Test' ),
			'with_trailing_hyphen'     => array( 'test-', '#test' ),
			'with_leading_underscore'  => array( '_test', '#Test' ),
			'with_trailing_underscore' => array( 'test_', '#test' ),
			'mixed_case'               => array( 'TestTag', '#TestTag' ),
			'with_numbers'             => array( 'test123', '#test123' ),
			'with_unicode'             => array( 'tëst', '#tëst' ),
			'with_unicode_spaces'      => array( 'tëst tàg', '#tëstTàg' ),
			'german_umlauts'           => array( 'über straße', '#überStraße' ),
			'japanese_characters'      => array( 'テスト', '#テスト' ),
			'arabic_characters'        => array( 'اختبار', '#اختبار' ),
			'cyrillic_characters'      => array( 'тест', '#тест' ),
			'empty_string'             => array( '', '#' ),
			'only_spaces'              => array( '   ', '#' ),
			'only_special_chars'       => array( '@!#$%', '#' ),
			'hyphenated_words'         => array( 'foo-bar-baz', '#fooBarBaz' ),
			'quotes'                   => array( "test'tag", '#testTag' ),
			'double_quotes'            => array( 'test"tag', '#testTag' ),
			'ampersand'                => array( 'test&tag', '#testTag' ),
			'html_entities'            => array( 'test&amp;tag', '#testTag' ),
			'leading_trailing_spaces'  => array( '  test  ', '#Test' ),
			'multiple_hyphens'         => array( 'test--tag', '#testTag' ),
			'camelCase_preservation'   => array( 'testTag', '#testTag' ),
			'with_dots'                => array( 'test.tag', '#testTag' ),
			'with_commas'              => array( 'test,tag', '#testTag' ),
			'with_semicolons'          => array( 'test;tag', '#testTag' ),
			'with_slashes'             => array( 'test/tag', '#testTag' ),
			'with_backslashes'         => array( 'test\\tag', '#testTag' ),
			'with_parentheses'         => array( 'test(tag)', '#testTag' ),
			'with_brackets'            => array( 'test[tag]', '#testTag' ),
			'with_braces'              => array( 'test{tag}', '#testTag' ),
			'emoji_mixed'              => array( 'test 😀 tag', '#testTag' ),
			'chinese_characters'       => array( '测试 标签', '#测试标签' ),
			'korean_characters'        => array( '테스트 태그', '#테스트태그' ),
			'greek_characters'         => array( 'δοκιμή', '#δοκιμή' ),
			'hebrew_characters'        => array( 'בדיקה', '#בדיקה' ),
			'thai_characters'          => array( 'ทดสอบ', '#ทดสอบ' ),
		);
	}

	/**
	 * Test esc_hashtag function.
	 *
	 * @dataProvider esc_hashtag_provider
	 * @covers \Activitypub\esc_hashtag
	 *
	 * @param string $input    The input string.
	 * @param string $expected The expected hashtag output.
	 */
	public function test_esc_hashtag( $input, $expected ) {
		$result = \Activitypub\esc_hashtag( $input );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Test esc_hashtag filter hook.
	 *
	 * @covers \Activitypub\esc_hashtag
	 */
	public function test_esc_hashtag_filter() {
		$filter_callback = function ( $hashtag, $input ) {
			if ( 'custom' === $input ) {
				return '#CustomTag';
			}
			return $hashtag;
		};

		\add_filter( 'activitypub_esc_hashtag', $filter_callback, 10, 2 );

		$result = \Activitypub\esc_hashtag( 'custom' );
		$this->assertSame( '#CustomTag', $result );

		\remove_filter( 'activitypub_esc_hashtag', $filter_callback );
	}

	/**
	 * Test esc_hashtag with HTML special characters.
	 *
	 * @covers \Activitypub\esc_hashtag
	 */
	public function test_esc_hashtag_html_escaping() {
		$result = \Activitypub\esc_hashtag( '<script>alert("xss")</script>' );
		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( 'alert', $result );
		// The result should be HTML-escaped.
		$this->assertStringStartsWith( '#', $result );
	}

	/**
	 * Test esc_hashtag with quoted strings.
	 *
	 * @covers \Activitypub\esc_hashtag
	 */
	public function test_esc_hashtag_with_quotes() {
		// Test single quotes.
		$result = \Activitypub\esc_hashtag( "test's tag" );
		$this->assertSame( '#testSTag', $result );

		// Test double quotes.
		$result = \Activitypub\esc_hashtag( 'test"s tag' );
		$this->assertSame( '#testSTag', $result );

		// Test HTML entities for quotes.
		$result = \Activitypub\esc_hashtag( 'test&#039;s tag' );
		$this->assertSame( '#testSTag', $result );
	}

	/**
	 * Data provider for seconds_to_iso8601 tests.
	 *
	 * @return array Test cases with input seconds and expected ISO 8601 duration.
	 */
	public function seconds_to_iso8601_provider() {
		return array(
			'zero_seconds'          => array( 0, 'PT0S' ),
			'negative_seconds'      => array( -10, 'PT0S' ),
			'one_second'            => array( 1, 'PT1S' ),
			'thirty_seconds'        => array( 30, 'PT30S' ),
			'one_minute'            => array( 60, 'PT1M' ),
			'one_minute_30_seconds' => array( 90, 'PT1M30S' ),
			'five_minutes'          => array( 300, 'PT5M' ),
			'one_hour'              => array( 3600, 'PT1H' ),
			'one_hour_30_minutes'   => array( 5400, 'PT1H30M' ),
			'one_hour_one_second'   => array( 3601, 'PT1H1S' ),
			'full_duration'         => array( 3661, 'PT1H1M1S' ),
			'two_hours_15_min_30s'  => array( 8130, 'PT2H15M30S' ),
			'podcast_length'        => array( 2745, 'PT45M45S' ),
			'long_podcast'          => array( 7384, 'PT2H3M4S' ),
			'string_input'          => array( '3600', 'PT1H' ),
		);
	}

	/**
	 * Test seconds_to_iso8601 function.
	 *
	 * @dataProvider seconds_to_iso8601_provider
	 * @covers \Activitypub\seconds_to_iso8601
	 *
	 * @param int|string $seconds  The input seconds.
	 * @param string     $expected The expected ISO 8601 duration.
	 */
	public function test_seconds_to_iso8601( $seconds, $expected ) {
		$result = \Activitypub\seconds_to_iso8601( $seconds );
		$this->assertSame( $expected, $result );
	}

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
}
