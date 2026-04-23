<?php
/**
 * Test file for Post Types.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Post_Types;

/**
 * Test class for Post Types.
 *
 * @coversDefaultClass \Activitypub\Post_Types
 */
class Test_Post_Types extends \WP_UnitTestCase {

	/**
	 * Set up test environment.
	 */
	public function set_up(): void {
		parent::set_up();
		Post_Types::init();
	}

	/**
	 * Tear down test environment.
	 *
	 * Restores the default post-type support state and option value so tests that
	 * mutate them don't leak into later runs.
	 */
	public function tear_down(): void {
		\delete_option( 'activitypub_support_post_types' );
		\add_post_type_support( 'post', 'activitypub' );
		parent::tear_down();
	}

	/**
	 * Test prevent_empty_post_meta method.
	 *
	 * @covers ::prevent_empty_post_meta
	 */
	public function test_prevent_empty_post_meta() {
		$post_id = self::factory()->post->create( array( 'post_author' => 1 ) );

		// Storing the default value should be prevented.
		\update_post_meta( $post_id, 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS );
		$this->assertFalse( \metadata_exists( 'post', $post_id, 'activitypub_max_image_attachments' ) );

		// Storing a non-default value should work.
		\update_post_meta( $post_id, 'activitypub_max_image_attachments', ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS + 3 );
		$this->assertTrue( \metadata_exists( 'post', $post_id, 'activitypub_max_image_attachments' ) );
		$this->assertEquals( ACTIVITYPUB_MAX_IMAGE_ATTACHMENTS + 3, \get_post_meta( $post_id, 'activitypub_max_image_attachments', true ) );

		\delete_post_meta( $post_id, 'activitypub_max_image_attachments' );
	}

	/**
	 * Test that the stored option drives registration when no filter is attached.
	 *
	 * @covers ::register_supported_post_type_feature
	 */
	public function test_registers_support_from_stored_option() {
		\register_post_type( 'guide', array( 'public' => true ) );
		\update_option( 'activitypub_support_post_types', array( 'guide' ) );

		\remove_post_type_support( 'post', 'activitypub' );
		Post_Types::register_supported_post_type_feature();

		$this->assertTrue( \post_type_supports( 'guide', 'activitypub' ) );
		$this->assertFalse( \post_type_supports( 'post', 'activitypub' ) );

		\remove_post_type_support( 'guide', 'activitypub' );
		\unregister_post_type( 'guide' );
	}

	/**
	 * Test that the filter receives the stored option value as its first argument.
	 *
	 * @covers ::register_supported_post_type_feature
	 */
	public function test_filter_receives_stored_option_value() {
		\update_option( 'activitypub_support_post_types', array( 'post', 'page' ) );

		$received = null;
		$filter   = static function ( $post_types ) use ( &$received ) {
			$received = $post_types;
			return $post_types;
		};
		\add_filter( 'activitypub_supported_post_types', $filter );

		Post_Types::register_supported_post_type_feature();

		$this->assertSame( array( 'post', 'page' ), $received );

		\remove_filter( 'activitypub_supported_post_types', $filter );
	}

	/**
	 * Test that the activitypub_supported_post_types filter adds post type support.
	 *
	 * @covers ::register_supported_post_type_feature
	 */
	public function test_supported_post_types_filter_adds_support() {
		\register_post_type( 'book', array( 'public' => true ) );

		$filter = static function ( $post_types ) {
			$post_types[] = 'book';
			return $post_types;
		};
		\add_filter( 'activitypub_supported_post_types', $filter );

		Post_Types::register_supported_post_type_feature();

		$this->assertTrue( \post_type_supports( 'book', 'activitypub' ) );

		\remove_filter( 'activitypub_supported_post_types', $filter );
		\remove_post_type_support( 'book', 'activitypub' );
		\unregister_post_type( 'book' );
	}

	/**
	 * Test that an empty array from the filter prevents support from being registered.
	 *
	 * The filter does not strip existing support; it controls what gets (re-)registered
	 * on the next call to `register_supported_post_type_feature()`.
	 *
	 * @covers ::register_supported_post_type_feature
	 */
	public function test_supported_post_types_filter_empty_array_skips_registration() {
		\update_option( 'activitypub_support_post_types', array( 'post' ) );

		$filter = static function () {
			return array();
		};
		\add_filter( 'activitypub_supported_post_types', $filter );

		\remove_post_type_support( 'post', 'activitypub' );
		Post_Types::register_supported_post_type_feature();

		$this->assertFalse( \post_type_supports( 'post', 'activitypub' ) );

		\remove_filter( 'activitypub_supported_post_types', $filter );
	}

	/**
	 * Test that a non-array filter return is coerced safely.
	 *
	 * Guards the `(array)` cast that protects against integrators returning null,
	 * scalars, or other non-array values — in those cases nothing should be registered.
	 *
	 * @covers ::register_supported_post_type_feature
	 */
	public function test_supported_post_types_filter_non_array_return() {
		\register_post_type( 'zine', array( 'public' => true ) );

		$filter = static function () {
			return null;
		};
		\add_filter( 'activitypub_supported_post_types', $filter );

		Post_Types::register_supported_post_type_feature();

		$this->assertFalse( \post_type_supports( 'zine', 'activitypub' ) );

		\remove_filter( 'activitypub_supported_post_types', $filter );
		\unregister_post_type( 'zine' );
	}
}
