<?php
/**
 * Test file for Activitypub Activity.
 *
 * @package Activitypub
 */

use DMS\PHPUnitExtensions\ArraySubset\Assert;

/**
 * Test class for Activitypub Activity.
 *
 * @coversDefaultClass \Activitypub\Activity\Activity
 */
class Test_Activitypub_Activity extends WP_UnitTestCase {

	/**
	 * Test activity mentions.
	 */
	public function test_activity_mentions() {
		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => '@alex hello',
			)
		);

		add_filter(
			'activitypub_extract_mentions',
			function ( $mentions ) {
				$mentions['@alex'] = 'https://example.com/alex';
				return $mentions;
			}
		);

		$activitypub_post = \Activitypub\Transformer\Post::transform( get_post( $post ) )->to_object();

		$activitypub_activity = new \Activitypub\Activity\Activity();
		$activitypub_activity->set_type( 'Create' );
		$activitypub_activity->set_object( $activitypub_post );

		$this->assertContains( \Activitypub\get_rest_url_by_path( 'actors/1/followers' ), $activitypub_activity->get_cc() );
		$this->assertContains( 'https://example.com/alex', $activitypub_activity->get_cc() );

		remove_all_filters( 'activitypub_extract_mentions' );
		\wp_trash_post( $post );
	}

	/**
	 * Test object transformation.
	 */
	public function test_object_transformation() {
		$test_array = array(
			'id'        => 'https://example.com/post/123',
			'type'      => 'Note',
			'content'   => 'Hello world!',
			'sensitive' => false,
		);

		$object = \Activitypub\Activity\Base_Object::init_from_array( $test_array );

		$this->assertEquals( 'Hello world!', $object->get_content() );

		$new_array = $object->to_array();
		// Ignore the added json-ld context for now.
		unset( $new_array['@context'] );
		$this->assertEquals( $test_array, $new_array );
	}

	/**
	 * Test activity object.
	 */
	public function test_activity_object() {
		$test_array = array(
			'id'     => 'https://example.com/post/123',
			'type'   => 'Create',
			'object' => array(
				'id'      => 'https://example.com/post/123/activity',
				'type'    => 'Note',
				'content' => 'Hello world!',
			),
		);

		$activity = \Activitypub\Activity\Activity::init_from_array( $test_array );

		$this->assertEquals( 'Hello world!', $activity->get_object()->get_content() );
		Assert::assertArraySubset( $test_array, $activity->to_array() );
	}

	/**
	 * Test activity object.
	 */
	public function test_activity_object_url() {
		$id = 'https://example.com/author/123';

		// Build the update.
		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( 'Update' );
		$activity->set_actor( $id );
		$activity->set_object( $id );
		$activity->set_to( array( 'https://www.w3.org/ns/activitystreams#Public' ) );

		$this->assertTrue( str_starts_with( $activity->get_id(), 'https://example.com/author/123#activity-update-' ) );
	}
}
