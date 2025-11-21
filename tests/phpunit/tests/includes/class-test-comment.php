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
	 * @covers ::get_source_id
	 * @covers ::get_source_url
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
				'post_author'  => 1,
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
		$post_id = self::factory()->post->create(
			array(
				'post_author' => 1,
			)
		);

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
					'comment_meta'         => array(
						'activitypub_status' => 'pending',
					),
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
					'comment_meta'         => array(
						'activitypub_status' => 'pending',
					),
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
					'comment_meta'         => array(
						'activitypub_status' => 'pending',
					),
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
					'comment_meta'         => array(
						'activitypub_status' => 'federated',
					),
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
	 * Test object_id_to_comment method.
	 *
	 * @covers ::object_id_to_comment
	 */
	public function test_object_id_to_comment() {
		$source_id = 'https://example.com/1';

		// No comment with the same source_id.
		$comment_0 = Comment::object_id_to_comment( $source_id );
		$this->assertFalse( $comment_0 );

		// Create a comment with the same source_id.
		$id_1 = self::factory()->comment->create();
		add_comment_meta( $id_1, 'source_id', $source_id, true );

		// Get the comment with the same source_id.
		$comment_1 = Comment::object_id_to_comment( $source_id );
		$this->assertEquals( $id_1, $comment_1->comment_ID );

		// Create another comment with the same source_id.
		$id_2 = self::factory()->comment->create(
			array(
				'comment_date' => '2024-01-01 00:00:00',
			)
		);
		add_comment_meta( $id_2, 'source_id', $source_id, true );

		// Get the comment with the same source_id.
		$comment_2 = Comment::object_id_to_comment( $source_id );
		$this->assertEquals( $id_1, $comment_2->comment_ID );

		// Create another comment with the same source_id.
		$id_3 = self::factory()->comment->create(
			array(
				'comment_date' => '2024-01-01 00:00:00',
			)
		);
		add_comment_meta( $id_3, 'source_id', $source_id, true );

		// Get the comment with the same source_id.
		$comment_3 = Comment::object_id_to_comment( $source_id );
		$this->assertEquals( $id_1, $comment_3->comment_ID );

		// Delete the comments.
		wp_delete_comment( $id_1, true );
		wp_delete_comment( $id_2, true );
		wp_delete_comment( $id_3, true );
	}

	/**
	 * Test pre_comment_approved filter.
	 *
	 * @covers ::pre_comment_approved
	 */
	public function test_pre_comment_approved_filter() {
		\add_option( 'activitypub_auto_approve_reactions', '1' );

		$post_id = self::factory()->post->create();

		$comment_data = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => 'John Doe',
			'comment_author_email' => 'john@example.com',
			'comment_author_url'   => 'https://example.com',
			'comment_type'         => 'like',
			'comment_content'      => 'This is a like.',
			'comment_approved'     => 0,
		);
		$comment_id   = \wp_new_comment( $comment_data );
		\clean_comment_cache( $comment_id );
		$this->assertEquals( 1, \get_comment( $comment_id, 'ARRAY_A' )['comment_approved'] );

		\delete_option( 'activitypub_auto_approve_reactions' );
		\wp_delete_comment( $comment_id, true );

		$comment_id = \wp_new_comment( $comment_data );
		$this->assertEquals( 0, \get_comment( $comment_id, 'ARRAY_A' )['comment_approved'] );

		\wp_delete_comment( $comment_id, true );
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test comment_feed_where.
	 *
	 * @covers ::comment_feed_where
	 */
	public function test_comment_feed_where() {
		$post_id = self::factory()->post->create();

		$core_comment_types = array(
			'comment',
			'pingback',
			'trackback',
		);

		$activitypub_comment_types = Comment::get_comment_type_slugs();

		$comment_types = \array_merge( $activitypub_comment_types, $core_comment_types );

		foreach ( $comment_types as $comment_type ) {
			self::factory()->comment->create(
				array(
					'comment_approved' => '1',
					'comment_content'  => 'This is a comment.',
					'comment_post_ID'  => $post_id,
					'comment_type'     => $comment_type,
				)
			);
		}

		$query = new \WP_Query(
			array(
				'feed'         => 'comments-rss2',
				'withcomments' => true,
			)
		);

		$this->assertSame( count( $core_comment_types ), $query->comment_count );
		$this->assertEqualSets( $core_comment_types, \wp_list_pluck( $query->comments, 'comment_type' ) );

		// Test what would happen if we don't filter comment_feed_where.
		\remove_filter( 'comment_feed_where', array( Comment::class, 'comment_feed_where' ) );
		$query->get_posts();

		$this->assertSame( count( $comment_types ), $query->comment_count ); // All comments are included.
		$this->assertEqualSets( $comment_types, \wp_list_pluck( $query->comments, 'comment_type' ) );

		// Restore the filter.
		\add_filter( 'comment_feed_where', array( Comment::class, 'comment_feed_where' ) );

		// Test filtering by comment type.
		foreach ( $activitypub_comment_types as $comment_type ) {
			\set_query_var( 'type', $comment_type );
			$query->get_posts();

			$this->assertSame( 1, $query->comment_count );
			$this->assertSame( $comment_type, $query->comments[0]->comment_type );
		}

		// Test filtering by comment type that doesn't exist.
		\set_query_var( 'type', 'foo_bar_baz_not_a_real_type' );
		$query->get_posts();

		$this->assertSame( count( $core_comment_types ), $query->comment_count );
		$this->assertEqualSets( $core_comment_types, \wp_list_pluck( $query->comments, 'comment_type' ) );

		// Clean up.
		\wp_delete_post( $post_id, true );
		\set_query_var( 'type', null );
	}

	/**
	 * Test that comments on ap_post are excluded from admin comment queries.
	 *
	 * @covers ::comment_query
	 */
	public function test_exclude_ap_post_comments_in_admin() {
		// Enable the option that activates ap_post comment filtering.
		\update_option( 'activitypub_create_posts', true );

		// Create a regular post.
		$regular_post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Regular Post',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		// Create an ap_post.
		$ap_post_id = wp_insert_post(
			array(
				'post_type'    => 'ap_post',
				'post_title'   => 'AP Post',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		// Create comments on both posts.
		$regular_comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => $regular_post_id,
				'comment_content' => 'Comment on regular post',
				'comment_author'  => 'Test User',
			)
		);

		$ap_comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => $ap_post_id,
				'comment_content' => 'Comment on ap_post',
				'comment_author'  => 'Test User',
				'comment_meta'    => array(
					'protocol' => 'activitypub',
				),
			)
		);

		// Simulate admin context.
		\set_current_screen( 'edit-comments' );

		// Query comments in admin context.
		$query    = new \WP_Comment_Query();
		$comments = $query->query( array() );

		// Check that ap_post comment is excluded.
		$comment_ids = wp_list_pluck( $comments, 'comment_ID' );
		$this->assertContains( (string) $regular_comment_id, $comment_ids, 'Regular post comment should be included' );
		$this->assertNotContains( (string) $ap_comment_id, $comment_ids, 'AP post comment should be excluded from admin' );

		// Clean up.
		\set_current_screen( 'front' );
		wp_delete_comment( $regular_comment_id, true );
		wp_delete_comment( $ap_comment_id, true );
		wp_delete_post( $regular_post_id, true );
		wp_delete_post( $ap_post_id, true );
		\delete_option( 'activitypub_create_posts' );
	}

	/**
	 * Test that ap_post comments are NOT excluded from frontend queries.
	 *
	 * @covers ::comment_query
	 */
	public function test_ap_post_comments_shown_on_frontend() {
		// Create an ap_post.
		$ap_post_id = wp_insert_post(
			array(
				'post_type'    => 'ap_post',
				'post_title'   => 'AP Post',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		// Create comment on ap_post.
		$ap_comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => $ap_post_id,
				'comment_content' => 'Comment on ap_post',
				'comment_author'  => 'Test User',
			)
		);

		// Ensure we're in frontend context (not admin).
		\set_current_screen( 'front' );

		// Query comments - should include ap_post comments on frontend.
		$query    = new \WP_Comment_Query();
		$comments = $query->query( array() );

		$comment_ids = wp_list_pluck( $comments, 'comment_ID' );
		$this->assertContains( (string) $ap_comment_id, $comment_ids, 'AP post comment should be shown on frontend' );

		// Clean up.
		wp_delete_comment( $ap_comment_id, true );
		wp_delete_post( $ap_post_id, true );
	}

	/**
	 * Test that ap_post comments are shown when querying for specific post.
	 *
	 * @covers ::comment_query
	 */
	public function test_ap_post_comments_shown_when_querying_specific_post() {
		// Create an ap_post.
		$ap_post_id = wp_insert_post(
			array(
				'post_type'    => 'ap_post',
				'post_title'   => 'AP Post',
				'post_content' => 'Content',
				'post_status'  => 'publish',
			)
		);

		// Create comment on ap_post.
		$ap_comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => $ap_post_id,
				'comment_content' => 'Comment on ap_post',
				'comment_author'  => 'Test User',
			)
		);

		// Simulate admin context.
		\set_current_screen( 'edit-comments' );

		// Query comments for specific post - should include ap_post comments.
		$query    = new \WP_Comment_Query();
		$comments = $query->query(
			array(
				'post_id' => $ap_post_id,
			)
		);

		$comment_ids = wp_list_pluck( $comments, 'comment_ID' );
		$this->assertContains( (string) $ap_comment_id, $comment_ids, 'AP post comment should be shown when querying specific post' );

		// Clean up.
		\set_current_screen( 'front' );
		wp_delete_comment( $ap_comment_id, true );
		wp_delete_post( $ap_post_id, true );
	}

	/**
	 * Test that multiple ap_post comments are excluded while regular comments remain.
	 *
	 * @covers ::comment_query
	 */
	public function test_multiple_ap_post_comments_excluded() {
		// Enable the option that activates ap_post comment filtering.
		\update_option( 'activitypub_create_posts', true );

		// Create regular posts.
		$regular_post_1 = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Regular Post 1',
				'post_status' => 'publish',
			)
		);

		$regular_post_2 = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Regular Post 2',
				'post_status' => 'publish',
			)
		);

		// Create ap_posts.
		$ap_post_1 = wp_insert_post(
			array(
				'post_type'   => 'ap_post',
				'post_title'  => 'AP Post 1',
				'post_status' => 'publish',
			)
		);

		$ap_post_2 = wp_insert_post(
			array(
				'post_type'   => 'ap_post',
				'post_title'  => 'AP Post 2',
				'post_status' => 'publish',
			)
		);

		// Create comments on regular posts.
		$regular_comment_ids   = array();
		$regular_comment_ids[] = wp_insert_comment(
			array(
				'comment_post_ID' => $regular_post_1,
				'comment_content' => 'Comment 1',
				'comment_author'  => 'User 1',
			)
		);
		$regular_comment_ids[] = wp_insert_comment(
			array(
				'comment_post_ID' => $regular_post_2,
				'comment_content' => 'Comment 2',
				'comment_author'  => 'User 2',
			)
		);

		// Create comments on ap_posts.
		$ap_comment_ids   = array();
		$ap_comment_ids[] = wp_insert_comment(
			array(
				'comment_post_ID' => $ap_post_1,
				'comment_content' => 'AP Comment 1',
				'comment_author'  => 'AP User 1',
			)
		);
		$ap_comment_ids[] = wp_insert_comment(
			array(
				'comment_post_ID' => $ap_post_2,
				'comment_content' => 'AP Comment 2',
				'comment_author'  => 'AP User 2',
			)
		);

		// Simulate admin context.
		\set_current_screen( 'edit-comments' );

		// Query all comments.
		$query    = new \WP_Comment_Query();
		$comments = $query->query( array() );

		$found_comment_ids = wp_list_pluck( $comments, 'comment_ID' );

		// Assert all regular comments are found.
		foreach ( $regular_comment_ids as $comment_id ) {
			$this->assertContains( (string) $comment_id, $found_comment_ids, 'Regular comment should be included' );
		}

		// Assert all ap_post comments are NOT found.
		foreach ( $ap_comment_ids as $comment_id ) {
			$this->assertNotContains( (string) $comment_id, $found_comment_ids, 'AP post comment should be excluded' );
		}

		// Clean up.
		\set_current_screen( 'front' );
		foreach ( array_merge( $regular_comment_ids, $ap_comment_ids ) as $comment_id ) {
			wp_delete_comment( $comment_id, true );
		}
		foreach ( array( $regular_post_1, $regular_post_2, $ap_post_1, $ap_post_2 ) as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		\delete_option( 'activitypub_create_posts' );
	}
}
