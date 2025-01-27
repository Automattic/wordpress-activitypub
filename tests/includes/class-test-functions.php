<?php
/**
 * Test file for Functions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Test class for Functions.
 */
class Test_Functions extends ActivityPub_TestCase_Cache_HTTP {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public $post_id;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		$this->post_id = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'test',
			)
		);
	}

	/**
	 * Test the get_remote_metadata_by_actor function.
	 *
	 * @covers ::get_remote_metadata_by_actor
	 */
	public function test_get_remote_metadata_by_actor() {
		$metadata = \ActivityPub\get_remote_metadata_by_actor( 'pfefferle@notiz.blog' );
		$this->assertEquals( 'https://notiz.blog/author/matthias-pfefferle/', $metadata['url'] );
		$this->assertEquals( 'pfefferle', $metadata['preferredUsername'] );
		$this->assertEquals( 'Matthias Pfefferle', $metadata['name'] );
	}

	/**
	 * Test object_id_to_comment.
	 *
	 * @covers ::object_id_to_comment
	 */
	public function test_object_id_to_comment_basic() {
		$single_comment_source_id = 'https://example.com/single';
		$content                  = 'example comment that has bunch of text';
		$comment_id               = \wp_new_comment(
			array(
				'comment_post_ID'      => $this->post_id,
				'comment_author'       => 'Example User',
				'comment_author_url'   => 'https://example.com/user',
				'comment_content'      => $content,
				'comment_type'         => '',
				'comment_author_email' => '',
				'comment_parent'       => 0,
				'comment_meta'         => array(
					'source_id'  => $single_comment_source_id,
					'source_url' => 'https://example.com/123',
					'avatar_url' => 'https://example.com/icon',
					'protocol'   => 'activitypub',
				),
			),
			true
		);
		$query_result             = \Activitypub\object_id_to_comment( $single_comment_source_id );
		$this->assertInstanceOf( \WP_Comment::class, $query_result );
		$this->assertEquals( $comment_id, $query_result->comment_ID );
		$this->assertEquals( $content, $query_result->comment_content );
	}

	/**
	 * Test object_id_to_comment with invalid source ID.
	 *
	 * @covers ::object_id_to_comment
	 */
	public function test_object_id_to_comment_none() {
		$single_comment_source_id = 'https://example.com/none';
		$query_result             = \Activitypub\object_id_to_comment( $single_comment_source_id );
		$this->assertFalse( $query_result );
	}

	/**
	 * Test object_id_to_comment with duplicate source ID.
	 *
	 * @covers ::object_id_to_comment
	 */
	public function test_object_id_to_comment_duplicate() {
		$duplicate_comment_source_id = 'https://example.com/duplicate';

		add_filter( 'duplicate_comment_id', '__return_zero', 99 );
		add_filter( 'wp_is_comment_flood', '__return_false', 99 );
		for ( $i = 0; $i < 2; ++$i ) {
			\wp_new_comment(
				array(
					'comment_post_ID'      => $this->post_id,
					'comment_author'       => 'Example User',
					'comment_author_url'   => 'https://example.com/user',
					'comment_content'      => 'example comment',
					'comment_type'         => '',
					'comment_author_email' => '',
					'comment_parent'       => 0,
					'comment_meta'         => array(
						'source_id'  => $duplicate_comment_source_id,
						'source_url' => 'https://example.com/123',
						'avatar_url' => 'https://example.com/icon',
						'protocol'   => 'activitypub',
					),
				),
				true
			);
		}
		remove_filter( 'duplicate_comment_id', '__return_zero', 99 );
		remove_filter( 'wp_is_comment_flood', '__return_false', 99 );

		$query_result = \Activitypub\object_id_to_comment( $duplicate_comment_source_id );
		$this->assertFalse( $query_result );
	}

	/**
	 * Test object_to_uri.
	 *
	 * @dataProvider object_to_uri_provider
	 * @covers ::object_to_uri
	 *
	 * @param mixed $input  The input to test.
	 * @param mixed $output The expected output.
	 */
	public function test_object_to_uri( $input, $output ) {
		$this->assertEquals( $output, \Activitypub\object_to_uri( $input ) );
	}

	/**
	 * Test is_self_ping.
	 *
	 * @covers ::is_self_ping
	 */
	public function test_is_self_ping() {
		$this->assertFalse( \Activitypub\is_self_ping( 'https://example.org' ) );
		$this->assertFalse( \Activitypub\is_self_ping( 'https://example.com' ) );
		$this->assertTrue( \Activitypub\is_self_ping( 'https://example.org/?c=123' ) );
		$this->assertFalse( \Activitypub\is_self_ping( 'https://example.com/?c=123' ) );
	}

	/**
	 * Data provider for test_object_to_uri.
	 *
	 * @return array[]
	 */
	public function object_to_uri_provider() {
		return array(
			array( null, null ),
			array( 'https://example.com', 'https://example.com' ),
			array( array( 'https://example.com' ), 'https://example.com' ),
			array(
				array(
					'https://example.com',
					'https://example.org',
				),
				'https://example.com',
			),
			array(
				array(
					'type' => 'Link',
					'href' => 'https://example.com',
				),
				'https://example.com',
			),
			array(
				array(
					array(
						'type' => 'Link',
						'href' => 'https://example.com',
					),
					array(
						'type' => 'Link',
						'href' => 'https://example.org',
					),
				),
				'https://example.com',
			),
			array(
				array(
					'type' => 'Actor',
					'id'   => 'https://example.com',
				),
				'https://example.com',
			),
			array(
				array(
					array(
						'type' => 'Actor',
						'id'   => 'https://example.com',
					),
					array(
						'type' => 'Actor',
						'id'   => 'https://example.org',
					),
				),
				'https://example.com',
			),
			array(
				array(
					'type' => 'Activity',
					'id'   => 'https://example.com',
				),
				'https://example.com',
			),
		);
	}

	/**
	 * Test is_activity with array input.
	 *
	 * @covers ::is_activity
	 */
	public function test_is_activity_with_array() {
		// Test valid activity types.
		$valid_activities = array(
			array( 'type' => 'Create' ),
			array( 'type' => 'Update' ),
			array( 'type' => 'Delete' ),
			array( 'type' => 'Follow' ),
			array( 'type' => 'Accept' ),
			array( 'type' => 'Reject' ),
			array( 'type' => 'Add' ),
			array( 'type' => 'Remove' ),
			array( 'type' => 'Like' ),
			array( 'type' => 'Announce' ),
			array( 'type' => 'Undo' ),
		);

		foreach ( $valid_activities as $activity ) {
			$this->assertTrue(
				\Activitypub\is_activity( $activity ),
				sprintf( 'Activity type %s should be valid', $activity['type'] )
			);
		}

		// Test invalid activity types.
		$invalid_activities = array(
			array( 'type' => 'Note' ),
			array( 'type' => 'Article' ),
			array( 'type' => 'Person' ),
			array( 'type' => 'Image' ),
			array( 'type' => 'Video' ),
			array( 'type' => 'Audio' ),
			array( 'type' => '' ),
			array( 'type' => null ),
			array(),
		);

		foreach ( $invalid_activities as $activity ) {
			$this->assertFalse(
				\Activitypub\is_activity( $activity ),
				sprintf( 'Activity type %s should be invalid', isset( $activity['type'] ) ? $activity['type'] : 'null' )
			);
		}
	}

	/**
	 * Test is_activity with object input.
	 *
	 * @covers ::is_activity
	 */
	public function test_is_activity_with_object() {
		// Test Activity object.
		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( 'Create' );
		$this->assertTrue( \Activitypub\is_activity( $activity ), 'Activity object should be valid' );

		// Test Base_Object.
		$object = new \Activitypub\Activity\Base_Object();
		$object->set_type( 'Note' );
		$this->assertFalse( \Activitypub\is_activity( $object ), 'Base_Object should be invalid' );

		// Test with custom filter.
		add_filter(
			'activitypub_activity_types',
			function ( $types ) {
				$types[] = 'CustomActivity';
				return $types;
			}
		);

		$activity = new \Activitypub\Activity\Activity();
		$activity->set_type( 'CustomActivity' );
		$this->assertTrue(
			\Activitypub\is_activity( $activity ),
			'Custom activity type should be valid after filter'
		);

		remove_all_filters( 'activitypub_activity_types' );
	}

	/**
	 * Test is_activity with invalid input.
	 *
	 * @covers ::is_activity
	 */
	public function test_is_activity_with_invalid_input() {
		$invalid_inputs = array(
			'string',
			123,
			true,
			false,
			null,
			new \stdClass(),
		);

		foreach ( $invalid_inputs as $input ) {
			$this->assertFalse(
				\Activitypub\is_activity( $input ),
				sprintf( 'Input of type %s should be invalid', gettype( $input ) )
			);
		}
	}
}
