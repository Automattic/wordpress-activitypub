<?php
/**
 * Test file for Outbox collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Activity\Activity;
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

		// Replace the post ID in the JSON with the actual post ID.
		$json       = json_decode( $json, true );
		$json['id'] = add_query_arg( 'p', $id, $json['id'] );
		$json       = wp_json_encode( $json, JSON_UNESCAPED_SLASHES );

		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'pending', $post->post_status );
		$this->assertJsonStringEqualsJsonString( $json, $post->post_content );

		$activity = json_decode( $post->post_content );

		if ( is_array( $data ) ) {
			$this->assertSame( $data['content'], $activity->object->content );
		} elseif ( $data instanceof Base_Object ) {
			$this->assertSame( $data->get_content(), $activity->object->content );
		}
		$this->assertEquals( $type, \get_post_meta( $id, '_activitypub_activity_type', true ) );

		// Fall back to blog if user does not have the activitypub capability.
		$actor_type = \user_can( $user_id, 'activitypub' ) ? 'user' : 'blog';
		$this->assertEquals( $actor_type, \get_post_meta( $id, '_activitypub_activity_actor', true ) );
	}

	/**
	 * Test comparing objects.
	 *
	 * @covers ::add
	 */
	public function test_compare_objects() {
		$object1 = new Base_Object();
		$object1->set_id( 'https://example.com/1' );
		$object1->set_type( 'Note' );
		$object1->set_content( '<p>Test content</p>' );

		$id1 = \Activitypub\add_to_outbox( $object1, 'Create', 1 );

		$post1     = \get_post( $id1 );
		$activity1 = json_decode( $post1->post_content );

		$object2 = new Activity();
		$object2->set_id( 'https://example.com/1' );
		$object2->set_type( 'Create' );
		$object2->set_object(
			array(
				'id'      => 'https://example.com/1',
				'type'    => 'Note',
				'content' => '<p>Test content</p>',
			)
		);

		$id2 = \Activitypub\add_to_outbox( $object2, null, 1 );

		$post2     = \get_post( $id2 );
		$activity2 = json_decode( $post2->post_content );

		$this->assertEquals( $activity1->object->type, $activity2->object->type );
		$this->assertEquals( $activity1->object->content, $activity2->object->content );
	}

	/**
	 * Data provider for test_add.
	 *
	 * @return array
	 */
	public function activity_object_provider() {
		$home_url = \addcslashes( \home_url(), '/' );

		$note1_json = '{"@context":["https:\/\/www.w3.org\/ns\/activitystreams",{"Hashtag":"as:Hashtag","sensitive":"as:sensitive","dcterms":"http:\/\/purl.org\/dc\/terms\/","gts":"https:\/\/gotosocial.org\/ns#","schema":"http:\/\/schema.org\/","exifData":"schema:exifData","PropertyValue":"schema:PropertyValue","interactionPolicy":{"@id":"gts:interactionPolicy","@type":"@id"},"canQuote":{"@id":"gts:canQuote","@type":"@id"},"canReply":{"@id":"gts:canReply","@type":"@id"},"canLike":{"@id":"gts:canLike","@type":"@id"},"canAnnounce":{"@id":"gts:canAnnounce","@type":"@id"},"automaticApproval":{"@id":"gts:automaticApproval","@type":"@id"},"manualApproval":{"@id":"gts:manualApproval","@type":"@id"},"always":{"@id":"gts:always","@type":"@id"}}],"actor":"http:\/\/example.org\/?author=1","id":"http:\/\/example.org\/?post_type=ap_outbox\u0026p=351","type":"Create","to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"object":{"id":"https:\/\/example.com\/1","type":"Note","content":"\u003Cp\u003EThis is a note\u003C\/p\u003E","contentMap":{"en":"\u003Cp\u003EThis is a note\u003C\/p\u003E"},"tag":[],"to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"mediaType":"text\/html"}}';
		$note2_json = '{"@context":["https:\/\/www.w3.org\/ns\/activitystreams",{"Hashtag":"as:Hashtag","sensitive":"as:sensitive","dcterms":"http:\/\/purl.org\/dc\/terms\/","gts":"https:\/\/gotosocial.org\/ns#","schema":"http:\/\/schema.org\/","exifData":"schema:exifData","PropertyValue":"schema:PropertyValue","interactionPolicy":{"@id":"gts:interactionPolicy","@type":"@id"},"canQuote":{"@id":"gts:canQuote","@type":"@id"},"canReply":{"@id":"gts:canReply","@type":"@id"},"canLike":{"@id":"gts:canLike","@type":"@id"},"canAnnounce":{"@id":"gts:canAnnounce","@type":"@id"},"automaticApproval":{"@id":"gts:automaticApproval","@type":"@id"},"manualApproval":{"@id":"gts:manualApproval","@type":"@id"},"always":{"@id":"gts:always","@type":"@id"}}],"actor":"http:\/\/example.org\/?author=0","id":"http:\/\/example.org\/?post_type=ap_outbox\u0026p=352","type":"Create","to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"object":{"id":"https:\/\/example.com\/2","type":"Note","content":"\u003Cp\u003EThis is another note\u003C\/p\u003E","contentMap":{"en":"\u003Cp\u003EThis is another note\u003C\/p\u003E"},"tag":[],"to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"mediaType":"text\/html"}}';
		$event_json = '{"@context":["https:\/\/schema.org\/","https:\/\/www.w3.org\/ns\/activitystreams",{"pt":"https:\/\/joinpeertube.org\/ns#","mz":"https:\/\/joinmobilizon.org\/ns#","status":"http:\/\/www.w3.org\/2002\/12\/cal\/ical#status","commentsEnabled":"pt:commentsEnabled","isOnline":"mz:isOnline","timezone":"mz:timezone","participantCount":"mz:participantCount","anonymousParticipationEnabled":"mz:anonymousParticipationEnabled","joinMode":{"@id":"mz:joinMode","@type":"mz:joinModeType"},"externalParticipationUrl":{"@id":"mz:externalParticipationUrl","@type":"schema:URL"},"repliesModerationOption":{"@id":"mz:repliesModerationOption","@type":"@vocab"},"contacts":{"@id":"mz:contacts","@type":"@id"}}],"actor":"http:\/\/example.org\/?author=1","id":"http:\/\/example.org\/?post_type=ap_outbox\u0026p=353","type":"Create","to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"object":{"id":"https:\/\/example.com\/3","type":"Event","content":"\u003Cp\u003EYou should not miss this Event!\u003C\/p\u003E","contentMap":{"en":"\u003Cp\u003EYou should not miss this Event!\u003C\/p\u003E"},"name":"WP Test Event","nameMap":{"en":"WP Test Event"},"endTime":"2030-02-29T17:00:00+01:00","location":[{"id":"https:\/\/example.com\/place\/1","type":"Place","attributedTo":"https:\/\/wp-test.event-federation.eu\/@test","name":"Fediverse Place","address":{"type":"PostalAddress","addressCountry":"FediCountry","addressLocality":"FediTown","postalCode":"1337","streetAddress":"FediStreet"}},{"type":"VirtualLocation","url":"https:\/\/example.com\/VirtualMeetingRoom"}],"startTime":"2030-02-29T16:00:00+01:00","to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"mediaType":"text\/html","tag":[],"timezone":"Europe\/Vienna","category":"MOVEMENTS_POLITICS","joinMode":"external"}}';
		return array(
			array(
				array(
					'@context' => 'https://www.w3.org/ns/activitystreams',
					'id'       => 'https://example.com/1',
					'type'     => 'Note',
					'content'  => '<p>This is a note</p>',
				),
				'Create',
				1,
				\str_replace( 'http:\/\/example.org', $home_url, $note1_json ),
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
				\str_replace( 'http:\/\/example.org', $home_url, $note2_json ),
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
				\str_replace( 'http:\/\/example.org', $home_url, $event_json ),
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
	 * Test that superseded outbox items are deleted.
	 *
	 * @covers ::delete_superseded_items
	 */
	public function test_delete_superseded_items() {
		$object        = $this->get_dummy_activity_object();
		$activity_type = 'Create';

		// Create first outbox item.
		$first_id = \Activitypub\add_to_outbox( $object, $activity_type, 1 );
		$this->assertNotFalse( $first_id );
		$this->assertEquals( 'pending', \get_post_status( $first_id ) );

		// Create second outbox item with same object_id and activity_type.
		$second_id = \Activitypub\add_to_outbox( $object, $activity_type, 1 );
		$this->assertNotFalse( $second_id );

		// First item should be deleted (superseded).
		$this->assertFalse( \get_post_status( $first_id ) );
		// New item should still be pending.
		$this->assertEquals( 'pending', \get_post_status( $second_id ) );
	}

	/**
	 * Test that only items with matching object_id and activity_type are deleted.
	 *
	 * @covers ::delete_superseded_items
	 */
	public function test_selective_superseding() {
		$object1 = $this->get_dummy_activity_object();
		$object2 = $this->get_dummy_activity_object();
		$object2->set_id( 'https://example.com/different-object' );

		// Create items with different combinations.
		$item1 = \Activitypub\add_to_outbox( $object1, 'Create', 1 ); // Should be deleted.
		$item2 = \Activitypub\add_to_outbox( $object2, 'Create', 1 ); // Should stay pending (different object).
		$item3 = \Activitypub\add_to_outbox( $object1, 'Update', 1 ); // Should stay pending (different activity).

		// Add new item that should trigger deletion of item1.
		$new_item = \Activitypub\add_to_outbox( $object1, 'Create', 1 );

		$this->assertFalse( \get_post_status( $item1 ) );
		$this->assertEquals( 'pending', \get_post_status( $item2 ) );
		$this->assertEquals( 'pending', \get_post_status( $item3 ) );
		$this->assertEquals( 'pending', \get_post_status( $new_item ) );
	}

	/**
	 * Test that Delete activities delete all pending items for the object.
	 *
	 * @covers ::delete_superseded_items
	 */
	public function test_delete_supersedes_all_activities() {
		$object = $this->get_dummy_activity_object();

		// Create items with different activity types.
		$create_id = \Activitypub\add_to_outbox( $object, 'Create', 1 );
		$update_id = \Activitypub\add_to_outbox( $object, 'Update', 1 );
		$like_id   = \Activitypub\add_to_outbox( $object, 'Like', 1 );

		$this->assertEquals( 'pending', \get_post_status( $create_id ) );
		$this->assertEquals( 'pending', \get_post_status( $update_id ) );
		$this->assertEquals( 'pending', \get_post_status( $like_id ) );

		// Add Delete activity.
		$delete_id = \Activitypub\add_to_outbox( $object, 'Delete', 1 );

		// All previous activities should be deleted (superseded).
		$this->assertFalse( \get_post_status( $create_id ) );
		$this->assertFalse( \get_post_status( $update_id ) );
		$this->assertFalse( \get_post_status( $like_id ) );
		// Delete activity should still be pending.
		$this->assertEquals( 'pending', \get_post_status( $delete_id ) );
	}

	/**
	 * Test get_object_id with different nested structures.
	 *
	 * @dataProvider data_provider_get_object_id
	 * @covers ::get_object_id
	 *
	 * @param Activity $activity The activity data to test.
	 * @param string   $expected The expected object ID.
	 */
	public function test_get_object_id( $activity, $expected ) {
		// Get the object ID using reflection since it's a private method.
		$get_object_id = new \ReflectionMethod( Outbox::class, 'get_object_id' );
		if ( \PHP_VERSION_ID < 80100 ) {
			$get_object_id->setAccessible( true );
		}

		$result = $get_object_id->invoke( null, $activity );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Data provider for test_get_object_id.
	 *
	 * @return array
	 */
	public function data_provider_get_object_id() {
		$create_with_id = Activity::init_from_array(
			array(
				'type'   => 'Create',
				'object' => array(
					'type' => 'Note',
					'id'   => 'https://example.com/note/123',
				),
			)
		);
		$create_no_id   = Activity::init_from_array(
			array(
				'type'   => 'Create',
				'object' => array(
					'type'    => 'Note',
					'content' => 'Test content',
				),
			)
		);

		return array(
			'object is a string'             => array(
				'activity' => Activity::init_from_array(
					array(
						'type'   => 'Create',
						'object' => 'https://example.com/note/123',
					)
				),
				'expected' => 'https://example.com/note/123',
			),
			'object is an object with id'    => array(
				'activity' => $create_with_id,
				'expected' => 'https://example.com/note/123',
			),
			'object is an object without id' => array(
				'activity' => $create_no_id,
				'expected' => null, // Will use activity ID as fallback.
			),
			'nested object with id'          => array(
				'activity' => Activity::init_from_array(
					array(
						'type'   => 'Announce',
						'object' => $create_with_id,
					)
				),
				'expected' => 'https://example.com/note/123',
			),
			'nested object without id'       => array(
				'activity' => Activity::init_from_array(
					array(
						'type'   => 'Announce',
						'object' => $create_no_id,
					)
				),
				'expected' => null, // Will use activity ID as fallback.
			),
			'activity with no object'        => array(
				'activity' => Activity::init_from_array(
					array(
						'type'  => 'Delete',
						'actor' => 'https://example.com/user/1',
					)
				),
				'expected' => 'https://example.com/user/1', // Will use actor as fallback.
			),
		);
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

		// Capture permalink before undo — Delete supersedes the original item.
		$permalink = \get_permalink( $id );

		$undo_id  = Outbox::undo( $id );
		$activity = Outbox::get_activity( $undo_id );

		// Only ID for Deletes.
		if ( 'Delete' === $expected ) {
			$this->assertSame( $permalink, $activity->get_object() );
		} else {
			$outbox_activity = \json_decode( \get_post( $undo_id )->post_content, true );
			$this->assertEquals( $outbox_activity['object'], $activity->get_object()->to_array( false ) );
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

	/**
	 * Test that Outbox::add() does not cause infinite recursion via Post::triage().
	 *
	 * When wp_insert_post() fires inside Outbox::add(), it triggers wp_after_insert_post,
	 * which could re-enter Post::triage() → add_to_outbox() → Outbox::add() in an infinite
	 * loop if the triage hook is not temporarily removed.
	 *
	 * @covers ::add
	 */
	public function test_add_does_not_recurse_via_post_triage() {
		// Ensure the triage hook is registered as a baseline.
		\add_action( 'wp_after_insert_post', array( \Activitypub\Scheduler\Post::class, 'triage' ), 33, 4 );

		$triage_hooked_during_insert = null;

		// Check whether Post::triage is hooked when wp_after_insert_post fires
		// during the outbox insert.
		\add_action(
			'wp_after_insert_post',
			function ( $post_id ) use ( &$triage_hooked_during_insert ) {
				if ( Outbox::POST_TYPE === \get_post_type( $post_id ) ) {
					$triage_hooked_during_insert = \has_action(
						'wp_after_insert_post',
						array( \Activitypub\Scheduler\Post::class, 'triage' )
					);
				}
			},
			0 // Run before priority 33 to inspect hook state.
		);

		$object = new Base_Object();
		$object->set_id( 'https://example.com/recursion-test' );
		$object->set_type( 'Note' );
		$object->set_content( '<p>Recursion test</p>' );

		$id = \Activitypub\add_to_outbox( $object, 'Create', self::$user_id );

		$this->assertIsInt( $id );
		$this->assertFalse( $triage_hooked_during_insert, 'Post::triage should be unhooked during Outbox::add() to prevent recursion.' );
	}

	/**
	 * Test that Outbox::add() restores the Post::triage hook after inserting.
	 *
	 * @covers ::add
	 */
	public function test_add_restores_triage_hook() {
		// Ensure the triage hook is registered as a baseline.
		\add_action( 'wp_after_insert_post', array( \Activitypub\Scheduler\Post::class, 'triage' ), 33, 4 );

		$object = new Base_Object();
		$object->set_id( 'https://example.com/restore-hook-test' );
		$object->set_type( 'Note' );
		$object->set_content( '<p>Hook restore test</p>' );

		\Activitypub\add_to_outbox( $object, 'Create', self::$user_id );

		// After add_to_outbox completes, the triage hook should be restored.
		$this->assertNotFalse(
			\has_action( 'wp_after_insert_post', array( \Activitypub\Scheduler\Post::class, 'triage' ) ),
			'Post::triage hook should be restored after Outbox::add() completes.'
		);
	}

	/**
	 * Helper method to create a dummy activity object for testing.
	 *
	 * @return Activity
	 */
	private function get_dummy_activity_object() {
		$object = new Activity();
		$object->set_id( 'https://example.com/test-object' );
		$object->set_type( 'Note' );
		$object->set_content( 'Test content' );

		return $object;
	}

	/**
	 * Test that Update activities have the updated attribute set.
	 *
	 * @covers ::get_activity
	 */
	public function test_update_activity_has_updated_attribute() {
		$object = $this->get_dummy_activity_object();
		$object->set_content( 'Original content' );

		// Create an Update activity.
		$id = \Activitypub\add_to_outbox( $object, 'Update', 1 );
		$this->assertNotFalse( $id );

		// Get the activity from the outbox.
		$activity = Outbox::get_activity( $id );
		$this->assertNotInstanceOf( \WP_Error::class, $activity );

		// Verify the updated attribute is set and matches the post's modified date.
		$post             = \get_post( $id );
		$expected_updated = \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( $post->post_modified ) );
		$this->assertEquals( $expected_updated, $activity->get_updated() );
	}

	/**
	 * Test purge method with more than 20 posts.
	 *
	 * @covers ::purge
	 */
	public function test_purge_more_than_20_posts() {
		// Create 20 old posts with activity type (will be deleted).
		self::factory()->post->create_many(
			20,
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-7 months' ) ),
				'meta_input'  => array(
					'_activitypub_activity_type' => 'Create',
				),
			)
		);

		// Create 5 new posts (will be kept).
		self::factory()->post->create_many(
			5,
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-1 month' ) ),
				'meta_input'  => array(
					'_activitypub_activity_type' => 'Create',
				),
			)
		);

		// Mock the count to exceed the 20-post threshold.
		$wp_count_posts_callback = function ( $counts, $type ) {
			if ( Outbox::POST_TYPE === $type ) {
				$counts->publish = 25;
			}
			return $counts;
		};
		\add_filter( 'wp_count_posts', $wp_count_posts_callback, 10, 2 );

		$deleted = Outbox::purge( 180 );
		\wp_cache_delete( \_count_posts_cache_key( Outbox::POST_TYPE ), 'counts' );

		\remove_filter( 'wp_count_posts', $wp_count_posts_callback );

		// Assert that 20 old posts were deleted.
		$this->assertEquals( 20, $deleted );

		// Verify 5 new posts remain.
		$remaining = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		$this->assertCount( 5, $remaining );
	}

	/**
	 * Test purge method with 20 or fewer posts.
	 *
	 * @covers ::purge
	 */
	public function test_purge_20_or_fewer_posts() {
		// Create 15 old posts.
		self::factory()->post->create_many(
			15,
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-1 year' ) ),
			)
		);

		$deleted = Outbox::purge( 180 );
		\wp_cache_delete( \_count_posts_cache_key( Outbox::POST_TYPE ), 'counts' );

		// Assert no posts were deleted (below threshold).
		$this->assertEquals( 0, $deleted );
		$this->assertEquals( 15, \wp_count_posts( Outbox::POST_TYPE )->publish );
	}

	/**
	 * Test purge method preserves Follow activities.
	 *
	 * @covers ::purge
	 */
	public function test_purge_preserves_follow_activities() {
		// Create old Follow activity (should be preserved).
		$follow_post_id = self::factory()->post->create(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-1 year' ) ),
			)
		);
		\update_post_meta( $follow_post_id, '_activitypub_activity_type', 'Follow' );

		// Create old Create activity (should be deleted).
		$create_post_id = self::factory()->post->create(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-1 year' ) ),
			)
		);
		\update_post_meta( $create_post_id, '_activitypub_activity_type', 'Create' );

		// Mock the count to exceed the 20-post threshold.
		$wp_count_posts_callback = function ( $counts, $type ) {
			if ( Outbox::POST_TYPE === $type ) {
				$counts->publish = 25;
			}
			return $counts;
		};
		\add_filter( 'wp_count_posts', $wp_count_posts_callback, 10, 2 );

		$deleted = Outbox::purge( 180 );
		\wp_cache_delete( \_count_posts_cache_key( Outbox::POST_TYPE ), 'counts' );

		\remove_filter( 'wp_count_posts', $wp_count_posts_callback );

		// Assert only 1 post was deleted (Create, not Follow).
		$this->assertEquals( 1, $deleted );

		// Follow activity should still exist.
		$this->assertNotNull( \get_post( $follow_post_id ) );

		// Create activity should be deleted.
		$this->assertNull( \get_post( $create_post_id ) );
	}

	/**
	 * Test purge method with different retention days.
	 *
	 * @covers ::purge
	 */
	public function test_purge_with_different_days() {
		// Create posts older than 60 days but newer than 30 days.
		self::factory()->post->create_many(
			10,
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'publish',
				'post_date'   => \gmdate( 'Y-m-d H:i:s', \strtotime( '-45 days' ) ),
				'meta_input'  => array(
					'_activitypub_activity_type' => 'Create',
				),
			)
		);

		// Mock the count to exceed threshold.
		$wp_count_posts_callback = function ( $counts, $type ) {
			if ( Outbox::POST_TYPE === $type ) {
				$counts->publish = 25;
			}
			return $counts;
		};
		\add_filter( 'wp_count_posts', $wp_count_posts_callback, 10, 2 );

		// Purge with 60 days retention - should not delete.
		$deleted = Outbox::purge( 60 );
		$this->assertEquals( 0, $deleted );

		// Purge with 30 days retention - should delete all.
		$deleted = Outbox::purge( 30 );
		\wp_cache_delete( \_count_posts_cache_key( Outbox::POST_TYPE ), 'counts' );

		\remove_filter( 'wp_count_posts', $wp_count_posts_callback );

		$this->assertEquals( 10, $deleted );
	}
}
