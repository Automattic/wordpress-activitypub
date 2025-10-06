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
}
