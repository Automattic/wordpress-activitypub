<?php
/**
 * Shared URI cases for collection save and lookup tests.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * URI fixtures used by the collection tests.
 */
trait Uri_Test_Cases {
	/**
	 * URLs containing individual special characters and combinations.
	 *
	 * @return array<string, string[]> Test cases.
	 */
	public function uri_provider() {
		return array(
			'plain'                      => array( 'https://example.com/uri-test/plain' ),
			'ampersand'                  => array( 'https://example.com/uri-test/?a=1&b=2' ),
			'multiple ampersands'        => array( 'https://example.com/uri-test/?a=1&b=2&c=3' ),
			'apostrophe'                 => array( "https://example.com/uri-test/o'brien" ),
			'apostrophe and ampersand'   => array( "https://example.com/uri-test/o'brien?a=1&b=2" ),
			'percent encoded characters' => array( 'https://example.com/uri-test/o%27brien?value=%26%22' ),
			'encoded and raw characters' => array( 'https://example.com/uri-test/o%27brien?value=%26&b=2#fragment' ),
			'literal named entity'       => array( 'https://example.com/uri-test/?a=1&amp;b=2' ),
			'literal numeric entity'     => array( 'https://example.com/uri-test/?a=1&#038;b=2' ),
		);
	}

	/**
	 * Build an actor fixture with the supplied identifier.
	 *
	 * @param string $uri Actor ID.
	 * @return array Actor data.
	 */
	protected function actor_for_uri( $uri ) {
		return array(
			'id'                => $uri,
			'type'              => 'Person',
			'url'               => $uri,
			'inbox'             => 'https://example.com/inbox',
			'preferredUsername' => 'uri-test',
		);
	}
}
