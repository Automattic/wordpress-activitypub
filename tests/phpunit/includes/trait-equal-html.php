<?php
/**
 * HTML comparison that ignores serialization details.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Compare HTML the way a browser parses it.
 *
 * WordPress 7.2 rewrites wp_kses() on the HTML API, which sorts attributes, drops the
 * self-closing slash of void elements and re-escapes character references. Comparing
 * the parsed trees keeps tests independent of that serialization.
 */
trait Equal_Html {
	/**
	 * Assert that two HTML strings are semantically equivalent.
	 *
	 * Falls back to an exact comparison on WordPress versions before 6.9, which have
	 * neither assertEqualHTML() nor the HTML-API-based wp_kses().
	 *
	 * @param string $expected The expected HTML.
	 * @param string $actual   The actual HTML.
	 * @param string $message  Optional. The assertion error message.
	 */
	public function assert_equal_html( $expected, $actual, $message = '' ) {
		if ( \method_exists( $this, 'assertEqualHTML' ) ) {
			$this->assertEqualHTML( $expected, $actual, '<body>', $message ? $message : 'HTML markup was not equivalent.' );
			return;
		}

		$this->assertSame( $expected, $actual, $message );
	}
}
