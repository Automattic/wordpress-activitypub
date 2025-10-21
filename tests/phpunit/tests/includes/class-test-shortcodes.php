<?php
/**
 * Test file for Activitypub Shortcodes.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Scheduler\Post;
use Activitypub\Shortcodes;

/**
 * Test class for Activitypub Shortcodes.
 *
 * @coversDefaultClass \Activitypub\Shortcodes
 */
class Test_Shortcodes extends \WP_UnitTestCase {

	/**
	 * Post object.
	 *
	 * @var \WP_Post
	 */
	protected $post;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		remove_action( 'wp_after_insert_post', array( Post::class, 'schedule_post_activity' ), 33 );

		Shortcodes::register();

		// Create a post.
		$this->post = self::factory()->post->create_and_get(
			array(
				'post_title'   => 'Test title for shortcode',
				'post_content' => 'Lorem ipsum dolor sit amet, consectetur.',
				'post_excerpt' => '',
			)
		);
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		parent::tear_down();

		Shortcodes::unregister();

		// Delete the post.
		wp_delete_post( $this->post->ID, true );
	}

	/**
	 * Test the content shortcode.
	 */
	public function test_content() {
		global $post;

		remove_filter( 'the_content', 'apply_block_hooks_to_content_from_post_object', 8 );

		$post               = $this->post;
		$post->post_content = '<script>test</script>hallo<script type="javascript">{"asdf": "qwerty"}</script><style></style>';

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( '[ap_content]' );
		wp_reset_postdata();

		$this->assertEquals( '<p>hallo</p>', $content );
	}

	/**
	 * Test the content shortcode with password protected content.
	 */
	public function test_password_protected_content() {
		global $post;

		$post                = $this->post;
		$post->post_password = 'abc';

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( '[ap_content]' );
		wp_reset_postdata();

		$this->assertEquals( '', $content );
	}

	/**
	 * Test the excerpt shortcode.
	 */
	public function test_excerpt() {
		global $post;

		$post               = $this->post;
		$post->post_content = 'Lorem ipsum dolor sit amet, consectetur.';

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( '[ap_excerpt length="25"]' );
		wp_reset_postdata();

		$this->assertEquals( "<p>Lorem ipsum dolor sit […]</p>\n", $content );
	}

	/**
	 * Tests 'ap_title' shortcode.
	 *
	 * @covers ::title
	 */
	public function test_title() {
		global $post;

		$post = $this->post;

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( '[ap_title]' );
		wp_reset_postdata();

		$this->assertEquals( 'Test title for shortcode', $content );
	}
}
