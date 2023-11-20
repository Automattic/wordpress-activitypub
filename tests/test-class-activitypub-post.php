<?php
class Test_Activitypub_Post extends WP_UnitTestCase {
	public function test_to_array() {
		$post = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => 'test',
			)
		);

		$permalink = \get_permalink( $post );

		$activitypub_post = \Activitypub\Transformers_Manager::instance()->get_transformer( get_post( $post ) )->set_wp_post( get_post( $post ) )->to_object();

		$this->assertEquals( $permalink, $activitypub_post->get_id() );

		\wp_trash_post( $post );

		$activitypub_post = \Activitypub\Transformers_Manager::instance()->get_transformer( get_post( $post ) )->set_wp_post( get_post( $post ) )->to_object();

		$this->assertEquals( $permalink, $activitypub_post->get_id() );

		$cached = \get_post_meta( $post, 'activitypub_canonical_url', true );

		$this->assertEquals( $cached, $activitypub_post->get_id() );
	}
}
