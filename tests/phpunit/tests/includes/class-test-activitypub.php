<?php
/**
 * Test file for Activitypub.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activitypub;
use Activitypub\Collection\Outbox;

/**
 * Test class for Activitypub.
 *
 * @coversDefaultClass \Activitypub\Activitypub
 */
class Test_Activitypub extends \WP_UnitTestCase {
	/**
	 * Test the default module gate.
	 *
	 * @covers \Activitypub\is_module_enabled
	 */
	public function test_modules_are_enabled_by_default() {
		$this->assertTrue( \Activitypub\is_module_enabled( 'runtime.router' ) );
		$this->assertTrue( \Activitypub\is_module_enabled( 'rest.inbox' ) );
	}

	/**
	 * Test selective module replacement.
	 *
	 * @covers \Activitypub\is_module_enabled
	 */
	public function test_module_gate_is_selective() {
		$filter = static function ( $enabled, $module ) {
			return 'runtime.router' === $module ? false : $enabled;
		};
		\add_filter( 'activitypub_module_enabled', $filter, 10, 2 );

		$this->assertFalse( \Activitypub\is_module_enabled( 'runtime.router' ) );
		$this->assertTrue( \Activitypub\is_module_enabled( 'runtime.signature' ) );

		\remove_filter( 'activitypub_module_enabled', $filter, 10 );
	}

	/**
	 * Test environment.
	 */
	public function test_test_env() {
		$this->assertEquals( 'production', \wp_get_environment_type() );
	}

	/**
	 * Test post type support.
	 *
	 * @covers ::init
	 */
	public function test_post_type_support() {
		\add_post_type_support( 'post', 'activitypub' );
		\add_post_type_support( 'page', 'activitypub' );

		$this->assertContains( 'post', \get_post_types_by_support( 'activitypub' ) );
		$this->assertContains( 'page', \get_post_types_by_support( 'activitypub' ) );
	}

	/**
	 * Test activity type meta sanitization.
	 *
	 * @dataProvider activity_meta_sanitization_provider
	 * @covers \Activitypub\Post_Types::register_outbox_post_type
	 *
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @param mixed  $expected   Expected value for invalid meta value.
	 */
	public function test_activity_meta_sanitization( $meta_key, $meta_value, $expected ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => Outbox::POST_TYPE,
				'meta_input' => array( $meta_key => $meta_value ),
			)
		);

		$this->assertEquals( $meta_value, \get_post_meta( $post_id, $meta_key, true ) );

		\wp_update_post(
			array(
				'ID'         => $post_id,
				'meta_input' => array( $meta_key => 'InvalidType' ),
			)
		);
		$this->assertEquals( $expected, \get_post_meta( $post_id, $meta_key, true ) );
	}

	/**
	 * Data provider for test_activity_meta_sanitization.
	 *
	 * @return array
	 */
	public function activity_meta_sanitization_provider() {
		return array(
			array( '_activitypub_activity_type', 'Create', 'Announce' ),
			array( '_activitypub_activity_actor', 'user', 'user' ),
			array( '_activitypub_activity_actor', 'blog', 'user' ),
		);
	}
}
