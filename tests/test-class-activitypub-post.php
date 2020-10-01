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

		$activitypub_post = new \Activitypub\Model\Post( $post );

		$this->assertEquals( $permalink, $activitypub_post->get_id() );

		\wp_trash_post( $post );

		$activitypub_post = new \Activitypub\Model\Post( $post );

		$this->assertEquals( $permalink, $activitypub_post->get_id() );
	}
}
