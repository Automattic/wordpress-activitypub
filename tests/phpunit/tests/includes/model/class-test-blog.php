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
	 * Test the custom blog name and its fallback to the site title.
	 *
	 * @covers ::get_name
	 */
	public function test_get_name() {
		\update_option( 'blogname', 'Site Title' );
		\delete_option( 'activitypub_blog_name' );

		// Falls back to the site title.
		$this->assertSame( 'Site Title', ( new Blog() )->get_name() );

		// Custom name takes precedence.
		\update_option( 'activitypub_blog_name', 'Custom Name' );
		$this->assertSame( 'Custom Name', ( new Blog() )->get_name() );

		// Site title changes are not picked up while a custom name is set.
		\update_option( 'blogname', 'New Site Title' );
		$this->assertSame( 'Custom Name', ( new Blog() )->get_name() );

		// Emptying the custom name falls back to the site title.
		\update_option( 'activitypub_blog_name', '' );
		$this->assertSame( 'New Site Title', ( new Blog() )->get_name() );
	}

	/**
	 * Test update_name writes the custom option and not the site title.
	 *
	 * @covers ::update_name
	 */
	public function test_update_name() {
		$blog = new Blog();

		$this->assertTrue( $blog->update_name( 'Federated Name' ) );
		$this->assertSame( 'Federated Name', \get_option( 'activitypub_blog_name' ) );
		$this->assertNotSame( 'Federated Name', \get_option( 'blogname' ) );
		$this->assertSame( 'Federated Name', $blog->get_name() );
	}

	/**
	 * Test the custom blog icon and its fallback to the site icon.
	 *
	 * @covers ::get_icon
	 */
	public function test_get_icon() {
		$attachment_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );

		// Falls back to the site icon when no custom icon is set.
		\delete_option( 'activitypub_blog_icon' );
		$fallback = ( new Blog() )->get_icon();
		$this->assertArrayHasKey( 'url', $fallback );

		// Custom icon takes precedence.
		\update_option( 'activitypub_blog_icon', $attachment_id );
		$icon = ( new Blog() )->get_icon();
		$this->assertSame( 'Image', $icon['type'] );
		$this->assertSame( \wp_get_attachment_url( $attachment_id ), $icon['url'] );

		// Falls back when the attachment is not an image.
		\update_option( 'activitypub_blog_icon', 999999 );
		$icon = ( new Blog() )->get_icon();
		$this->assertSame( $fallback['url'], $icon['url'] );
	}

	/**
	 * Test update_icon writes the custom option and not the site icon.
	 *
	 * @covers ::update_icon
	 */
	public function test_update_icon() {
		$blog          = new Blog();
		$attachment_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/data/assets/test.jpg' );

		// Non-image attachment IDs are rejected.
		$this->assertFalse( $blog->update_icon( 999999 ) );

		$this->assertTrue( $blog->update_icon( $attachment_id ) );
		$this->assertSame( $attachment_id, (int) \get_option( 'activitypub_blog_icon' ) );
		$this->assertNotSame( $attachment_id, (int) \get_option( 'site_icon' ) );
		$this->assertSame( \wp_get_attachment_url( $attachment_id ), $blog->get_icon()['url'] );
	}
}
