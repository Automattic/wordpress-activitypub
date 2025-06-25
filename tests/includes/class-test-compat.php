<?php
/**
 * Test file for Activitypub Compat.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for Activitypub Compat.
 */
class Test_Compat extends \WP_UnitTestCase {
	/**
	 * Test str_starts_with.
	 *
	 * No coverage because it's a polyfill.
	 */
	public function test_str_starts_with() {
		$this->assertTrue( \str_starts_with( 'abc', 'ab' ) );
		$this->assertFalse( \str_starts_with( 'abc', 'bc' ) );
	}

	/**
	 * Test str_ends_with.
	 *
	 * No coverage because it's a polyfill.
	 */
	public function test_str_ends_with() {
		$this->assertTrue( \str_ends_with( 'abc', 'bc' ) );
		$this->assertFalse( \str_ends_with( 'abc', 'ab' ) );
	}
}
