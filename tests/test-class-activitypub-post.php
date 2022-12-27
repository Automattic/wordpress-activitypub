<?php
class Test_Activitypub_Post extends WP_UnitTestCase {
	public function test_to_array() {
		\add_action( 'wp_trash_post', array( '\Activitypub\Activitypub', 'trash_post' ), 1 );
		\add_action( 'untrash_post', array( '\Activitypub\Activitypub', 'untrash_post' ), 1 );

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
