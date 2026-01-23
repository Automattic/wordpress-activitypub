<?php
/**
 * Test file for Functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for Functions.
 *
 * @coversDefaultClass \Activitypub
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
}
