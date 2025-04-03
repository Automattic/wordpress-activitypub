<?php
/**
 * Test file for Activitypub Blog Model.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Model;

use Activitypub\Model\Blog;
use Activitypub\Move;

/**
 * Test class for Activitypub Blog Model.
 *
 * @coversDefaultClass Blog
 */
class Test_Blog extends \WP_UnitTestCase {

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		// Enable blog actor.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
	}

	/**
	 * Tear down after class.
	 */
	public static function tear_down_after_class() {
		// Disable blog actor.
		\delete_option( 'activitypub_actor_mode' );

		parent::tear_down_after_class();
	}

	/**
	 * Test the Blog constructor.
	 *
	 * @covers ::__construct
	 */
	public function test___construct() {
		$old_domain = home_url( '/' );
		$new_domain = 'http://newdomain.com';
		\remove_filter( 'option_home', '_config_wp_home' );

		\add_filter( 'pre_update_option_home', array( Move::class, 'pre_update_option_home' ), 10, 2 );
		\update_option( 'home', $new_domain );
		\remove_filter( 'pre_update_option_home', array( Move::class, 'pre_update_option_home' ) );

		// New domain is set.
		$this->assertSame( 'http://newdomain.com/?author=0', ( new Blog() )->get_id() );

		// Set up the old domain.
		$_SERVER['HTTP_HOST'] = \wp_parse_url( $old_domain, PHP_URL_HOST );

		// Blog now returns old blog user.
		$blog = ( new Blog() )->to_array();
		$this->assertSame( add_query_arg( 'author', 0, $old_domain ), $blog['id'] );

		// Clean up.
		\delete_option( 'activitypub_old_domain' );
		\delete_option( 'activitypub_blog_user_old_domain_data' );
		\update_option( 'home', $old_domain );
		\add_filter( 'option_home', '_config_wp_home' );
	}
}
