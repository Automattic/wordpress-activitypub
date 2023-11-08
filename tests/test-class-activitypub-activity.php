<?php
use DMS\PHPUnitExtensions\ArraySubset\Assert;

class Test_Activitypub_Activity extends WP_UnitTestCase {
	public function test_activity_mentions() {
		$post = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => '@alex hello',
			)
		);

		add_filter(
			'activitypub_extract_mentions',
			function( $mentions ) {
				$mentions['@alex'] = 'https://example.com/alex';
				return $mentions;
			},
			10
		);

		$activitypub_post = \Activitypub\Transformer\Post::transform( get_post( $post ) )->to_object();

		$activitypub_activity = new \Activitypub\Activity\Activity();
		$activitypub_activity->set_type( 'Create' );
		$activitypub_activity->set_object( $activitypub_post );

		$this->assertContains( \Activitypub\get_rest_url_by_path( 'users/1/followers' ), $activitypub_activity->get_to() );
		$this->assertContains( 'https://example.com/alex', $activitypub_activity->get_cc() );

		remove_all_filters( 'activitypub_extract_mentions' );
		\wp_trash_post( $post );
	}

	public function test_object_transformation() {
		$test_array = array(
			'id'      => 'https://example.com/post/123',
			'type'    => 'Note',
			'content' => 'Hello world!',
		);

		$object = \Activitypub\Activity\Base_Object::init_from_array( $test_array );

		$this->assertEquals( 'Hello world!', $object->get_content() );
		$this->assertEquals( $test_array, $object->to_array() );
	}

	public function test_activity_object() {
		$test_array = array(
			'id'      => 'https://example.com/post/123',
			'type'    => 'Create',
			'object'  => array(
				'id'      => 'https://example.com/post/123/activity',
				'type'    => 'Note',
				'content' => 'Hello world!',
			),
		);

		$activity = \Activitypub\Activity\Activity::init_from_array( $test_array );

		$this->assertEquals( 'Hello world!', $activity->get_object()->get_content() );
		Assert::assertArraySubset( $test_array, $activity->to_array() );
	}
}
