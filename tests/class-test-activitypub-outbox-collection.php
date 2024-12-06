<?php
/**
 * Test file for Activitypub Outbox-Collection.
 *
 * @package Activitypub
 */

/**
 * Test class for Activitypub Outbox-Collection.
 *
 * @coversDefaultClass \Activitypub\Collection\Outbox
 */
class Test_Activitypub_Outbox_Collection extends ActivityPub_TestCase_Cache_HTTP {

	/**
	 * Test add an item to the outbox.
	 *
	 * @covers ::add
	 *
	 * @dataProvider activity_object_provider
	 */
	public function asd_test_add( $data, $type, $user_id, $json ) {
		$id = \Activitypub\add_to_outbox( $data, $type, $user_id );

		$this->assertIsInt( $id );

		$post = get_post( $id );

		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'draft', $post->post_status );
		//$this->assertEquals( $json, $post->post_content );
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
				'{"@context":"https:\/\/www.w3.org\/ns\/activitystreams","id":"https:\/\/example.com\/1","type":"Note","content":"This is a note"}',
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
				'{"@context":"https:\/\/www.w3.org\/ns\/activitystreams","id":"https:\/\/example.com\/2","type":"Note","content":"This is another note"}',
			),
		);
	}
}
