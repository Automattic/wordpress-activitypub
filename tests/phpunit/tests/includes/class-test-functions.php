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
	 * Test get_object_id with a WP_Post.
	 *
	 * @covers \Activitypub\get_object_id
	 */
	public function test_get_object_id_with_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => 1,
				'post_status' => 'publish',
			)
		);

		$post   = \get_post( $post_id );
		$result = \Activitypub\get_object_id( $post );

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertSame( \Activitypub\get_post_id( $post_id ), $result );

		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test get_object_id with a WP_Comment.
	 *
	 * @covers \Activitypub\get_object_id
	 */
	public function test_get_object_id_with_comment() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
			)
		);

		$comment = \get_comment( $comment_id );
		$result  = \Activitypub\get_object_id( $comment );

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
		$this->assertSame( \Activitypub\get_comment_id( $comment ), $result );

		\wp_delete_comment( $comment_id, true );
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test get_object_id with unsupported type returns null.
	 *
	 * @covers \Activitypub\get_object_id
	 */
	public function test_get_object_id_with_unsupported_type() {
		$this->assertNull( \Activitypub\get_object_id( 'string' ) );
		$this->assertNull( \Activitypub\get_object_id( 42 ) );
		$this->assertNull( \Activitypub\get_object_id( null ) );
		$this->assertNull( \Activitypub\get_object_id( new \stdClass() ) );
	}

	/**
	 * Test get_client_ip returns REMOTE_ADDR by default.
	 *
	 * @covers \Activitypub\get_client_ip
	 */
	public function test_get_client_ip_default() {
		$_SERVER['REMOTE_ADDR'] = '192.168.1.1';
		$this->assertSame( '192.168.1.1', \Activitypub\get_client_ip() );
	}

	/**
	 * Test get_client_ip is filterable.
	 *
	 * @covers \Activitypub\get_client_ip
	 */
	public function test_get_client_ip_filter() {
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

		$filter = function () {
			return '203.0.113.50';
		};

		\add_filter( 'activitypub_client_ip', $filter );
		$this->assertSame( '203.0.113.50', \Activitypub\get_client_ip() );
		\remove_filter( 'activitypub_client_ip', $filter );
	}

	/**
	 * Test get_client_ip returns unknown when REMOTE_ADDR is missing.
	 *
	 * @covers \Activitypub\get_client_ip
	 */
	public function test_get_client_ip_missing_remote_addr() {
		unset( $_SERVER['REMOTE_ADDR'] );
		$this->assertSame( 'unknown', \Activitypub\get_client_ip() );
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
}
