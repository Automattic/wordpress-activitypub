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
	 * Pairs of a stored ID and a different ID that the database collation treats as equal.
	 *
	 * @return array<string, string[]> Test cases: stored ID, requested ID.
	 */
	public function lookalike_uri_provider() {
		return array(
			'accent in host'          => array( 'https://bücher.example/uri-test/note', 'https://bucher.example/uri-test/note' ),
			'accent and case in host' => array( 'https://BÜCHER.example/uri-test/note', 'https://bucher.example/uri-test/note' ),
			'accent in path'          => array( 'https://example.com/uri-test/café', 'https://example.com/uri-test/cafe' ),
			'case in path'            => array( 'https://example.com/uri-test/Note', 'https://example.com/uri-test/note' ),
		);
	}

	/**
	 * Pairs of a stored handle and a different handle that the database collation treats as equal.
	 *
	 * @return array<string, string[]> Test cases: stored handle, requested handle.
	 */
	public function lookalike_acct_provider() {
		return array(
			'accent in host' => array( 'alice@bücher.example', 'alice@bucher.example' ),
			'accent in user' => array( 'älice@example.com', 'alice@example.com' ),
		);
	}

	/**
	 * Pairs of a stored handle and a handle that only differs in case, which is the same handle.
	 *
	 * @return array<string, string[]> Test cases: stored handle, requested handle.
	 */
	public function same_acct_provider() {
		return array(
			'case in user' => array( 'alice@example.com', 'Alice@example.com' ),
			'case in host' => array( 'alice@example.com', 'alice@Example.COM' ),
			'case of both' => array( 'Alice@Example.com', 'aLICE@example.COM' ),
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
