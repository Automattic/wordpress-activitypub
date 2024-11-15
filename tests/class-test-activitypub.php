<?php
/**
 * Test file for Activitypub.
 *
 * @package Activitypub
 */

/**
 * Test class for Activitypub.
 *
 * @coversDefaultClass \Activitypub\Activitypub
 */
class Test_Activitypub extends WP_UnitTestCase {

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
}
