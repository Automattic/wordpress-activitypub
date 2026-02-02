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
 * @coversDefaultClass \Activitypub\Model\Blog
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

		\add_filter( 'update_option_home', array( Move::class, 'change_domain' ), 10, 2 );
		\update_option( 'home', $new_domain );
		\remove_filter( 'update_option_home', array( Move::class, 'change_domain' ) );

		// New domain is set.
		$this->assertSame( 'http://newdomain.com/?author=0', ( new Blog() )->get_id() );

		// Set up the old host.
		$_SERVER['HTTP_HOST'] = \wp_parse_url( $old_domain, PHP_URL_HOST );

		// Blog now returns old blog actor.
		\add_action( 'activitypub_construct_model_actor', array( Move::class, 'maybe_initiate_old_user' ) );
		$blog = ( new Blog() )->to_array();

		// The port might be lost due to HTTP_HOST manipulation, so check base URL structure.
		$this->assertStringContainsString( '/?author=0', $blog['id'] );
		$this->assertStringStartsWith( 'http://' . \wp_parse_url( $old_domain, PHP_URL_HOST ), $blog['id'] );

		\remove_action( 'activitypub_construct_model_actor', array( Move::class, 'maybe_initiate_old_user' ) );

		// Clean up.
		\delete_option( 'activitypub_old_host' );
		\delete_option( 'activitypub_blog_user_old_host_data' );
		\update_option( 'home', $old_domain );
		\add_filter( 'option_home', '_config_wp_home' );
	}

	/**
	 * Test blog actor type changes to Service in relay mode.
	 *
	 * @covers ::get_type
	 */
	public function test_blog_actor_type_in_relay_mode() {
		$blog = new Blog();

		// Test without relay mode.
		\update_option( 'activitypub_relay_mode', false );
		$this->assertEquals( 'Group', $blog->get_type() );

		// Test with relay mode.
		\update_option( 'activitypub_relay_mode', true );
		$this->assertEquals( 'Service', $blog->get_type() );

		// Clean up.
		\update_option( 'activitypub_relay_mode', false );
	}

	/**
	 * Test blog actor type changes to Service when marked as bot.
	 *
	 * @covers ::get_type
	 */
	public function test_blog_actor_type_as_bot() {
		$blog = new Blog();

		// Ensure relay mode is off.
		\update_option( 'activitypub_relay_mode', false );

		// Default type should be Group (in multi-user mode).
		$this->assertSame( 'Group', $blog->get_type() );

		// Enable bot mode for blog.
		\update_option( 'activitypub_blog_is_bot', '1' );

		// Type should now be Service.
		$this->assertSame( 'Service', $blog->get_type() );

		// Verify it appears in the actor array.
		$actor_array = $blog->to_array( false );
		$this->assertSame( 'Service', $actor_array['type'] );

		// Disable bot mode.
		\update_option( 'activitypub_blog_is_bot', '0' );

		// Type should be Group again.
		$this->assertSame( 'Group', $blog->get_type() );

		// Clean up.
		\delete_option( 'activitypub_blog_is_bot' );
	}

	/**
	 * Test that relay mode takes precedence over bot setting.
	 *
	 * @covers ::get_type
	 */
	public function test_relay_mode_precedence_over_bot() {
		$blog = new Blog();

		// Enable relay mode only (bot off).
		\update_option( 'activitypub_relay_mode', true );
		\update_option( 'activitypub_blog_is_bot', '0' );

		// Type should be Service from relay mode alone.
		$this->assertSame( 'Service', $blog->get_type() );

		// Enable both relay mode and bot mode.
		\update_option( 'activitypub_blog_is_bot', '1' );

		// Type should still be Service (both result in Service, but relay is checked first).
		$this->assertSame( 'Service', $blog->get_type() );

		// Disable relay mode, bot should still keep it as Service.
		\update_option( 'activitypub_relay_mode', false );
		$this->assertSame( 'Service', $blog->get_type() );

		// Disable bot mode too.
		\update_option( 'activitypub_blog_is_bot', '0' );
		$this->assertSame( 'Group', $blog->get_type() );

		// Clean up.
		\delete_option( 'activitypub_relay_mode' );
		\delete_option( 'activitypub_blog_is_bot' );
	}
}
