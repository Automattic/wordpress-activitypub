<?php
/**
 * Test file for Outbox collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Outbox;
use Activitypub\Activity\Base_Object;
use WP_UnitTestCase;

/**
 * Test class for Outbox collection.
 *
 * @coversDefaultClass \Activitypub\Collection\Outbox
 */
class Test_Outbox extends \Activitypub\Tests\ActivityPub_Outbox_TestCase {
	/**
	 * Test add an item to the outbox.
	 *
	 * @covers ::add
	 *
	 * @dataProvider activity_object_provider
	 * @param array  $data    The data to add.
	 * @param string $type    The type of the activity.
	 * @param int    $user_id The user ID.
	 * @param string $json    The JSON representation of the data.
	 */
	public function test_add( $data, $type, $user_id, $json ) {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$id = \Activitypub\add_to_outbox( $data, $type, $user_id );

		$this->assertIsInt( $id );

		$post = \get_post( $id );

		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'pending', $post->post_status );
		$this->assertEquals( $json, $post->post_content );

		$activity = json_decode( $post->post_content );
		$this->assertSame( $data['content'], $activity->content );

		$this->assertEquals( $type, \get_post_meta( $id, '_activitypub_activity_type', true ) );

		// Fall back to blog if user does not have the activitypub capability.
		$actor_type = \user_can( $user_id, 'activitypub' ) ? 'user' : 'blog';
		$this->assertEquals( $actor_type, \get_post_meta( $id, '_activitypub_activity_actor', true ) );
	}

	/**
	 * Data provider for test_add.
	 *
	 * @return array
	 */
	public function activity_object_provider() {
		return array(
			array(
				array(
					'@context' => 'https://www.w3.org/ns/activitystreams',
					'id'       => 'https://example.com/' . self::$user_id,
					'type'     => 'Note',
					'content'  => '<p>This is a note</p>',
				),
				'Create',
				1,
				'{"@context":["https:\/\/www.w3.org\/ns\/activitystreams",{"Hashtag":"as:Hashtag","sensitive":"as:sensitive"}],"id":"https:\/\/example.com\/' . self::$user_id . '","type":"Note","content":"\u003Cp\u003EThis is a note\u003C\/p\u003E","contentMap":{"en":"\u003Cp\u003EThis is a note\u003C\/p\u003E"},"tag":[],"to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"cc":[],"mediaType":"text\/html","sensitive":false}',
			),
			array(
				array(
					'@context' => 'https://www.w3.org/ns/activitystreams',
					'id'       => 'https://example.com/2',
					'type'     => 'Note',
					'content'  => '<p>This is another note</p>',
				),
				'Create',
				2,
				'{"@context":["https:\/\/www.w3.org\/ns\/activitystreams",{"Hashtag":"as:Hashtag","sensitive":"as:sensitive"}],"id":"https:\/\/example.com\/2","type":"Note","content":"\u003Cp\u003EThis is another note\u003C\/p\u003E","contentMap":{"en":"\u003Cp\u003EThis is another note\u003C\/p\u003E"},"tag":[],"to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"cc":[],"mediaType":"text\/html","sensitive":false}',
			),
		);
	}

	/**
	 * Test add an item to the outbox with a user.
	 *
	 * @covers ::add
	 * @dataProvider author_object_provider
	 *
	 * @param string $mode           The actor mode.
	 * @param int    $user_id        The user ID.
	 * @param string $expected_actor The expected actor.
	 */
	public function test_author_fallbacks( $mode, $user_id, $expected_actor ) {
		\update_option( 'activitypub_actor_mode', $mode );

		$user_id = $user_id ?? self::$user_id;
		$data    = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => 'https://example.com/' . $user_id,
			'type'     => 'Note',
			'content'  => '<p>This is a note</p>',
		);

		$id = \Activitypub\add_to_outbox( $data, 'Create', $user_id );
		$this->assertEquals( $expected_actor, \get_post_meta( $id, '_activitypub_activity_actor', true ) );
	}

	/**
	 * Data provider for test_author_fallbacks.
	 *
	 * @return array[]
	 */
	public function author_object_provider() {
		return array(
			array( ACTIVITYPUB_ACTOR_AND_BLOG_MODE, null, 'user' ),
			array( ACTIVITYPUB_ACTOR_AND_BLOG_MODE, 90210, 'blog' ),
			array( ACTIVITYPUB_BLOG_MODE, 90210, 'blog' ),
			array( ACTIVITYPUB_ACTOR_MODE, 90210, false ),
		);
	}

	/**
	 * Test get_pending method.
	 *
	 * @covers ::get_pending
	 */
	public function test_get_pending() {
		// Create test activity objects.
		$activity_object = new Base_Object();
		$activity_object->set_content( 'Test Content' );
		$activity_object->set_type( 'Note' );
		$activity_object->set_id( 'https://example.com/test-id-5' );

		// Add multiple pending activities.
		$pending_ids = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$pending_ids[] = Outbox::add(
				$activity_object,
				'Create',
				self::$user_id,
				ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC
			);
		}

		// Add a published activity (should not be returned).
		$published_id = Outbox::add(
			$activity_object,
			'Create',
			self::$user_id,
			ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC
		);
		wp_update_post(
			array(
				'ID'          => $published_id,
				'post_status' => 'publish',
			)
		);

		// Test default limit (10).
		$pending_activities = Outbox::get_pending();
		$this->assertCount( 5, $pending_activities );
		$this->assertEquals( 'pending', $pending_activities[0]->post_status );

		// Test custom limit.
		$limited_activities = Outbox::get_pending( 3 );
		$this->assertCount( 3, $limited_activities );

		// Test limit larger than available items.
		$all_activities = Outbox::get_pending( 20 );
		$this->assertCount( 5, $all_activities );

		// Test with no pending activities.
		foreach ( $pending_ids as $id ) {
			wp_delete_post( $id, true );
		}
		$no_activities = Outbox::get_pending();
		$this->assertEmpty( $no_activities );

		// Clean up.
		wp_delete_post( $published_id, true );
	}

	/**
	 * Test get_pending returns correct post type.
	 *
	 * @covers ::get_pending
	 */
	public function test_get_pending_post_type() {
		// Create test activity.
		$activity_object = new Base_Object();
		$activity_object->set_content( 'Test Content' );
		$activity_object->set_type( 'Note' );
		$activity_object->set_id( 'https://example.com/test-id-3' );

		$pending_id = Outbox::add(
			$activity_object,
			'Create',
			self::$user_id,
			ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC
		);

		$pending_activities = Outbox::get_pending();
		$this->assertEquals( Outbox::POST_TYPE, $pending_activities[0]->post_type );

		// Clean up.
		wp_delete_post( $pending_id, true );
	}

	/**
	 * Test get_pending with multiple users.
	 *
	 * @covers ::get_pending
	 */
	public function test_get_pending_multiple_users() {
		// Create second test user.
		$second_user_id = $this->factory->user->create(
			array(
				'role' => 'author',
			)
		);

		$activity_object = new Base_Object();
		$activity_object->set_content( 'Test Content' );
		$activity_object->set_type( 'Note' );
		$activity_object->set_id( 'https://example.com/test-id-4' );

		// Add activities for first user.
		$first_user_id_post = Outbox::add(
			$activity_object,
			'Create',
			self::$user_id,
			ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC
		);

		// Add activities for second user.
		$second_user_id_post = Outbox::add(
			$activity_object,
			'Create',
			$second_user_id,
			ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC
		);

		$pending_activities = Outbox::get_pending();
		$this->assertCount( 2, $pending_activities );

		// Verify activities from both users are returned.
		$authors = array_map(
			function ( $activity ) {
				return (int) $activity->post_author;
			},
			$pending_activities
		);

		$this->assertContains( self::$user_id, $authors );
		$this->assertContains( $second_user_id, $authors );

		// Clean up.
		\wp_delete_post( $first_user_id_post, true );
		\wp_delete_post( $second_user_id_post, true );
		\wp_delete_user( $second_user_id );
	}
}
