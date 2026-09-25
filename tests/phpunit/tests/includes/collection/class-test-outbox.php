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
use Activitypub\OAuth\Scope;
use Activitypub\Tests\OAuth_Token_Stub;

/**
 * Test class for Outbox collection.
 *
 * @coversDefaultClass \Activitypub\Collection\Outbox
 */
class Test_Outbox extends \Activitypub\Tests\ActivityPub_Outbox_TestCase {
	use OAuth_Token_Stub;

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
		$expected = json_decode( $json, true );
		if ( isset( $expected['id'] ) ) {
			$expected['id'] = add_query_arg( 'p', $id, $expected['id'] );
		}

		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'pending', $post->post_status );

		// Decode the actual output and assert context separately to avoid
		// breaking the test whenever the JSON-LD context changes.
		$actual = json_decode( $post->post_content, true );

		// Assert the context is correct. For fixtures without @context, the actual output should
		// have Base_Object's context. For fixtures with @context, assert it matches exactly.
		if ( ! isset( $expected['@context'] ) ) {
			$this->assertSame( Base_Object::JSON_LD_CONTEXT, $actual['@context'] );
		} else {
			$this->assertSame( $expected['@context'], $actual['@context'] );
		}

		// Remove context from both arrays and compare the rest with order-sensitive comparison
		// (assertEquals maintains array key order, unlike assertEqualsCanonicalizing).
		$this->assertNotEmpty( $actual['published'] );

		unset( $expected['@context'], $actual['@context'], $expected['published'], $actual['published'] );
		$this->assertEquals( $expected, $actual );

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

		$note1_json = '{"actor":"http:\/\/example.org\/?author=1","id":"http:\/\/example.org\/?post_type=ap_outbox&p=351","type":"Create","to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"object":{"id":"https:\/\/example.com\/1","type":"Note","content":"<p>This is a note<\/p>","contentMap":{"en":"<p>This is a note<\/p>"},"tag":[],"to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"mediaType":"text\/html"}}';
		$note2_json = '{"actor":"http:\/\/example.org\/?author=0","id":"http:\/\/example.org\/?post_type=ap_outbox&p=352","type":"Create","to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"object":{"id":"https:\/\/example.com\/2","type":"Note","content":"<p>This is another note<\/p>","contentMap":{"en":"<p>This is another note<\/p>"},"tag":[],"to":["https:\/\/www.w3.org\/ns\/activitystreams#Public"],"mediaType":"text\/html"}}';
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
	 * Test that a fresh Create activity invalidates any pending Delete for the same object.
	 *
	 * Covers the soft-delete re-publish race: a post is moved to draft (Delete scheduled),
	 * then re-published before the Delete has fired. The Delete must be invalidated so we
	 * do not send both Delete and Create.
	 *
	 * @covers ::delete_superseded_items
	 */
	public function test_create_supersedes_pending_delete() {
		$object = $this->get_dummy_activity_object();

		$delete_id = \Activitypub\add_to_outbox( $object, 'Delete', 1 );
		$this->assertEquals( 'pending', \get_post_status( $delete_id ) );

		$create_id = \Activitypub\add_to_outbox( $object, 'Create', 1 );

		$this->assertFalse( \get_post_status( $delete_id ), 'Pending Delete should be invalidated by a fresh Create.' );
		$this->assertEquals( 'pending', \get_post_status( $create_id ) );
	}

	/**
	 * Test that an Update activity does NOT cancel a pending Delete for the same object.
	 *
	 * External callers (CLI commands, third-party plugins, filters) can queue an Update
	 * for a soft-deleted object that is still hidden. Cancelling the Delete on every
	 * Update would silently resurrect federation while the post remains draft/private/
	 * password-protected. Only a confirmed Create (the scheduler's resurrection path)
	 * should cancel the Delete.
	 *
	 * @covers ::delete_superseded_items
	 */
	public function test_update_does_not_supersede_pending_delete() {
		$object = $this->get_dummy_activity_object();

		$delete_id = \Activitypub\add_to_outbox( $object, 'Delete', 1 );
		$this->assertEquals( 'pending', \get_post_status( $delete_id ) );

		$update_id = \Activitypub\add_to_outbox( $object, 'Update', 1 );

		$this->assertEquals( 'pending', \get_post_status( $delete_id ), 'Pending Delete must survive a manual Update for the same object.' );
		$this->assertEquals( 'pending', \get_post_status( $update_id ) );
	}

	/**
	 * Test that a Delete also wipes already-sent (non-pending) outbox history for the object.
	 *
	 * A sent Create/Update remains in the outbox with post_status='publish' as a record of
	 * what was federated. When a Delete is then queued, that record is now stale and a
	 * redelivery retry could resurrect the very content we are tearing down.
	 *
	 * @covers ::delete_superseded_items
	 */
	public function test_delete_supersedes_already_sent_activities() {
		$object = $this->get_dummy_activity_object();

		// Simulate a previously-sent Create activity (post_status='publish').
		$create_id = \Activitypub\add_to_outbox( $object, 'Create', 1 );
		\wp_update_post(
			array(
				'ID'          => $create_id,
				'post_status' => 'publish',
			)
		);
		$this->assertEquals( 'publish', \get_post_status( $create_id ) );

		// Now queue a Delete.
		$delete_id = \Activitypub\add_to_outbox( $object, 'Delete', 1 );

		$this->assertFalse(
			\get_post_status( $create_id ),
			'Already-sent Create must be wiped when a Delete supersedes the object.'
		);
		$this->assertEquals( 'pending', \get_post_status( $delete_id ) );
	}

	/**
	 * Test that Delete wipes more than get_posts()' default five matching outbox items.
	 *
	 * @covers ::delete_superseded_items
	 */
	public function test_delete_supersedes_all_matching_outbox_history() {
		$object = $this->get_dummy_activity_object();
		$ids    = array();

		for ( $i = 0; $i < 7; $i++ ) {
			$id = \Activitypub\add_to_outbox( $object, 'Create', 1 );
			\wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'publish',
				)
			);

			$ids[] = $id;
		}

		$delete_id = \Activitypub\add_to_outbox( $object, 'Delete', 1 );

		foreach ( $ids as $id ) {
			$this->assertFalse(
				\get_post_status( $id ),
				'Delete must wipe every stale outbox snapshot for the object, not only the first five.'
			);
		}

		$this->assertEquals( 'pending', \get_post_status( $delete_id ) );
	}

	/**
	 * Test that non-republish activities (Like, Add, Remove, Undo) do NOT cancel a pending Delete.
	 *
	 * A soft-deleted post can still have unrelated activities queued (e.g. an Add to the
	 * featured collection from a sticky transition). Those must not invalidate the Delete,
	 * otherwise the remote copy would never be torn down.
	 *
	 * @dataProvider data_non_republish_activity_types
	 *
	 * @covers ::delete_superseded_items
	 *
	 * @param string $activity_type Activity type that should NOT cancel a pending Delete.
	 */
	public function test_non_republish_activity_does_not_cancel_pending_delete( $activity_type ) {
		$object = $this->get_dummy_activity_object();

		$delete_id = \Activitypub\add_to_outbox( $object, 'Delete', 1 );
		$this->assertEquals( 'pending', \get_post_status( $delete_id ) );

		$other_id = \Activitypub\add_to_outbox( $object, $activity_type, 1 );

		$this->assertEquals(
			'pending',
			\get_post_status( $delete_id ),
			"Pending Delete must survive a {$activity_type} for the same object."
		);

		if ( $other_id && ! \is_wp_error( $other_id ) ) {
			$this->assertEquals( 'pending', \get_post_status( $other_id ) );
		}
	}

	/**
	 * Data provider: activity types that are orthogonal to the soft-delete lifecycle.
	 *
	 * Excludes Follow / Announce / Accept / Reject because delete_superseded_items
	 * short-circuits on those before the meta_query is built.
	 *
	 * @return array[]
	 */
	public function data_non_republish_activity_types() {
		return array(
			'Like'   => array( 'Like' ),
			'Add'    => array( 'Add' ),
			'Remove' => array( 'Remove' ),
			'Undo'   => array( 'Undo' ),
		);
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
	 * Test that the blind audience (`bto`/`bcc`) is persisted to the database and
	 * survives the `add` → `get_activity` roundtrip.
	 *
	 * @covers ::add
	 * @covers ::get_activity
	 */
	public function test_add_persists_blind_audience() {
		$activity = new Activity();
		$activity->set_type( 'Create' );
		$activity->set_actor( 'https://example.com/author/test' );
		$activity->set_object(
			array(
				'id'      => 'https://example.com/note/123',
				'type'    => 'Note',
				'content' => 'Hello',
			)
		);
		$activity->set_to( array( 'https://www.w3.org/ns/activitystreams#Public' ) );
		$activity->set_cc( array() );
		$activity->set_bto( array( 'https://example.com/users/secret' ) );
		$activity->set_bcc( array( 'https://example.com/users/hidden' ) );

		$id = Outbox::add( $activity, self::$user_id );

		$this->assertIsInt( $id );

		/* Stored JSON in the DB must keep the blind audience. */
		$stored = \json_decode( \get_post( $id )->post_content, true );
		$this->assertSame( array( 'https://example.com/users/secret' ), $stored['bto'], 'bto persisted to DB' );
		$this->assertSame( array( 'https://example.com/users/hidden' ), $stored['bcc'], 'bcc persisted to DB' );

		/* Rehydrated Activity must expose the blind audience via its getters. */
		$reloaded = Outbox::get_activity( $id );
		$this->assertInstanceOf( Activity::class, $reloaded );
		$this->assertSame( array( 'https://example.com/users/secret' ), $reloaded->get_bto(), 'bto exposed after reload' );
		$this->assertSame( array( 'https://example.com/users/hidden' ), $reloaded->get_bcc(), 'bcc exposed after reload' );
	}

	/**
	 * Stored activity that already has a `published` value is not overwritten.
	 *
	 * @covers ::get_activity
	 */
	public function test_get_activity_preserves_existing_published() {
		$object = $this->get_dummy_activity_object();
		$id     = \Activitypub\add_to_outbox( $object, 'Create', 1 );
		$this->assertNotFalse( $id );

		$frozen           = '2020-01-02T03:04:05Z';
		$post             = \get_post( $id );
		$raw              = \json_decode( $post->post_content, true );
		$raw['published'] = $frozen;
		\wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => \wp_slash( \wp_json_encode( $raw ) ),
			)
		);

		$activity = Outbox::get_activity( $id );
		$this->assertEquals( $frozen, $activity->get_published() );
	}

	/**
	 * Every activity is stamped with a publication date when it is queued, in UTC.
	 *
	 * The row describes itself from then on, so nothing has to derive a date when it is read.
	 *
	 * @covers ::add
	 */
	public function test_add_stamps_published() {
		\update_option( 'timezone_string', 'Europe/Berlin' );

		$before = \gmdate( ACTIVITYPUB_DATE_TIME_RFC3339 );
		$id     = \Activitypub\add_to_outbox( $this->get_dummy_activity_object(), 'Create', 1 );
		$after  = \gmdate( ACTIVITYPUB_DATE_TIME_RFC3339 );

		\delete_option( 'timezone_string' );

		$this->assertNotFalse( $id );

		$stored = \json_decode( \get_post( $id )->post_content, true );

		$this->assertNotEmpty( $stored['published'], 'The stored activity carries its own date.' );
		$this->assertGreaterThanOrEqual( $before, $stored['published'], 'The date is UTC, not the site timezone.' );
		$this->assertLessThanOrEqual( $after, $stored['published'] );
	}

	/**
	 * An Update is stamped with the time it was queued, overriding an older date from the post.
	 *
	 * An Update sent for a reason other than an edit, a quote authorization arriving for instance,
	 * would otherwise repeat the date of the last content change.
	 *
	 * @covers ::add
	 */
	public function test_add_stamps_updated_on_an_update() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => 1,
				'post_status' => 'publish',
				'post_date'   => '2026-01-01 10:00:00',
			)
		);

		// An edit long in the past, which is what the transformer would report.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_modified'     => '2026-01-01 12:00:00',
				'post_modified_gmt' => '2026-01-01 12:00:00',
			),
			array( 'ID' => $post_id )
		);
		\clean_post_cache( $post_id );

		$id = \Activitypub\add_to_outbox( \get_post( $post_id ), 'Update', 1 );
		$this->assertNotFalse( $id );

		$stored = \json_decode( \get_post( $id )->post_content, true );

		$this->assertNotSame( '2026-01-01T12:00:00Z', $stored['updated'], 'The Update reports when it was queued.' );
		$this->assertSame( '2026-01-01T12:00:00Z', $stored['object']['updated'], "The object keeps the post's own edit time." );
	}

	/**
	 * Only what the row stores is reported, never anything derived from its date columns.
	 *
	 * @covers ::get_activity
	 */
	public function test_get_activity_derives_no_dates_from_the_row() {
		$id = \Activitypub\add_to_outbox( $this->get_dummy_activity_object(), 'Create', 1 );
		$this->assertNotFalse( $id );

		$raw = \json_decode( \get_post( $id )->post_content, true );
		unset( $raw['published'] );

		\wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => \wp_slash( \wp_json_encode( $raw ) ),
			)
		);

		$activity = Outbox::get_activity( $id );

		$this->assertEmpty( $activity->get_published(), 'Nothing is read from post_date.' );
		$this->assertEmpty( $activity->get_updated(), 'Nothing is read from post_modified.' );
	}

	/**
	 * A pending row must still carry a GMT publication date.
	 *
	 * WordPress only derives `post_date_gmt` for statuses that do not float, and wp_publish_post()
	 * never repairs it, so without supplying it the row would report the sentinel for life and every
	 * reader would compare a local date against a GMT one.
	 *
	 * @covers ::add
	 */
	public function test_add_populates_the_gmt_date_of_a_pending_row() {
		\update_option( 'timezone_string', 'Europe/Berlin' );

		$id = \Activitypub\add_to_outbox( $this->get_dummy_activity_object(), 'Create', 1 );
		$this->assertNotFalse( $id );

		$post = \get_post( $id );

		// Convert while the site timezone is still set, or get_gmt_from_date() is a no-op.
		$expected = \get_gmt_from_date( $post->post_date );

		\delete_option( 'timezone_string' );

		$this->assertSame( 'pending', $post->post_status );
		$this->assertNotSame( '0000-00-00 00:00:00', $post->post_date_gmt );
		$this->assertSame( $expected, $post->post_date_gmt, "The row's two clocks must agree." );
	}

	/**
	 * Rescheduling moves the publication date, so both of its columns move together.
	 *
	 * @covers ::reschedule
	 */
	public function test_reschedule_moves_both_date_columns() {
		\update_option( 'timezone_string', 'Europe/Berlin' );

		$id = \Activitypub\add_to_outbox( $this->get_dummy_activity_object(), 'Create', 1 );
		$this->assertNotFalse( $id );

		// Backdate the row, and leave the GMT column on the sentinel an upgraded site still carries.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->posts,
			array(
				'post_date'     => '2026-01-01 00:00:00',
				'post_date_gmt' => '0000-00-00 00:00:00',
			),
			array( 'ID' => $id )
		);
		\clean_post_cache( $id );

		Outbox::reschedule( $id );
		\clean_post_cache( $id );

		$post = \get_post( $id );

		// Convert while the site timezone is still set, or get_gmt_from_date() is a no-op.
		$expected = \get_gmt_from_date( $post->post_date );

		\delete_option( 'timezone_string' );

		$this->assertNotSame( '2026-01-01 00:00:00', $post->post_date, 'The reschedule must move the date.' );
		$this->assertNotSame( '0000-00-00 00:00:00', $post->post_date_gmt, 'The reschedule must repair the GMT column.' );
		$this->assertSame( $expected, $post->post_date_gmt, "The row's two clocks must agree." );
	}

	/**
	 * A retraction does not inherit the publication date of what it retracts.
	 *
	 * @covers ::undo
	 */
	public function test_undo_does_not_inherit_the_retracted_date() {
		$id = \Activitypub\add_to_outbox( $this->get_dummy_activity_object(), 'Create', 1 );
		$this->assertNotFalse( $id );

		// A date from long before the retraction, the way a year-old Follow would carry one.
		$post             = \get_post( $id );
		$raw              = \json_decode( $post->post_content, true );
		$raw['published'] = '2024-05-06T07:08:09Z';

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $wpdb->posts, array( 'post_content' => \wp_json_encode( $raw ) ), array( 'ID' => $id ) );
		\clean_post_cache( $id );

		$undo_id = Outbox::undo( $id );
		$this->assertNotFalse( $undo_id );
		$this->assertNotWPError( $undo_id );

		$undo = Outbox::get_activity( $undo_id );

		$this->assertNotSame( '2024-05-06T07:08:09Z', $undo->get_published(), 'The retraction must not claim the retracted activity\'s date.' );
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

	/**
	 * Create a private outbox item authored by the blog actor for the tests below.
	 *
	 * @return \WP_Post The created outbox post.
	 */
	private function create_private_blog_actor_outbox_item() {
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => 0,
				'post_type'    => Outbox::POST_TYPE,
				'post_status'  => 'pending',
				'post_content' => \wp_json_encode(
					array(
						'@context' => array( 'https://www.w3.org/ns/activitystreams' ),
						'id'       => 'https://example.org/activity/private-accept',
						'type'     => 'Accept',
						'actor'    => 'https://example.org/blog',
						'object'   => 'https://example.org/follow/1',
					)
				),
				'meta_input'   => array(
					'_activitypub_activity_type'     => 'Accept',
					'_activitypub_activity_actor'    => 'blog',
					'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE,
				),
			)
		);

		return \get_post( $post_id );
	}

	/**
	 * Test that anonymous visitors cannot fetch a private blog-actor outbox item by permalink.
	 *
	 * Regression test for the `0 === 0` identity match: when `post_author` is `0`
	 * (blog actor) and the visitor is unauthenticated, `get_current_user_id()` also
	 * returns `0`, so the author bypass at the top of `maybe_get_activity()` would
	 * return private items without an `is_user_logged_in()` guard.
	 *
	 * @covers ::maybe_get_activity
	 */
	public function test_maybe_get_activity_anonymous_cannot_read_private_blog_actor_item() {
		$post = $this->create_private_blog_actor_outbox_item();

		\wp_set_current_user( 0 );

		$result = Outbox::maybe_get_activity( $post );

		$this->assertWPError( $result );
		$this->assertEquals( 'private_outbox_item', $result->get_error_code() );
	}

	/**
	 * Test that administrators can fetch a private blog-actor outbox item by permalink.
	 *
	 * Mirrors the `verify_owner` blog-actor capability bypass: a user authorized to
	 * act as the blog actor reads the same private outbox they can post to.
	 *
	 * @covers ::maybe_get_activity
	 */
	public function test_maybe_get_activity_admin_can_read_private_blog_actor_item() {
		$post = $this->create_private_blog_actor_outbox_item();

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $admin_id );

		$result = Outbox::maybe_get_activity( $post );

		$this->assertInstanceOf( Activity::class, $result );
	}

	/**
	 * Test that an OAuth owner needs the read scope to fetch a private outbox item by permalink.
	 *
	 * @covers ::maybe_get_activity
	 */
	public function test_maybe_get_activity_oauth_owner_requires_read_scope() {
		$post = $this->create_private_blog_actor_outbox_item();

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $admin_id );

		foreach ( array( array( Scope::PUSH ), array( Scope::WRITE ) ) as $scopes ) {
			$this->set_oauth_current_token( $this->mock_oauth_token( $scopes, $admin_id ) );

			$result = Outbox::maybe_get_activity( $post );

			$this->assertWPError( $result, \implode( ',', $scopes ) . ' must not read a private item.' );
			$this->assertEquals( 'private_outbox_item', $result->get_error_code() );
		}

		$this->set_oauth_current_token( $this->mock_oauth_token( array( Scope::READ ), $admin_id ) );
		$this->assertInstanceOf( Activity::class, Outbox::maybe_get_activity( $post ) );
	}

	/**
	 * Test that an activity with a list of objects can be added.
	 *
	 * A list stays an array in the Activity, so the title lookup must not treat it as an object.
	 *
	 * @covers ::add
	 */
	public function test_add_with_list_of_objects() {
		$activity = new Activity();
		$activity->set_type( 'Add' );
		$activity->set_id( 'https://example.com/activities/list-of-objects' );
		$activity->set_object(
			array(
				'https://example.com/notes/1',
				'https://example.com/notes/2',
			)
		);

		$this->assertIsArray( $activity->get_object(), 'The object list stays an array.' );

		$id = Outbox::add( $activity, self::$user_id );

		$this->assertIsInt( $id );
	}
}
