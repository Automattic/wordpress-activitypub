<?php
/**
 * Test file for Activitypub Shortcodes.
 *
 * @package Activitypub
 */

use Activitypub\Shortcodes;

/**
 * Test class for Activitypub Shortcodes.
 *
 * @coversDefaultClass \Activitypub\Shortcodes
 */
class Test_Activitypub_Shortcodes extends WP_UnitTestCase {

	/**
	 * Test the content shortcode.
	 *
	 * @covers ::content
	 */
	public function test_content() {
		Shortcodes::register();
		global $post;

		$post_id              = -99; // Negative ID, to avoid clash with a valid post.
		$post                 = new stdClass();
		$post->ID             = $post_id;
		$post->post_author    = 1;
		$post->post_date      = current_time( 'mysql' );
		$post->post_date_gmt  = current_time( 'mysql', 1 );
		$post->post_title     = 'Some title or other';
		$post->post_content   = '<script>test</script>hallo<script type="javascript">{"asdf": "qwerty"}</script><style></style>';
		$post->post_status    = 'publish';
		$post->comment_status = 'closed';
		$post->ping_status    = 'closed';
		$post->post_name      = 'fake-post-' . wp_rand( 1, 99999 ); // Append random number to avoid clash.
		$post->post_type      = 'post';
		$post->filter         = 'raw'; // important!

		$content = '[ap_content]';

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( $content );
		wp_reset_postdata();

		$this->assertEquals( '<p>hallo</p>', $content );
		Shortcodes::unregister();
	}

	/**
	 * Test the content shortcode with password protected content.
	 *
	 * @covers ::content
	 */
	public function test_password_protected_content() {
		Shortcodes::register();
		global $post;

		$post_id              = -98; // Negative ID, to avoid clash with a valid post.
		$post                 = new stdClass();
		$post->ID             = $post_id;
		$post->post_author    = 1;
		$post->post_date      = current_time( 'mysql' );
		$post->post_date_gmt  = current_time( 'mysql', 1 );
		$post->post_title     = 'Some title or other';
		$post->post_content   = '<script>test</script>hallo<script type="javascript">{"asdf": "qwerty"}</script><style></style>';
		$post->comment_status = 'closed';
		$post->ping_status    = 'closed';
		$post->post_name      = 'fake-post-' . wp_rand( 1, 99999 ); // Append random number to avoid clash.
		$post->post_type      = 'post';
		$post->filter         = 'raw'; // important!
		$post->post_password  = 'abc';

		$content = '[ap_content]';

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( $content );
		wp_reset_postdata();

		$this->assertEquals( '', $content );
		Shortcodes::unregister();
	}

	/**
	 * Test the excerpt shortcode.
	 *
	 * @covers ::excerpt
	 */
	public function test_excerpt() {
		Shortcodes::register();
		global $post;

		$post_id              = -97; // Negative ID, to avoid clash with a valid post.
		$post                 = new stdClass();
		$post->ID             = $post_id;
		$post->post_author    = 1;
		$post->post_date      = current_time( 'mysql' );
		$post->post_date_gmt  = current_time( 'mysql', 1 );
		$post->post_title     = 'Some title or other';
		$post->post_content   = '<script>test</script>Lorem ipsum dolor sit amet, consectetur.<script type="javascript">{"asdf": "qwerty"}</script><style></style>';
		$post->post_status    = 'publish';
		$post->comment_status = 'closed';
		$post->ping_status    = 'closed';
		$post->post_name      = 'fake-post-' . wp_rand( 1, 99999 ); // Append random number to avoid clash.
		$post->post_type      = 'post';
		$post->filter         = 'raw'; // important!

		$content = '[ap_excerpt length="25"]';

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( $content );
		wp_reset_postdata();

		$this->assertEquals( "<p>Lorem ipsum dolor […]</p>\n", $content );
		Shortcodes::unregister();
	}

	/**
	 * Tests 'ap_title' shortcode.
	 *
	 * @covers ::title
	 */
	public function test_title() {
		Shortcodes::register();
		global $post;

		$post = self::factory()->post->create_and_get(
			array(
				'post_title' => 'Test title for shortcode',
			)
		);

		$content = '[ap_title]';

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( $content );
		wp_reset_postdata();
		Shortcodes::unregister();

		$this->assertEquals( 'Test title for shortcode', $content );
	}

	/**
	 * Test the content shortcode with more tag.
	 *
	 * @covers ::content
	 */
	public function test_content_with_more_tag() {
		Shortcodes::register();
		global $post;

		// Create a post with more tag.
		$post = self::factory()->post->create_and_get(
			array(
				'post_title'   => 'Test Post with More',
				'post_content' => 'This is the teaser content.<!--more-->This is the full content.',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_author'  => 1,
			)
		);

		setup_postdata( $post );

		// Test without more_tag attribute (should show full content).
		$content = do_shortcode( '[ap_content apply_filters="no"]' );
		$this->assertEquals(
			'This is the teaser content.<!--more-->This is the full content.',
			trim( $content )
		);

		// Test with more_tag attribute (should show only teaser).
		$content = do_shortcode( '[ap_content more_tag="yes"]' );
		$this->assertEquals(
			'<p>This is the teaser content.</p>',
			trim( $content )
		);

		// Test with more_tag=no and apply_filters=yes (should show full content with more tag replaced).
		$content = do_shortcode( '[ap_content]' );
		$this->assertEquals(
			'<p>This is the teaser content.<!--more-->This is the full content.</p>',
			trim( $content )
		);

		wp_reset_postdata();
		Shortcodes::unregister();
	}
}
