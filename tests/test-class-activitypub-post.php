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

		$activitypub_post = \Activitypub\Transformer\Post::transform( get_post( $post ) )->to_object();

		$this->assertEquals( $permalink, $activitypub_post->get_id() );

		\wp_trash_post( $post );

		$activitypub_post = \Activitypub\Transformer\Post::transform( get_post( $post ) )->to_object();

		$this->assertEquals( $permalink, $activitypub_post->get_id() );

		$cached = \get_post_meta( $post, 'activitypub_canonical_url', true );

		$this->assertEquals( $cached, $activitypub_post->get_id() );
	}

	public function test_content_visibility() {
		$post_id = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => 'test content visibility',
			)
		);

		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC );

		$this->assertFalse( \Activitypub\is_post_disabled( $post_id ) );
		$object = \Activitypub\Transformer\Post::transform( get_post( $post_id ) )->to_object();
		$this->assertContains( 'https://www.w3.org/ns/activitystreams#Public', $object->get_to() );

		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC );

		$this->assertFalse( \Activitypub\is_post_disabled( $post_id ) );
		$object = \Activitypub\Transformer\Post::transform( get_post( $post_id ) )->to_object();
		$this->assertContains( 'https://www.w3.org/ns/activitystreams#Public', $object->get_cc() );

		\update_post_meta( $post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );

		$this->assertTrue( \Activitypub\is_post_disabled( $post_id ) );
		$object = \Activitypub\Transformer\Post::transform( get_post( $post_id ) )->to_object();
		$this->assertEquals( array(), $object->get_to() );
		$this->assertEquals( array(), $object->get_cc() );
	}
}
