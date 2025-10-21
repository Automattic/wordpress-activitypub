<?php
/**
 * Test file for Activity.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Activity;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use DMS\PHPUnitExtensions\ArraySubset\Assert;

/**
 * Test class for Activity.
 *
 * @coversDefaultClass \Activitypub\Activity\Activity
 */
class Test_Activity extends \WP_UnitTestCase {

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

		$activitypub_activity = new Activity();
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
			'id'      => 'https://example.com/post/123',
			'type'    => 'Note',
			'content' => 'Hello world!',
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
	 *
	 * @covers ::init_from_array
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

		$activity = Activity::init_from_array( $test_array );

		$this->assertEquals( 'Hello world!', $activity->get_object()->get_content() );
		Assert::assertArraySubset( $test_array, $activity->to_array() );
	}

	/**
	 * Test activity object.
	 *
	 * @covers ::init_from_array
	 */
	public function test_activity_object_url() {
		$test_array = array(
			'id'     => 'https://example.com/id/123',
			'type'   => 'Follow',
			'object' => 'https://example.com/post/123',
		);

		$activity = Activity::init_from_array( $test_array );

		$this->assertEquals( 'https://example.com/id/123', $activity->get_id() );

		$test_array2 = array(
			'type'   => 'Follow',
			'object' => 'https://example.com/post/123',
		);

		$activity2 = Activity::init_from_array( $test_array2 );

		$this->assertTrue( str_starts_with( $activity2->get_id(), 'https://example.com/post/123#activity-follow-' ) );
	}

	/**
	 * Test activity object.
	 */
	public function test_activity_object_id() {
		$id = 'https://example.com/author/123';

		// Build the update.
		$activity = new Activity();
		$activity->set_type( 'Update' );
		$activity->set_actor( $id );
		$activity->set_object( $id );
		$activity->set_to( array( 'https://www.w3.org/ns/activitystreams#Public' ) );

		$this->assertTrue( str_starts_with( $activity->get_id(), 'https://example.com/author/123#activity-update-' ) );
	}

	/**
	 * Test activity object list.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#Flag
	 * @covers ::init_from_array
	 */
	public function test_activity_object_list() {
		$object   = array(
			'https://dummysite.example/?author=0',
			'https://dummysite.example/?p=123',
		);
		$activity = Activity::init_from_array(
			array(
				'id'      => 'https://example.social/activities/123',
				'type'    => 'Flag',
				'actor'   => 'https://example.social/actor',
				'content' => '',
				'object'  => $object,
			)
		);

		$this->assertSame( $object, $activity->get_object() );
	}

	/**
	 * Test activity object mixed array.
	 *
	 * @covers ::init_from_array
	 */
	public function test_activity_object_mixed_array() {
		$activity = Activity::init_from_array(
			array(
				'@context' => 'https://www.w3.org/ns/activitystreams',
				'summary'  => 'Sally liked a note',
				'type'     => 'Like',
				'actor'    => 'http://sally.example.org',
				'object'   => array(
					'http://example.org/posts/1',
					array(
						'type'    => 'Note',
						'summary' => 'A simple note',
						'content' => 'That is a tree.',
					),
				),
			)
		);

		$object = $activity->get_object();

		// Should be an array with 2 items.
		$this->assertIsArray( $object );
		$this->assertCount( 2, $object );

		// First item should be the URL string (unchanged).
		$this->assertSame( 'http://example.org/posts/1', $object[0] );

		// Second item should be a Base_Object (converted from array).
		$this->assertInstanceOf( 'Activitypub\Activity\Base_Object', $object[1] );
	}

	/**
	 * Test activity object.
	 */
	public function test_activity_object_in_reply_to() {
		// Create user with `activitypub` capabilities.
		$user_id = self::factory()->user->create(
			array(
				'role' => 'author',
			)
		);

		// Only send minimal data.
		$activity_object = array(
			'id'     => 'https://example.com/post/123',
			'type'   => 'Follow',
			'actor'  => 'https://example.com/author/123',
			'object' => 'https://example.com/post/123',
			'to'     => array( 'https://example.com/author/123' ),
		);

		$activity = new Activity();
		$activity->set_type( 'Accept' );
		$activity->set_actor( Actors::get_by_id( $user_id )->get_id() );
		$activity->set_object( $activity_object );

		$this->assertContains( 'https://example.com/author/123', $activity->get_to() );

		$activity->set_to( array( 'https://example.com/author/456' ) );
		$activity->set_object( $activity_object );

		$this->assertContains( 'https://example.com/author/456', $activity->get_to() );

		// Delete user.
		\wp_delete_user( $user_id );
	}
}
