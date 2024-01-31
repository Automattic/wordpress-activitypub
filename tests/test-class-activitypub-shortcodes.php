<?php
use Activitypub\Shortcodes;

class Test_Activitypub_Shortcodes extends WP_UnitTestCase {

	public function test_content() {
		Shortcodes::register();
		global $post;

		$post_id = -99; // negative ID, to avoid clash with a valid post
		$post = new stdClass();
		$post->ID = $post_id;
		$post->post_author = 1;
		$post->post_date = current_time( 'mysql' );
		$post->post_date_gmt = current_time( 'mysql', 1 );
		$post->post_title = 'Some title or other';
		$post->post_content = '<script>test</script>hallo<script type="javascript">{"asdf": "qwerty"}</script><style></style>';
		$post->post_status = 'publish';
		$post->comment_status = 'closed';
		$post->ping_status = 'closed';
		$post->post_name = 'fake-post-' . rand( 1, 99999 ); // append random number to avoid clash
		$post->post_type = 'post';
		$post->filter = 'raw'; // important!

		$content = '[ap_content]';

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( $content );
		wp_reset_postdata();

		$this->assertEquals( '<p>hallo</p>', $content );
		Shortcodes::unregister();
	}

	public function test_password_protected_content() {
		Shortcodes::register();
		global $post;

		$post_id = -98; // negative ID, to avoid clash with a valid post
		$post = new stdClass();
		$post->ID = $post_id;
		$post->post_author = 1;
		$post->post_date = current_time( 'mysql' );
		$post->post_date_gmt = current_time( 'mysql', 1 );
		$post->post_title = 'Some title or other';
		$post->post_content = '<script>test</script>hallo<script type="javascript">{"asdf": "qwerty"}</script><style></style>';
		$post->comment_status = 'closed';
		$post->ping_status = 'closed';
		$post->post_name = 'fake-post-' . rand( 1, 99999 ); // append random number to avoid clash
		$post->post_type = 'post';
		$post->filter = 'raw'; // important!
		$post->post_password = 'abc';

		$content = '[ap_content]';

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( $content );
		wp_reset_postdata();

		$this->assertEquals( '', $content );
		Shortcodes::unregister();
	}

	public function test_excerpt() {
		Shortcodes::register();
		global $post;

		$post_id = -97; // negative ID, to avoid clash with a valid post
		$post = new stdClass();
		$post->ID = $post_id;
		$post->post_author = 1;
		$post->post_date = current_time( 'mysql' );
		$post->post_date_gmt = current_time( 'mysql', 1 );
		$post->post_title = 'Some title or other';
		$post->post_content = '<script>test</script>Lorem ipsum dolor sit amet, consectetur.<script type="javascript">{"asdf": "qwerty"}</script><style></style>';
		$post->post_status = 'publish';
		$post->comment_status = 'closed';
		$post->ping_status = 'closed';
		$post->post_name = 'fake-post-' . rand( 1, 99999 ); // append random number to avoid clash
		$post->post_type = 'post';
		$post->filter = 'raw'; // important!

		$content = '[ap_excerpt length="25"]';

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( $content );
		wp_reset_postdata();

		$this->assertEquals( "<p>Lorem ipsum [&hellip;]</p>\n", $content );
		Shortcodes::unregister();
	}
}
