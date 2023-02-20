<?php
class Test_Activitypub_Shortcodes extends WP_UnitTestCase {
	public function test_content() {
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
		$post->post_name = 'fake-page-' . rand( 1, 99999 ); // append random number to avoid clash
		$post->post_type = 'page';
		$post->filter = 'raw'; // important!

		$content = '[ap_content]';

		// Fill in the shortcodes.
		setup_postdata( $post );
		$content = do_shortcode( $content );
		wp_reset_postdata();

		$this->assertEquals( '<p>hallo</p>', $content );
	}
}
