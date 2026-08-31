<?php
/**
 * Test file for Icons class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Icons;

/**
 * Test class for Icons.
 *
 * @coversDefaultClass \Activitypub\Icons
 */
class Test_Icons extends \WP_UnitTestCase {

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! \function_exists( 'wp_register_icon' ) ) {
			$this->markTestSkipped( 'The Icons API is not available in this WordPress version.' );
		}
	}

	/**
	 * The icons registered on init are available through the Icons API.
	 *
	 * @covers ::register_icons
	 */
	public function test_icons_are_registered() {
		$icons = array( 'activitypub/fediverse', 'activitypub/fediverse-symbol', 'activitypub/activitypub' );

		foreach ( $icons as $icon ) {
			$svg = \wp_get_icon( $icon );

			$this->assertStringStartsWith( '<svg', $svg, "Icon {$icon} is not registered." );
			$this->assertStringContainsString( '<path', $svg, "Icon {$icon} has no path data." );
		}
	}

	/**
	 * The Fediverse logo keeps its brand colors through the sanitizer.
	 *
	 * @covers ::register_icons
	 */
	public function test_fediverse_logo_keeps_colors() {
		$this->assertStringContainsString( 'fill="#a730b8"', \wp_get_icon( 'activitypub/fediverse' ) );
	}

	/**
	 * The monochrome icons carry no hardcoded fill, so they inherit the surrounding color.
	 *
	 * @covers ::register_icons
	 */
	public function test_monochrome_icons_inherit_color() {
		$this->assertStringNotContainsString( 'fill=', \wp_get_icon( 'activitypub/fediverse-symbol' ) );
		$this->assertStringNotContainsString( 'fill=', \wp_get_icon( 'activitypub/activitypub' ) );
	}

	/**
	 * Registering again is a no-op instead of a fatal or a warning storm.
	 *
	 * @covers ::register_icons
	 */
	public function test_reregistering_is_flagged_as_doing_it_wrong() {
		$this->setExpectedIncorrectUsage( 'WP_Icon_Collections_Registry::register' );
		$this->setExpectedIncorrectUsage( 'WP_Icons_Registry::register' );

		Icons::register_icons();
	}
}
