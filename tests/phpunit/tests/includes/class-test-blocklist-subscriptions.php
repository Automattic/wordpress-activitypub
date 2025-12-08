<?php
/**
 * Test Blocklist_Subscriptions class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Blocklist_Subscriptions;

/**
 * Test Blocklist_Subscriptions class.
 *
 * @coversDefaultClass \Activitypub\Blocklist_Subscriptions
 */
class Test_Blocklist_Subscriptions extends \WP_UnitTestCase {

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		\delete_option( Blocklist_Subscriptions::OPTION_KEY );
		parent::tear_down();
	}

	/**
	 * Test get_all returns empty array when no subscriptions exist.
	 *
	 * @covers ::get_all
	 */
	public function test_get_all_returns_empty_array() {
		$subscriptions = Blocklist_Subscriptions::get_all();

		$this->assertIsArray( $subscriptions );
		$this->assertEmpty( $subscriptions );
	}

	/**
	 * Test get_all returns existing subscriptions.
	 *
	 * @covers ::get_all
	 */
	public function test_get_all_returns_subscriptions() {
		$expected = array(
			'https://example.com/blocklist.csv' => 1234567890,
			'https://test.org/list.csv'         => 1234567891,
		);
		\update_option( Blocklist_Subscriptions::OPTION_KEY, $expected );

		$subscriptions = Blocklist_Subscriptions::get_all();

		$this->assertSame( $expected, $subscriptions );
	}

	/**
	 * Test remove removes an existing subscription.
	 *
	 * @covers ::remove
	 */
	public function test_remove_existing_subscription() {
		$subscriptions = array(
			'https://example.com/blocklist.csv' => 1234567890,
			'https://test.org/list.csv'         => 1234567891,
		);
		\update_option( Blocklist_Subscriptions::OPTION_KEY, $subscriptions );

		$result = Blocklist_Subscriptions::remove( 'https://example.com/blocklist.csv' );

		$this->assertTrue( $result );
		$remaining = Blocklist_Subscriptions::get_all();
		$this->assertCount( 1, $remaining );
		$this->assertArrayNotHasKey( 'https://example.com/blocklist.csv', $remaining );
		$this->assertArrayHasKey( 'https://test.org/list.csv', $remaining );
	}

	/**
	 * Test remove returns false for non-existent subscription.
	 *
	 * @covers ::remove
	 */
	public function test_remove_nonexistent_subscription() {
		$result = Blocklist_Subscriptions::remove( 'https://nonexistent.com/list.csv' );

		$this->assertFalse( $result );
	}

	/**
	 * Test add returns false for empty URL.
	 *
	 * @covers ::add
	 */
	public function test_add_empty_url() {
		$result = Blocklist_Subscriptions::add( '' );

		$this->assertFalse( $result );
		$this->assertEmpty( Blocklist_Subscriptions::get_all() );
	}

	/**
	 * Test add returns false for invalid URL format.
	 *
	 * @covers ::add
	 */
	public function test_add_invalid_url_format() {
		// URL with spaces is invalid.
		$result = Blocklist_Subscriptions::add( 'https://invalid url with spaces.com' );

		$this->assertFalse( $result );
		$this->assertEmpty( Blocklist_Subscriptions::get_all() );
	}

	/**
	 * Test add returns true for already subscribed URL.
	 *
	 * @covers ::add
	 */
	public function test_add_already_subscribed() {
		$url = 'https://example.com/blocklist.csv';
		\update_option(
			Blocklist_Subscriptions::OPTION_KEY,
			array( $url => 1234567890 )
		);

		$result = Blocklist_Subscriptions::add( $url );

		$this->assertTrue( $result );
	}

	/**
	 * Test sync returns false when URL contains no valid domains.
	 *
	 * @covers ::sync
	 */
	public function test_sync_returns_false_for_no_valid_domains() {
		$filter_http_request = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => "<html>\n<head><title>Not a blocklist</title></head>\n<body>Hello World</body>\n</html>",
			);
		};
		\add_filter( 'pre_http_request', $filter_http_request );

		$result = Blocklist_Subscriptions::sync( 'https://example.com/not-a-blocklist.html' );

		\remove_filter( 'pre_http_request', $filter_http_request );

		$this->assertFalse( $result );
	}
}
