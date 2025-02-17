<?php
/**
 * Test file for Outbox collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Activity\Base_Object;
use Activitypub\Activity\Extended_Object\Event;
use Activitypub\Collection\Outbox;

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
		$this->assertJsonStringEqualsJsonString( $json, $post->post_content );

		$activity = json_decode( $post->post_content );

		if ( is_array( $data ) ) {
			$this->assertSame( $data['content'], $activity->content );
		} elseif ( $data instanceof Base_Object ) {
			$this->assertSame( $data->get_content(), $activity->content );
		}
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
				'{"@context":["https:\/\/www.w3.org\/ns\/activitystreams",{"Hashtag":"as:Hashtag","sensitive":"as:sensitive"}],"id":"https:\/\/example.com\/' . self::$user_id . '","type":"Note","content":"\u003Cp\u003EThis is a note\u003C\/p\u003E","contentMap":{"en":"\u003Cp\u003EThis is a note\u003C\/p\u003E"},"to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"mediaType":"text\/html"}',
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
				'{"@context":["https:\/\/www.w3.org\/ns\/activitystreams",{"Hashtag":"as:Hashtag","sensitive":"as:sensitive"}],"id":"https:\/\/example.com\/2","type":"Note","content":"\u003Cp\u003EThis is another note\u003C\/p\u003E","contentMap":{"en":"\u003Cp\u003EThis is another note\u003C\/p\u003E"},"to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"mediaType":"text\/html"}',
			),
			array(
				Event::init_from_array(
					array(
						'id'        => 'https://example.com/3',
						'name'      => 'WP Test Event',
						'type'      => 'Event',
						'location'  => array(
							array(
								'id'           => 'https://example.com/place/1',
								'type'         => 'Place',
								'attributedTo' => 'https://wp-test.event-federation.eu/@test',
								'name'         => 'Fediverse Place',
								'address'      => array(
									'type'            => 'PostalAddress',
									'addressCountry'  => 'FediCountry',
									'addressLocality' => 'FediTown',
									'postalCode'      => '1337',
									'streetAddress'   => 'FediStreet',
								),
							),
							array(
								'type' => 'VirtualLocation',
								'url'  => 'https://example.com/VirtualMeetingRoom',
							),
						),
						'startTime' => '2030-02-29T16:00:00+01:00',
						'endTime'   => '2030-02-29T17:00:00+01:00',
						'timezone'  => 'Europe/Vienna',
						'joinMode'  => 'external',
						'category'  => 'MOVEMENTS_POLITICS',
						'content'   => '<p>You should not miss this Event!</p>',
					)
				),
				'Create',
				1,
				'{"@context":["https:\/\/schema.org\/","https:\/\/www.w3.org\/ns\/activitystreams",{"pt":"https:\/\/joinpeertube.org\/ns#","mz":"https:\/\/joinmobilizon.org\/ns#","status":"http:\/\/www.w3.org\/2002\/12\/cal\/ical#status","commentsEnabled":"pt:commentsEnabled","isOnline":"mz:isOnline","timezone":"mz:timezone","participantCount":"mz:participantCount","anonymousParticipationEnabled":"mz:anonymousParticipationEnabled","joinMode":{"@id":"mz:joinMode","@type":"mz:joinModeType"},"externalParticipationUrl":{"@id":"mz:externalParticipationUrl","@type":"schema:URL"},"repliesModerationOption":{"@id":"mz:repliesModerationOption","@type":"@vocab"},"contacts":{"@id":"mz:contacts","@type":"@id"}}],"type":"Event","name":"WP Test Event","timezone":"Europe\/Vienna","category":"MOVEMENTS_POLITICS","joinMode":"external","id":"https:\/\/example.com\/3","nameMap":{"en":"WP Test Event"},"endTime":"2030-02-29T17:00:00+01:00","location":[{"id":"https:\/\/example.com\/place\/1","type":"Place","attributedTo":"https:\/\/wp-test.event-federation.eu\/@test","name":"Fediverse Place","address":{"type":"PostalAddress","addressCountry":"FediCountry","addressLocality":"FediTown","postalCode":"1337","streetAddress":"FediStreet"}},{"type":"VirtualLocation","url":"https:\/\/example.com\/VirtualMeetingRoom"}],"startTime":"2030-02-29T16:00:00+01:00","to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"mediaType":"text\/html","content":"\u003Cp\u003EYou should not miss this Event!\u003C\/p\u003E","contentMap":{"en":"\u003Cp\u003EYou should not miss this Event!\u003C\/p\u003E"}}',
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
	 * Test invalidating existing outbox items.
	 */
	public function test_invalidate_existing_items() {
		$object        = $this->get_dummy_activity_object();
		$activity_type = 'Create';

		// Create first outbox item.
		$first_id = \Activitypub\add_to_outbox( $object, $activity_type, 1 );
		$this->assertNotFalse( $first_id );
		$this->assertEquals( 'pending', get_post_status( $first_id ) );

		// Create second outbox item with same object_id and activity_type.
		$second_id = \Activitypub\add_to_outbox( $object, $activity_type, 1 );
		$this->assertNotFalse( $second_id );

		// First item should now be published (invalidated).
		$this->assertEquals( 'publish', get_post_status( $first_id ) );
		// New item should still be pending.
		$this->assertEquals( 'pending', get_post_status( $second_id ) );
	}

	/**
	 * Test that only items with matching object_id and activity_type are invalidated.
	 */
	public function test_selective_invalidation() {
		$object1 = $this->get_dummy_activity_object();
		$object2 = $this->get_dummy_activity_object();
		$object2->set_id( 'https://example.com/different-object' );

		// Create items with different combinations.
		$item1 = \Activitypub\add_to_outbox( $object1, 'Create', 1 ); // Should be invalidated.
		$item2 = \Activitypub\add_to_outbox( $object2, 'Create', 1 ); // Should stay pending (different object).
		$item3 = \Activitypub\add_to_outbox( $object1, 'Update', 1 ); // Should stay pending (different activity).

		// Add new item that should trigger invalidation of item1.
		$new_item = \Activitypub\add_to_outbox( $object1, 'Create', 1 );

		$this->assertEquals( 'publish', get_post_status( $item1 ) );
		$this->assertEquals( 'pending', get_post_status( $item2 ) );
		$this->assertEquals( 'pending', get_post_status( $item3 ) );
		$this->assertEquals( 'pending', get_post_status( $new_item ) );
	}

	/**
	 * Test that Delete activities invalidate all existing items for the object.
	 */
	public function test_delete_invalidates_all_activities() {
		$object = $this->get_dummy_activity_object();

		// Create items with different activity types.
		$create_id = \Activitypub\add_to_outbox( $object, 'Create', 1 );
		$update_id = \Activitypub\add_to_outbox( $object, 'Update', 1 );
		$like_id   = \Activitypub\add_to_outbox( $object, 'Like', 1 );

		$this->assertEquals( 'pending', get_post_status( $create_id ) );
		$this->assertEquals( 'pending', get_post_status( $update_id ) );
		$this->assertEquals( 'pending', get_post_status( $like_id ) );

		// Add Delete activity.
		$delete_id = \Activitypub\add_to_outbox( $object, 'Delete', 1 );

		// All previous activities should be published (invalidated).
		$this->assertEquals( 'publish', get_post_status( $create_id ) );
		$this->assertEquals( 'publish', get_post_status( $update_id ) );
		$this->assertEquals( 'publish', get_post_status( $like_id ) );
		// Delete activity should still be pending.
		$this->assertEquals( 'pending', get_post_status( $delete_id ) );
	}

	/**
	 * Helper method to create a dummy activity object for testing.
	 *
	 * @return \Activitypub\Activity\Activity
	 */
	private function get_dummy_activity_object() {
		$object = new \Activitypub\Activity\Activity();
		$object->set_id( 'https://example.com/test-object' );
		$object->set_type( 'Note' );
		$object->set_content( 'Test content' );

		return $object;
	}

	/**
	 * Test undo.
	 *
	 * @covers ::undo
	 * @dataProvider undo_object_provider
	 *
	 * @param string $type     Type of the activity to be undone.
	 * @param string $expected Expected type.
	 */
	public function test_undo( $type, $expected ) {
		$data = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'id'       => 'https://example.com/' . self::$user_id,
			'type'     => 'Note',
			'content'  => '<p>This is a note</p>',
		);

		$id = \Activitypub\add_to_outbox( $data, $type, self::$user_id );

		$undo_id  = Outbox::undo( $id );
		$activity = Outbox::get_activity( $undo_id );

		// Only ID for Deletes.
		if ( 'Delete' === $expected ) {
			$this->assertSame( get_permalink( $id ), $activity->get_object() );
		} else {
			$this->assertEquals( json_decode( get_post( $undo_id )->post_content, true ), $activity->get_object()->to_array() );
		}

		$this->assertSame( $expected, $activity->get_type() );
	}

	/**
	 * Data provider for test_undo.
	 *
	 * @return array[]
	 */
	public function undo_object_provider() {
		return array(
			array( 'Create', 'Delete' ),
			array( 'Update', 'Undo' ),
			array( 'Add', 'Remove' ),
		);
	}
}
