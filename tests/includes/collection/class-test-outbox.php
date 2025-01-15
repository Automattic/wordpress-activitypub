<?php
/**
 * Test file for Outbox collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

/**
 * Test class for Outbox collection.
 *
 * @coversDefaultClass \Activitypub\Collection\Outbox
 */
class Test_Outbox extends \Activitypub\Tests\ActivityPub_TestCase_Cache_HTTP {

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
		$id = \Activitypub\add_to_outbox( $data, $type, $user_id );

		$this->assertIsInt( $id );

		$post = get_post( $id );

		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'draft', $post->post_status );
		$this->assertEquals( $json, $post->post_content );
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
					'id'       => 'https://example.com/1',
					'type'     => 'Note',
					'content'  => 'This is a note',
				),
				'Create',
				1,
				'{"@context":["https://www.w3.org/ns/activitystreams",{"Hashtag":"as:Hashtag","sensitive":"as:sensitive"}],"id":"https://example.com/1","type":"Note","content":"This is a note","contentMap":{"en":"This is a note"},"tag":[],"to":["https://www.w3.org/ns/activitystreams#Public"],"cc":[],"mediaType":"text/html","sensitive":false}',
			),
			array(
				array(
					'@context' => 'https://www.w3.org/ns/activitystreams',
					'id'       => 'https://example.com/2',
					'type'     => 'Note',
					'content'  => 'This is another note',
				),
				'Create',
				2,
				'{"@context":["https://www.w3.org/ns/activitystreams",{"Hashtag":"as:Hashtag","sensitive":"as:sensitive"}],"id":"https://example.com/2","type":"Note","content":"This is another note","contentMap":{"en":"This is another note"},"tag":[],"to":["https://www.w3.org/ns/activitystreams#Public"],"cc":[],"mediaType":"text/html","sensitive":false}',
			),
		);
	}
}
