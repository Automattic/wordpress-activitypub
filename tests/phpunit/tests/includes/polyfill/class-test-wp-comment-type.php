<?php
/**
 * Test file for the WP_Comment_Type polyfill.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Polyfill;

/**
 * Test class for the WP_Comment_Type polyfill.
 *
 * The class mirrors core's; these pin the properties the plugin relies on.
 */
class Test_WP_Comment_Type extends \WP_UnitTestCase {

	/**
	 * Every registration arg is copied onto the object, including plugin-specific ones.
	 */
	public function test_registration_args_become_properties() {
		$type = new \WP_Comment_Type(
			'probe',
			array(
				'label'          => 'Probes',
				'icon'           => '🔬',
				'activity_types' => array( 'probe' ),
			)
		);

		$this->assertSame( 'probe', $type->name );
		$this->assertSame( 'Probes', $type->label );
		$this->assertSame( '🔬', $type->icon );
		$this->assertSame( array( 'probe' ), $type->activity_types );
	}

	/**
	 * Defaults match core: public, not internal, not built-in, never hierarchical.
	 */
	public function test_defaults() {
		$type = new \WP_Comment_Type( 'probe' );

		$this->assertTrue( $type->public );
		$this->assertFalse( $type->internal );
		$this->assertFalse( $type->_builtin );
		$this->assertFalse( $type->hierarchical );
		$this->assertSame( 'Comments', $type->labels->name, 'Falls back to the default label.' );
	}

	/**
	 * A bare `label` becomes the plural name, as register_post_type() treats it.
	 */
	public function test_label_becomes_the_name_label() {
		$type = new \WP_Comment_Type( 'probe', array( 'label' => 'Probes' ) );

		$this->assertSame( 'Probes', $type->labels->name );
		$this->assertSame( 'Probes', $type->labels->menu_name );
	}
}
