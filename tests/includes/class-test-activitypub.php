<?php
/**
 * Test file for Activitypub.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Outbox;

/**
 * Test class for Activitypub.
 *
 * @coversDefaultClass \Activitypub\Activitypub
 */
class Test_Activitypub extends \WP_UnitTestCase {

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		\Activitypub\Activitypub::init();
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
	 * Test activitypub_preview_template filter.
	 *
	 * @covers ::render_activitypub_template
	 */
	public function test_preview_template_filter() {
		// Create a test post.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => 1,
			)
		);
		$this->go_to( get_permalink( $post_id ) );

		// Simulate ActivityPub request and preview mode.
		$_SERVER['HTTP_ACCEPT'] = 'application/activity+json';
		\set_query_var( 'preview', true );

		// Add filter before testing.
		\add_filter(
			'activitypub_preview_template',
			function () {
				return '/custom/template.php';
			}
		);

		// Test that the filter is applied.
		$template = \Activitypub\Activitypub::render_activitypub_template( 'original.php' );
		$this->assertEquals( '/custom/template.php', $template, 'Custom preview template should be used when filter is applied.' );

		// Clean up.
		unset( $_SERVER['HTTP_ACCEPT'] );
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test activity type meta sanitization.
	 *
	 * @dataProvider activity_meta_sanitization_provider
	 * @covers ::register_post_types
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

		wp_update_post(
			array(
				'ID'         => $post_id,
				'meta_input' => array( $meta_key => 'InvalidType' ),
			)
		);
		$this->assertEquals( $expected, \get_post_meta( $post_id, $meta_key, true ) );

		wp_delete_post( $post_id, true );
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
