<?php
/**
 * Test file for Activitypub.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for Activitypub.
 *
 * @coversDefaultClass \Activitypub\Activitypub
 */
class Test_Activitypub extends \WP_UnitTestCase {

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
		$post_id = self::factory()->post->create();
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
	}
}
