<?php
/**
 * Test OpenGraph integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Integration\Opengraph;

/**
 * Test the OpenGraph integration.
 *
 * @group integration
 * @coversDefaultClass \Activitypub\Integration\Opengraph
 */
class Test_Opengraph extends \WP_UnitTestCase {
	/**
	 * Set up: the bootstrap already ran `init()` with the default setting.
	 */
	public function set_up() {
		parent::set_up();

		\remove_filter( 'opengraph_metadata', array( Opengraph::class, 'add_metadata' ) );
		\remove_action( 'wp_head', array( Opengraph::class, 'add_meta_tags' ) );
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_use_opengraph' );

		parent::tear_down();
	}

	/**
	 * The metadata filter is hooked by default.
	 *
	 * @covers ::init
	 */
	public function test_init_hooks_metadata_by_default() {
		Opengraph::init();

		$this->assertNotFalse( \has_filter( 'opengraph_metadata', array( Opengraph::class, 'add_metadata' ) ) );
	}

	/**
	 * Nothing is hooked while the setting is off.
	 *
	 * The `init` action is registered unconditionally at `plugins_loaded`; the setting is
	 * read here, once the site context is settled.
	 *
	 * @covers ::init
	 */
	public function test_init_hooks_nothing_when_disabled() {
		\update_option( 'activitypub_use_opengraph', '0' );

		Opengraph::init();

		$this->assertFalse( \has_filter( 'opengraph_metadata', array( Opengraph::class, 'add_metadata' ) ) );
		$this->assertFalse( \has_action( 'wp_head', array( Opengraph::class, 'add_meta_tags' ) ) );
	}
}
