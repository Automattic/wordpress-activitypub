<?php
/**
 * HTML assertions compatible with the supported WordPress test suites.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Compare markup independently of attribute order and entity spelling.
 */
trait Html_Assertions {
	/**
	 * Assert that two HTML fragments are equivalent.
	 *
	 * @param string $expected Expected markup.
	 * @param string $actual   Actual markup.
	 */
	protected function assert_html_equals( $expected, $actual ) {
		if ( \method_exists( $this, 'assertEqualHTML' ) ) {
			$this->assertEqualHTML( $expected, $actual );
			return;
		}

		// WordPress before 6.9 has no HTML assertion; use libxml's parser and PHPUnit's XML comparison.
		$expected_document = new \DOMDocument();
		$actual_document   = new \DOMDocument();
		$expected_document->loadHTML( '<meta charset="utf-8">' . $expected, LIBXML_NOERROR | LIBXML_NOWARNING );
		$actual_document->loadHTML( '<meta charset="utf-8">' . $actual, LIBXML_NOERROR | LIBXML_NOWARNING );
		$this->assertXmlStringEqualsXmlString( $expected_document->saveXML(), $actual_document->saveXML() );
	}
}
