<?php
/**
 * Test file for Activitypub Comment.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Comment;

/**
 * Test class for Activitypub Comment.
 *
 * @coversDefaultClass \Activitypub\Comment
 */
class Test_Comment extends \WP_UnitTestCase {

	/**
	 * Test get source id or url.
	 *
	 * @covers ::get_source_id, ::get_source_url
	 */
	public function test_get_source_id_or_url() {
		$comment_id = wp_insert_comment(
			array(
				'comment_type'         => 'comment id',
				'comment_content'      => 'This is a comment id test',
				'comment_author_url'   => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol'  => 'activitypub',
					'source_id' => 'https://example.com/id',
				),
			)
		);

		$this->assertEquals( 'https://example.com/id', Comment::get_source_url( $comment_id ) );
		$this->assertEquals( 'https://example.com/id', Comment::get_source_id( $comment_id ) );
		$this->assertEquals( 'https://example.com/id', Comment::get_source_id( $comment_id, false ) );
		$this->assertEquals( null, Comment::get_source_url( $comment_id, false ) );

		$comment_id = wp_insert_comment(
			array(
				'comment_type'         => 'comment url',
				'comment_content'      => 'This is a comment url test',
				'comment_author_url'   => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol'   => 'activitypub',
					'source_url' => 'https://example.com/url',
				),
			)
		);

		$this->assertEquals( 'https://example.com/url', Comment::get_source_id( $comment_id ) );
		$this->assertEquals( 'https://example.com/url', Comment::get_source_url( $comment_id ) );
		$this->assertEquals( 'https://example.com/url', Comment::get_source_url( $comment_id, false ) );
		$this->assertEquals( null, Comment::get_source_id( $comment_id, false ) );

		$comment_id = wp_insert_comment(
			array(
				'comment_type'         => 'comment url and id',
				'comment_content'      => 'This is a comment url and id test',
				'comment_author_url'   => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol'   => 'activitypub',
					'source_url' => 'https://example.com/url',
					'source_id'  => 'https://example.com/id',
				),
			)
		);

		$this->assertEquals( 'https://example.com/id', Comment::get_source_id( $comment_id ) );
		$this->assertEquals( 'https://example.com/id', Comment::get_source_id( $comment_id, false ) );
		$this->assertEquals( 'https://example.com/url', Comment::get_source_url( $comment_id ) );
		$this->assertEquals( 'https://example.com/url', Comment::get_source_url( $comment_id, false ) );
	}

	/**
	 * Test ability to federate comment.
	 *
	 * @dataProvider ability_to_federate_comment
	 *
	 * @param array $comment  Comment data.
	 * @param array $expected Expected result.
	 */
	public function test_check_ability_to_federate_comment( $comment, $expected ) {
		$comment_id = wp_insert_comment( $comment );
		$comment    = get_comment( $comment_id );

		$this->assertEquals( $expected['was_sent'], Comment::was_sent( $comment ) );
		$this->assertEquals( $expected['was_received'], Comment::was_received( $comment ) );
		$this->assertEquals( $expected['should_be_federated'], Comment::should_be_federated( $comment ) );
	}

	/**
	 * Test ability to federate threaded comment.
	 *
	 * @dataProvider ability_to_federate_threaded_comment
	 *
	 * @param array $parent_comment Parent comment data.
	 * @param array $comment Comment data.
	 * @param array $expected Expected result.
	 */
	public function test_check_ability_to_federate_threaded_comment( $parent_comment, $comment, $expected ) {
		$parent_comment_id         = wp_insert_comment( $parent_comment );
		$comment['comment_parent'] = $parent_comment_id;
		$comment_id                = wp_insert_comment( $comment );
		$comment                   = get_comment( $comment_id );

		$this->assertEquals( $expected['was_sent'], Comment::was_sent( $parent_comment_id ) );
		$this->assertEquals( $expected['was_received'], Comment::was_received( $parent_comment_id ) );
		$this->assertEquals( $expected['should_be_federated'], Comment::should_be_federated( $comment ) );
	}

	/**
	 * Test get comment ancestors.
	 *
	 * @covers ::get_comment_ancestors
	 */
	public function test_get_comment_ancestors() {
		$comment_id = wp_insert_comment(
			array(
				'comment_type'         => 'comment',
				'comment_content'      => 'This is a comment.',
				'comment_author_url'   => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$this->assertEquals( array(), \Activitypub\get_comment_ancestors( $comment_id ) );

		$comment_array = get_comment( $comment_id, ARRAY_A );

		$parent_comment_id = wp_insert_comment(
			array(
				'comment_type'         => 'parent comment',
				'comment_content'      => 'This is a parent comment.',
				'comment_author_url'   => 'https://example.com',
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$comment_array['comment_parent'] = $parent_comment_id;

		wp_update_comment( $comment_array );

		$this->assertEquals( array( $parent_comment_id ), \Activitypub\get_comment_ancestors( $comment_id ) );
	}

	/**
	 * Test pre_comment_approved.
	 *
	 * @covers ::pre_comment_approved
	 */
	public function test_pre_comment_approved() {
		// Disable flood control.
		\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		$post_id = \wp_insert_post(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'This is a test post.',
				'post_status'  => 'publish',
			)
		);

		$comment_id_to_approve = \wp_new_comment(
			array(
				'comment_type'         => 'comment',
				'comment_content'      => 'This is a comment to approve.',
				'comment_author'       => 'Approved',
				'comment_author_url'   => 'https://example.com/@approved',
				'comment_post_ID'      => $post_id,
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$comment_to_approve = \get_comment( $comment_id_to_approve );
		$this->assertEquals( '0', $comment_to_approve->comment_approved );

		\wp_set_comment_status( $comment_id_to_approve, 'approve' );
		$comment_to_approve = \get_comment( $comment_id_to_approve );
		$this->assertEquals( '1', $comment_to_approve->comment_approved );

		$comment_id_autoapproved = \wp_new_comment(
			array(
				'comment_type'         => 'comment',
				'comment_content'      => 'This is another comment to approve.',
				'comment_author'       => 'Approved',
				'comment_author_url'   => 'https://example.com/@approved',
				'comment_post_ID'      => $post_id,
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$comment_autoapproved = \get_comment( $comment_id_autoapproved );
		$this->assertEquals( '1', $comment_autoapproved->comment_approved );

		\remove_filter( 'pre_comment_approved', array( 'Activitypub\Comment', 'pre_comment_approved' ), 10 );

		$comment_id_unapproved = \wp_new_comment(
			array(
				'comment_type'         => 'comment',
				'comment_content'      => 'This is final comment.',
				'comment_author'       => 'Approved',
				'comment_author_url'   => 'https://example.com/@approved',
				'comment_post_ID'      => $post_id,
				'comment_author_email' => '',
				'comment_meta'         => array(
					'protocol' => 'activitypub',
				),
			)
		);

		$comment_unapproved = \get_comment( $comment_id_unapproved );
		$this->assertEquals( '0', $comment_unapproved->comment_approved );

		// Restore flood control.
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
	}

	/**
	 * Test pre_wp_update_comment_count_now.
	 *
	 * @covers ::pre_wp_update_comment_count_now
	 */
	public function test_pre_wp_update_comment_count_now() {
		$post_id = self::factory()->post->create();

		// Case 1: $new is null, no approved comments of non-ActivityPub types.
		$this->assertSame( 0, Comment::pre_wp_update_comment_count_now( null, 0, $post_id ) );

		// Case 2: $new is null, approved comments of non-ActivityPub types exist.
		self::factory()->comment->create_post_comments( $post_id, 2, array( 'comment_approved' => '1' ) );
		$this->assertSame( 2, Comment::pre_wp_update_comment_count_now( null, 0, $post_id ) );

		// Case 3: $new is null, mix of ActivityPub and non-ActivityPub approved comments.
		self::factory()->comment->create_post_comments(
			$post_id,
			3,
			array(
				'comment_approved' => '1',
				'comment_type'     => 'like',
			)
		);
		self::factory()->comment->create_post_comments( $post_id, 3, array( 'comment_approved' => '1' ) );
		$this->assertSame( 5, Comment::pre_wp_update_comment_count_now( null, 0, $post_id ) );

		// Case 4: $new is not null, should return $new unmodified.
		$this->assertSame( 10, Comment::pre_wp_update_comment_count_now( 10, 0, $post_id ) );
	}

	/**
	 * Data provider for test_check_ability_to_federate_comment.
	 */
	public function ability_to_federate_comment() {
		return array(
			array(
				'comment'  => array(
					'comment_type'         => 'comment',
					'comment_content'      => 'This is a received comment.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
					'comment_meta'         => array(
						'protocol' => 'activitypub',
					),
				),
				'expected' => array(
					'was_sent'            => false,
					'was_received'        => true,
					'should_be_federated' => false,
				),
			),
			array(
				'comment'  => array(
					'user_id'              => 1,
					'comment_type'         => 'comment',
					'comment_content'      => 'This is a sent comment.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected' => array(
					'was_sent'            => true,
					'was_received'        => false,
					'should_be_federated' => true,
				),
			),
			array(
				'comment'  => array(
					'comment_type'         => 'comment',
					'comment_content'      => 'This is a comment that is neither sent nor received.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected' => array(
					'was_sent'            => false,
					'was_received'        => false,
					'should_be_federated' => false,
				),
			),
		);
	}

	/**
	 * Data provider for test_check_ability_to_federate_threaded_comment.
	 */
	public function ability_to_federate_threaded_comment() {
		return array(
			array(
				'parent_comment' => array(
					'comment_type'         => 'comment',
					'comment_content'      => 'This is a parent comment.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
					'comment_meta'         => array(
						'protocol' => 'activitypub',
					),
				),
				'comment'        => array(
					'comment_type'         => 'comment',
					'comment_content'      => 'This is a regular comment.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
					'comment_meta'         => array(
						'protocol' => 'activitypub',
					),
				),
				'expected'       => array(
					'was_sent'            => false,
					'was_received'        => true,
					'should_be_federated' => false,
				),
			),
			array(
				'parent_comment' => array(
					'comment_type'         => 'comment',
					'comment_content'      => 'This is another parent comment.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
					'comment_meta'         => array(
						'protocol' => 'activitypub',
					),
				),
				'comment'        => array(
					'user_id'              => 1,
					'comment_type'         => 'comment',
					'comment_content'      => 'This is another comment.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected'       => array(
					'was_sent'            => false,
					'was_received'        => true,
					'should_be_federated' => true,
				),
			),
			array(
				'parent_comment' => array(
					'user_id'              => 1,
					'comment_type'         => 'comment',
					'comment_content'      => 'This is yet another parent comment.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
					'comment_meta'         => array(
						'activitypub_status' => 'federated',
					),
				),
				'comment'        => array(
					'user_id'              => 1,
					'comment_type'         => 'comment',
					'comment_content'      => 'This is yet another comment.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected'       => array(
					'was_sent'            => true,
					'was_received'        => false,
					'should_be_federated' => true,
				),
			),
			array(
				'parent_comment' => array(
					'comment_type'         => 'comment',
					'comment_content'      => 'This is a fourth parent comment.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
				),
				'comment'        => array(
					'comment_type'         => 'comment',
					'comment_content'      => 'This is a fourth comment.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected'       => array(
					'was_sent'            => false,
					'was_received'        => false,
					'should_be_federated' => false,
				),
			),
			array(
				'parent_comment' => array(
					'comment_type'         => 'comment',
					'comment_content'      => 'This is a fifth comment I think.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
				),
				'comment'        => array(
					'user_id'              => 1,
					'comment_type'         => 'comment',
					'comment_content'      => 'This is a comment that is not a duplicate.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected'       => array(
					'was_sent'            => false,
					'was_received'        => false,
					'should_be_federated' => false,
				),
			),
			// This should not be possible, but we test it anyway.
			array(
				'parent_comment' => array(
					'user_id'              => 1,
					'comment_type'         => 'comment',
					'comment_content'      => 'This is a parent comment that should not be possible.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
				),
				'comment'        => array(
					'comment_type'         => 'comment',
					'comment_content'      => 'This is a comment that should not be possible.',
					'comment_author_url'   => 'https://example.com',
					'comment_author_email' => '',
				),
				'expected'       => array(
					'was_sent'            => true,
					'was_received'        => false,
					'should_be_federated' => false,
				),
			),
		);
	}

	/**
	 * Test get_comment_type_by_activity_type method.
	 *
	 * @covers ::get_comment_type_by_activity_type
	 */
	public function test_get_comment_type_by_activity_type() {
		// Test Like activity type.
		$comment_type = Comment::get_comment_type_by_activity_type( 'Like' );
		$this->assertIsArray( $comment_type );
		$this->assertEquals( 'like', $comment_type['type'] );
		$this->assertEquals( 'Like', $comment_type['singular'] );
		$this->assertEquals( 'Likes', $comment_type['label'] );
		$this->assertContains( 'like', $comment_type['activity_types'] );

		// Test Announce activity type.
		$comment_type = Comment::get_comment_type_by_activity_type( 'Announce' );
		$this->assertIsArray( $comment_type );
		$this->assertEquals( 'repost', $comment_type['type'] );
		$this->assertEquals( 'Repost', $comment_type['singular'] );
		$this->assertEquals( 'Reposts', $comment_type['label'] );
		$this->assertContains( 'announce', $comment_type['activity_types'] );

		// Test case insensitivity.
		$comment_type = Comment::get_comment_type_by_activity_type( 'like' );
		$this->assertIsArray( $comment_type );
		$this->assertEquals( 'like', $comment_type['type'] );

		$comment_type = Comment::get_comment_type_by_activity_type( 'ANNOUNCE' );
		$this->assertIsArray( $comment_type );
		$this->assertEquals( 'repost', $comment_type['type'] );

		// Test invalid activity type.
		$comment_type = Comment::get_comment_type_by_activity_type( 'InvalidType' );
		$this->assertNull( $comment_type );

		// Test empty activity type.
		$comment_type = Comment::get_comment_type_by_activity_type( '' );
		$this->assertNull( $comment_type );
	}

	/**
	 * Test is_registered_comment_type.
	 *
	 * @covers ::is_registered_comment_type
	 */
	public function test_is_registered_comment_type() {
		// Test registered types (these are registered in Comment::register_comment_types()).
		$this->assertTrue( Comment::is_registered_comment_type( 'repost' ) );
		$this->assertTrue( Comment::is_registered_comment_type( 'like' ) );

		// Test case insensitivity.
		$this->assertTrue( Comment::is_registered_comment_type( 'REPOST' ) );
		$this->assertTrue( Comment::is_registered_comment_type( 'Like' ) );

		// Test with spaces and special characters (sanitize_key removes these).
		$this->assertTrue( Comment::is_registered_comment_type( ' repost ' ) );
		$this->assertTrue( Comment::is_registered_comment_type( 'like!' ) );

		// Test unregistered types.
		$this->assertFalse( Comment::is_registered_comment_type( 'nonexistent' ) );
		$this->assertFalse( Comment::is_registered_comment_type( '' ) );
		$this->assertFalse( Comment::is_registered_comment_type( 'comment' ) );
	}

	/**
	 * Test get_comment_type_slugs.
	 *
	 * @covers ::get_comment_type_slugs
	 */
	public function test_get_comment_type_slugs() {
		// Get the registered slugs.
		$slugs = Comment::get_comment_type_slugs();

		// Test that we get an array.
		$this->assertIsArray( $slugs );

		// Test that the array is not empty.
		$this->assertNotEmpty( $slugs );

		// Test that it contains the expected default types.
		$this->assertContains( 'repost', $slugs );
		$this->assertContains( 'like', $slugs );

		// Test that the array only contains strings.
		foreach ( $slugs as $slug ) {
			$this->assertIsString( $slug );
		}

		// Test that there are no duplicate slugs.
		$this->assertEquals( count( $slugs ), count( array_unique( $slugs ) ) );
	}

	/**
	 * Test get_comment_type_names to maintain backwards compatibility.
	 *
	 * @covers ::get_comment_type_names
	 */
	public function test_get_comment_type_names() {
		$this->setExpectedDeprecated( 'Activitypub\Comment::get_comment_type_names' );

		// Get both types of results.
		$names = Comment::get_comment_type_names();
		$slugs = Comment::get_comment_type_slugs();

		// Test that we get an array.
		$this->assertIsArray( $names );

		// Test that the array is not empty.
		$this->assertNotEmpty( $names );

		// Test that it returns exactly the same as get_comment_type_slugs().
		$this->assertEquals( $slugs, $names );

		// Verify it returns slugs and not singular names.
		$this->assertContains( 'repost', $names );
		$this->assertContains( 'like', $names );
		$this->assertNotContains( 'Repost', $names );
		$this->assertNotContains( 'Like', $names );
	}
}
