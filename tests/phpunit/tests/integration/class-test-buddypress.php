<?php
/**
 * Test BuddyPress integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Integration\Buddypress;

/**
 * Test BuddyPress integration.
 *
 * @group integration
 * @coversDefaultClass \Activitypub\Integration\Buddypress
 */
class Test_Buddypress extends \WP_UnitTestCase {
	/**
	 * Test that escape_at_signs encodes @ in data-wp-context attributes.
	 *
	 * @covers ::escape_at_signs
	 */
	public function test_escape_at_signs_in_context() {
		$input  = '<div data-wp-context="{&quot;handle&quot;:&quot;@alice@mastodon.social&quot;}">';
		$result = Buddypress::escape_at_signs( $input );

		$this->assertStringNotContainsString( '@', $this->extract_context( $result ) );
		$this->assertStringContainsString( '&#x40;alice&#x40;mastodon.social', $result );
	}

	/**
	 * Test that escape_at_signs does not affect content outside data-wp-context.
	 *
	 * @covers ::escape_at_signs
	 */
	public function test_escape_at_signs_preserves_other_content() {
		$input  = '<div data-wp-context="{&quot;handle&quot;:&quot;@bob@pixelfed.social&quot;}"><span>@bob@pixelfed.social</span></div>';
		$result = Buddypress::escape_at_signs( $input );

		// @ inside data-wp-context should be escaped.
		$this->assertStringContainsString( '&#x40;bob&#x40;pixelfed.social', $result );

		// @ outside data-wp-context should be untouched.
		$this->assertStringContainsString( '<span>@bob@pixelfed.social</span>', $result );
	}

	/**
	 * Test that escape_at_signs handles content without data-wp-context.
	 *
	 * @covers ::escape_at_signs
	 */
	public function test_escape_at_signs_no_context() {
		$input  = '<p>Hello @world</p>';
		$result = Buddypress::escape_at_signs( $input );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test that escape_at_signs handles multiple items in context.
	 *
	 * @covers ::escape_at_signs
	 */
	public function test_escape_at_signs_multiple_handles() {
		$context = \wp_json_encode(
			array(
				'items' => array(
					array( 'handle' => '@alice@mastodon.social' ),
					array( 'handle' => '@bob@pixelfed.social' ),
				),
			),
			JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
		);

		$input  = '<div data-wp-context="' . \esc_attr( $context ) . '"></div>';
		$result = Buddypress::escape_at_signs( $input );

		$this->assertStringNotContainsString( '@alice', $this->extract_context( $result ) );
		$this->assertStringNotContainsString( '@bob', $this->extract_context( $result ) );
		$this->assertStringContainsString( '&#x40;alice', $result );
		$this->assertStringContainsString( '&#x40;bob', $result );
	}

	/**
	 * Extract the data-wp-context attribute value from HTML.
	 *
	 * @param string $html The HTML to extract from.
	 *
	 * @return string The attribute value, or empty string if not found.
	 */
	private function extract_context( $html ) {
		if ( \preg_match( '/data-wp-context="([^"]*)"/', $html, $matches ) ) {
			return $matches[1];
		}

		return '';
	}
}
