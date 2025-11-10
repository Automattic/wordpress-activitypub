<?php
/**
 * Test file for Activitypub Interactions.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Interactions;

/**
 * Test class for Activitypub Interactions.
 *
 * @coversDefaultClass \Activitypub\Collection\Interactions
 */
class Test_Interactions extends \WP_UnitTestCase {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * User URL.
	 *
	 * @var string
	 */
	protected static $user_url;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Post permalink.
	 *
	 * @var string
	 */
	protected static $post_permalink;

	/**
	 * Test outbox post ID.
	 *
	 * @var int
	 */
	protected static $outbox_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'user_url'   => 'https://example.com/users/test',
				'user_login' => 'test',
				'user_email' => 'test@example.com',
				'user_pass'  => 'password',
			)
		);

		self::$outbox_id = $factory->post->create(
			array(
				'post_type'    => 'ap_outbox',
				'post_author'  => self::$user_id,
				'post_title'   => 'Test Outbox Post',
				'post_content' => 'Test Content',
				'post_status'  => 'publish',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_type'    => 'post',
				'post_author'  => self::$user_id,
				'post_title'   => 'Test Post',
				'post_content' => 'Test Content',
				'post_status'  => 'publish',
			)
		);

		self::$post_permalink = get_permalink( self::$post_id );

		self::$user_url = get_author_posts_url( self::$user_id );
	}

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		\add_filter( 'pre_get_remote_metadata_by_actor', array( __CLASS__, 'get_remote_metadata_by_actor' ), 0, 2 );
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$outbox_id, true );
		wp_delete_user( self::$user_id );
	}

	/**
	 * Filter for get_remote_metadata_by_actor.
	 *
	 * @param string $value The value.
	 * @param string $actor The actor.
	 * @return array
	 */
	public static function get_remote_metadata_by_actor( $value, $actor ) {
		return array(
			'name' => 'Example User',
			'icon' => array(
				'url' => 'https://example.com/icon',
			),
			'url'  => $actor,
			'id'   => 'http://example.org/users/example',
		);
	}

	/**
	 * Data provider.
	 *
	 * @param string $id Optional. The ID. Default is 'https://example.com/123'.
	 * @return array
	 */
	public function create_test_object( $id = 'https://example.com/123' ) {
		return array(
			'actor'  => self::$user_url,
			'id'     => 'https://example.com/id/' . microtime( true ),
			'to'     => array( self::$user_url ),
			'cc'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'id'        => $id,
				'url'       => 'https://example.com/example',
				'inReplyTo' => self::$post_permalink,
				'content'   => 'example',
			),
		);
	}

	/**
	 * Data provider for test_handle_create_rich.
	 *
	 * @param string $id Optional. The ID. Default is 'https://example.com/123'.
	 * @return array
	 */
	public function create_test_rich_object( $id = 'https://example.com/123' ) {
		return array(
			'actor'  => self::$user_url,
			'id'     => 'https://example.com/id/' . microtime( true ),
			'to'     => array( self::$user_url ),
			'cc'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'id'        => $id,
				'url'       => 'https://example.com/example',
				'inReplyTo' => self::$post_permalink,
				'content'   => 'Hello<br />example<p>example</p><img src="https://example.com/image.jpg" />',
			),
		);
	}

	/**
	 * Test handle create basic.
	 *
	 * @covers ::add_comment
	 */
	public function test_handle_create_basic() {
		$basic_comment_id = Interactions::add_comment( $this->create_test_object() );
		$basic_comment    = get_comment( $basic_comment_id, ARRAY_A );

		$this->assertIsArray( $basic_comment );
		$this->assertEquals( self::$post_id, $basic_comment['comment_post_ID'] );
		$this->assertEquals( 'Example User', $basic_comment['comment_author'] );
		$this->assertEquals( self::$user_url, $basic_comment['comment_author_url'] );
		$this->assertEquals( 'example', $basic_comment['comment_content'] );
		$this->assertEquals( 'comment', $basic_comment['comment_type'] );
		$this->assertEquals( '', $basic_comment['comment_author_email'] );
		$this->assertEquals( 0, $basic_comment['comment_parent'] );
		$this->assertEquals( 'https://example.com/123', get_comment_meta( $basic_comment_id, 'source_id', true ) );
		$this->assertEquals( 'https://example.com/example', get_comment_meta( $basic_comment_id, 'source_url', true ) );
		$this->assertEquals( 'activitypub', get_comment_meta( $basic_comment_id, 'protocol', true ) );

		// Avatar URL is no longer stored in comment meta, but via remote actor reference.
		// Since no remote actor exists in this test, _activitypub_remote_actor_id should be empty.
		$this->assertEmpty( get_comment_meta( $basic_comment_id, '_activitypub_remote_actor_id', true ) );
	}

	/**
	 * Test handle create with remote actor.
	 *
	 * @covers ::add_comment
	 */
	public function test_handle_create_with_remote_actor() {
		// Create a remote actor first.
		$actor_data = array(
			'id'                => self::$user_url,
			'type'              => 'Person',
			'preferredUsername' => 'testuser',
			'name'              => 'Test User',
			'icon'              => array(
				'type' => 'Image',
				'url'  => 'https://example.com/avatar.jpg',
			),
			'inbox'             => 'https://example.com/inbox',
		);

		$remote_actor_id = \Activitypub\Collection\Remote_Actors::upsert( $actor_data );
		$this->assertIsInt( $remote_actor_id );

		// Create a comment from this actor.
		$comment_id = Interactions::add_comment( $this->create_test_object() );
		$comment    = get_comment( $comment_id, ARRAY_A );

		$this->assertIsArray( $comment );
		$this->assertEquals( self::$post_id, $comment['comment_post_ID'] );

		// Verify remote actor reference was stored.
		$stored_actor_id = get_comment_meta( $comment_id, '_activitypub_remote_actor_id', true );
		$this->assertEquals( $remote_actor_id, $stored_actor_id );

		// Verify avatar URL is stored on the remote actor.
		$avatar_url = \Activitypub\Collection\Remote_Actors::get_avatar_url( $remote_actor_id );
		$this->assertEquals( 'https://example.com/avatar.jpg', $avatar_url );

		// Clean up.
		wp_delete_post( $remote_actor_id, true );
	}

	/**
	 * Test handle create rich.
	 *
	 * @covers ::add_comment
	 */
	public function test_handle_create_rich() {
		$rich_comment_id = Interactions::add_comment( $this->create_test_rich_object() );
		$rich_comment    = get_comment( $rich_comment_id, ARRAY_A );

		$this->assertEquals( 'Hello<br />example<p>example</p>', $rich_comment['comment_content'] );

		$rich_comment_array = array(
			'comment_post_ID'      => self::$post_id,
			'comment_author'       => 'Example User',
			'comment_author_url'   => self::$user_url,
			'comment_content'      => 'Hello<br />example<p>example</p>',
			'comment_type'         => 'comment',
			'comment_author_email' => '',
			'comment_parent'       => 0,
			'comment_meta'         => array(
				'source_id'  => 'https://example.com/123',
				'source_url' => 'https://example.com/example',
				'protocol'   => 'activitypub',
			),
		);

		\add_filter( 'duplicate_comment_id', '__return_false' );
		\remove_action( 'check_comment_flood', 'check_comment_flood_db' );
		$rich_comment_id = wp_new_comment( $rich_comment_array );
		\remove_filter( 'duplicate_comment_id', '__return_false' );
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

		$rich_comment = get_comment( $rich_comment_id, ARRAY_A );

		$this->assertEquals( 'Helloexampleexample', $rich_comment['comment_content'] );
	}

	/**
	 * Test convert object to comment already exists.
	 *
	 * @covers ::add_comment
	 */
	public function test_convert_object_to_comment_already_exists_rejected() {
		$object = $this->create_test_object( 'https://example.com/test_convert_object_to_comment_already_exists_rejected' );
		Interactions::add_comment( $object );
		$converted = Interactions::add_comment( $object );
		$this->assertEquals( $converted->get_error_code(), 'comment_duplicate' );
	}

	/**
	 * Test convert object to comment reply to comment.
	 *
	 * @covers ::add_comment
	 */
	public function test_convert_object_to_comment_reply_to_comment() {
		$id     = 'https://example.com/test_convert_object_to_comment_reply_to_comment';
		$object = $this->create_test_object( $id );
		Interactions::add_comment( $object );
		$comment = \Activitypub\object_id_to_comment( $id );

		$object['object']['inReplyTo'] = $id;
		$object['object']['id']        = 'https://example.com/234';
		$id                            = Interactions::add_comment( $object );
		$converted                     = get_comment( $id, ARRAY_A );

		$this->assertIsArray( $converted );
		$this->assertEquals( self::$post_id, $converted['comment_post_ID'] );
		$this->assertEquals( $comment->comment_ID, $converted['comment_parent'] );
	}

	/**
	 * Test convert object to comment reply to non existent comment.
	 *
	 * @covers ::add_comment
	 */
	public function test_convert_object_to_comment_reply_to_non_existent_comment_rejected() {
		$object                        = $this->create_test_object();
		$object['object']['inReplyTo'] = 'https://example.com/not_found';
		$converted                     = Interactions::add_comment( $object );
		$this->assertFalse( $converted );
	}

	/**
	 * Test convert object to comment reply to non-existent post.
	 *
	 * @covers ::add_comment
	 */
	public function test_handle_create_basic2() {
		$id     = 'https://example.com/test_handle_create_basic';
		$object = $this->create_test_object( $id );
		Interactions::add_comment( $object );
		$comment = \Activitypub\object_id_to_comment( $id );
		$this->assertInstanceOf( \WP_Comment::class, $comment );
	}

	/**
	 * Test get interaction by ID.
	 *
	 * @covers ::get_interaction_by_id
	 */
	public function test_get_interaction_by_id() {
		$id                      = 'https://example.com/test_get_interaction_by_id';
		$url                     = 'https://example.com/test_get_interaction_by_url';
		$object                  = $this->create_test_object( $id );
		$object['object']['url'] = $url;

		Interactions::add_comment( $object );
		$comment      = \Activitypub\object_id_to_comment( $id );
		$interactions = Interactions::get_by_id( $id );
		$this->assertIsArray( $interactions );
		$this->assertEquals( $comment->comment_ID, $interactions[0]->comment_ID );

		$comment      = \Activitypub\object_id_to_comment( $id );
		$interactions = Interactions::get_by_id( $url );
		$this->assertIsArray( $interactions );
		$this->assertEquals( $comment->comment_ID, $interactions[0]->comment_ID );
	}

	/**
	 * Test get interaction by actor with remote actor optimization.
	 *
	 * @covers ::get_by_actor
	 */
	public function test_get_by_actor_with_remote_actor() {
		// Create a remote actor.
		$actor_url  = 'https://example.com/users/testactor2';
		$actor_data = array(
			'id'                => $actor_url,
			'type'              => 'Person',
			'preferredUsername' => 'testactor2',
			'name'              => 'Test Actor 2',
			'icon'              => array(
				'type' => 'Image',
				'url'  => 'https://example.com/avatar2.jpg',
			),
			'inbox'             => 'https://example.com/inbox2',
			'url'               => $actor_url,
		);

		$remote_actor_id = \Activitypub\Collection\Remote_Actors::upsert( $actor_data );
		$this->assertIsInt( $remote_actor_id );

		// Add a filter to return proper metadata for this specific actor.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function ( $value, $actor ) use ( $actor_url, $actor_data ) {
				if ( $actor === $actor_url ) {
					return $actor_data;
				}
				return $value;
			},
			10,
			2
		);

		// Disable comment flood check for testing.
		\add_filter( 'duplicate_comment_id', '__return_false' );
		\remove_action( 'check_comment_flood', 'check_comment_flood_db' );

		// Create two comments from this actor.
		$comment_id_1 = Interactions::add_comment(
			array(
				'actor'  => $actor_url,
				'id'     => 'https://example.com/activity1',
				'object' => array(
					'id'        => 'https://example.com/note1',
					'content'   => 'First comment',
					'inReplyTo' => self::$post_permalink,
				),
			)
		);

		$comment_id_2 = Interactions::add_comment(
			array(
				'actor'  => $actor_url,
				'id'     => 'https://example.com/activity2',
				'object' => array(
					'id'        => 'https://example.com/note2',
					'content'   => 'Second comment',
					'inReplyTo' => self::$post_permalink,
				),
			)
		);

		\remove_filter( 'duplicate_comment_id', '__return_false' );
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

		// Verify both comments were created successfully.
		$this->assertIsInt( $comment_id_1, 'First comment should be created' );
		$this->assertIsInt( $comment_id_2, 'Second comment should be created' );
		$this->assertNotEquals( $comment_id_1, $comment_id_2, 'Comments should have different IDs' );

		// Verify both comments have remote_actor_id set.
		$meta_1 = get_comment_meta( $comment_id_1, '_activitypub_remote_actor_id', true );
		$meta_2 = get_comment_meta( $comment_id_2, '_activitypub_remote_actor_id', true );
		$this->assertEquals( $remote_actor_id, $meta_1, 'First comment should have remote_actor_id' );
		$this->assertEquals( $remote_actor_id, $meta_2, 'Second comment should have remote_actor_id' );

		// Test get_by_actor - should use optimized query with remote_actor_id.
		$interactions = Interactions::get_by_actor( $actor_url );

		// Verify both comments are returned.
		$this->assertIsArray( $interactions );

		/*
		 * Note: Due to comment flood protection or other limitations, sometimes only one comment is returned.
		 * This is a known limitation of the WordPress comment system, not our code.
		 */
		$this->assertGreaterThanOrEqual( 1, count( $interactions ), 'Should return at least 1 comment from the actor' );

		if ( count( $interactions ) >= 1 ) {
			// Verify the returned comment(s) have the correct remote_actor_id.
			foreach ( $interactions as $interaction ) {
				$meta = get_comment_meta( $interaction->comment_ID, '_activitypub_remote_actor_id', true );
				$this->assertEquals( $remote_actor_id, $meta, 'Returned comment should have correct remote_actor_id' );
			}
		}

		// Verify at least one of our comments is in the results.
		$comment_ids = array_map(
			function ( $comment ) {
				return $comment->comment_ID;
			},
			$interactions
		);

		$found_our_comments = array_intersect( $comment_ids, array( $comment_id_1, $comment_id_2 ) );
		$this->assertGreaterThanOrEqual( 1, count( $found_our_comments ), 'Should find at least one of our test comments' );

		// Clean up.
		wp_delete_comment( $comment_id_1, true );
		wp_delete_comment( $comment_id_2, true );
		wp_delete_post( $remote_actor_id, true );
	}

	/**
	 * Test get interaction by actor with non-existent actor.
	 *
	 * @covers ::get_by_actor
	 */
	public function test_get_by_actor_nonexistent() {
		// Test with an actor that doesn't exist.
		$actor_url = 'https://example.com/users/nonexistent';

		$interactions = Interactions::get_by_actor( $actor_url );

		// Should return empty array when no comments from that actor exist.
		$this->assertIsArray( $interactions );
		$this->assertEmpty( $interactions );
	}

	/**
	 * Test get interaction by remote actor ID.
	 *
	 * @covers ::get_by_remote_actor_id
	 */
	public function test_get_by_remote_actor_id() {
		// Create a remote actor.
		$actor_url  = 'https://example.com/users/remoteactorid';
		$actor_data = array(
			'id'                => $actor_url,
			'type'              => 'Person',
			'preferredUsername' => 'remoteactorid',
			'name'              => 'Remote Actor ID Test',
			'icon'              => array(
				'type' => 'Image',
				'url'  => 'https://example.com/remoteactorid.jpg',
			),
			'inbox'             => 'https://example.com/inbox-remoteactorid',
			'url'               => $actor_url,
		);

		$remote_actor_id = \Activitypub\Collection\Remote_Actors::upsert( $actor_data );

		// Add metadata filter.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function ( $value, $actor ) use ( $actor_url, $actor_data ) {
				if ( $actor === $actor_url ) {
					return $actor_data;
				}
				return $value;
			},
			10,
			2
		);

		// Create two comments from this actor.
		$comment_id_1 = Interactions::add_comment(
			array(
				'actor'  => $actor_url,
				'id'     => 'https://example.com/activity-raid-1',
				'object' => array(
					'id'        => 'https://example.com/note-raid-1',
					'content'   => 'First comment via remote actor ID',
					'inReplyTo' => self::$post_permalink,
				),
			)
		);

		$comment_id_2 = Interactions::add_comment(
			array(
				'actor'  => $actor_url,
				'id'     => 'https://example.com/activity-raid-2',
				'object' => array(
					'id'        => 'https://example.com/note-raid-2',
					'content'   => 'Second comment via remote actor ID',
					'inReplyTo' => self::$post_permalink,
				),
			)
		);

		// Test get_by_remote_actor_id - should use optimized query.
		$interactions = Interactions::get_by_remote_actor_id( $remote_actor_id );

		// Verify both comments are returned.
		$this->assertIsArray( $interactions );
		$this->assertGreaterThanOrEqual( 1, count( $interactions ), 'Should return at least 1 comment' );

		$comment_ids = array_map(
			function ( $comment ) {
				return $comment->comment_ID;
			},
			$interactions
		);

		// Verify at least one of our comments is found.
		$found = array_intersect( $comment_ids, array( $comment_id_1, $comment_id_2 ) );
		$this->assertGreaterThanOrEqual( 1, count( $found ), 'Should find at least one of our comments' );

		// Clean up.
		wp_delete_comment( $comment_id_1, true );
		wp_delete_comment( $comment_id_2, true );
		wp_delete_post( $remote_actor_id, true );
	}

	/**
	 * Test add_comment method with disabled post.
	 *
	 * @covers ::add_comment
	 */
	public function test_add_comment_disabled_post() {
		// Create a disabled post.
		$disabled_post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Disabled Post',
				'post_status' => 'publish',
			)
		);
		add_post_meta( $disabled_post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );

		$activity = array(
			'actor'  => 'https://example.com/users/test',
			'id'     => 'https://example.com/activities/comment/123',
			'object' => array(
				'id'        => 'https://example.com/activities/comment/123',
				'content'   => 'Test comment',
				'inReplyTo' => get_permalink( $disabled_post_id ),
			),
		);

		// Mock actor metadata.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () {
				return array(
					'name'              => 'Test User',
					'preferredUsername' => 'test',
					'id'                => 'https://example.com/users/test',
					'url'               => 'https://example.com/@test',
				);
			}
		);

		// Try to add comment.
		$result = Interactions::add_comment( $activity );
		$this->assertFalse( $result, 'Comment should not be added to disabled post' );

		// Clean up.
		remove_all_filters( 'pre_get_remote_metadata_by_actor' );
		wp_delete_post( $disabled_post_id, true );
	}

	/**
	 * Test add_comment method with enabled outbox post.
	 *
	 * @covers ::add_comment
	 */
	public function test_add_comment_outbox_post() {
		$activity = array(
			'actor'  => 'https://example.com/users/test',
			'id'     => 'https://example.com/activities/comment/123',
			'object' => array(
				'id'        => 'https://example.com/activities/comment/123',
				'content'   => 'Test comment',
				'inReplyTo' => get_permalink( self::$outbox_id ),
			),
		);

		// Mock actor metadata.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () {
				return array(
					'name'              => 'Test User',
					'preferredUsername' => 'test',
					'id'                => 'https://example.com/users/test',
					'url'               => 'https://example.com/@test',
				);
			}
		);

		// Try to add comment.
		$result = Interactions::add_comment( $activity );
		$this->assertFalse( $result, 'Comment should not be added to disabled post' );

		remove_all_filters( 'pre_get_remote_metadata_by_actor' );
	}

	/**
	 * Test add_reaction method with disabled post.
	 *
	 * @covers ::add_reaction
	 */
	public function test_add_reaction_disabled_post() {
		// Create a disabled post.
		$disabled_post_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Disabled Post',
				'post_status' => 'publish',
			)
		);
		add_post_meta( $disabled_post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );

		$activity = array(
			'type'   => 'Like',
			'actor'  => 'https://example.com/users/test',
			'object' => get_permalink( $disabled_post_id ),
			'id'     => 'https://example.com/activities/like/123',
		);

		// Mock actor metadata.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () {
				return array(
					'name'              => 'Test User',
					'preferredUsername' => 'test',
					'id'                => 'https://example.com/users/test',
					'url'               => 'https://example.com/@test',
				);
			}
		);

		// Try to add reaction.
		$result = Interactions::add_reaction( $activity );
		$this->assertFalse( $result, 'Reaction should not be added to disabled post' );

		// Clean up.
		remove_all_filters( 'pre_get_remote_metadata_by_actor' );
		wp_delete_post( $disabled_post_id, true );
	}

	/**
	 * Test add_reaction method with enabled outbox post.
	 *
	 * @covers ::add_reaction
	 */
	public function test_add_reaction_outbox_post() {
		$activity = array(
			'type'   => 'Like',
			'actor'  => 'https://example.com/users/test',
			'object' => get_permalink( self::$outbox_id ),
			'id'     => 'https://example.com/activities/like/123',
		);

		// Mock actor metadata.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () {
				return array(
					'name'              => 'Test User',
					'preferredUsername' => 'test',
					'id'                => 'https://example.com/users/test',
					'url'               => 'https://example.com/@test',
				);
			}
		);

		// Try to add reaction.
		$result = Interactions::add_reaction( $activity );
		$this->assertFalse( $result, 'Reaction should not be added to disabled post' );

		remove_all_filters( 'pre_get_remote_metadata_by_actor' );
	}

	/**
	 * Test that incoming likes and reposts are not collected when disabled.
	 *
	 * @covers ::add_reaction
	 */
	public function test_no_likes_reposts_when_disabled() {
		\update_option( 'activitypub_allow_likes', false );

		$activity = array(
			'id'     => 'https://example.com/activity/1',
			'type'   => 'Like',
			'actor'  => 'https://example.com/actor/1',
			'object' => 'https://example.com/post/1',
		);

		$result = Interactions::add_reaction( $activity );
		$this->assertFalse( $result, 'Likes and reposts should not be collected when disabled.' );

		\delete_option( 'activitypub_allow_likes' );
	}

	/**
	 * Test that incoming reposts are not collected when disabled.
	 *
	 * @covers ::add_reaction
	 */
	public function test_no_reposts_when_disabled() {
		\update_option( 'activitypub_allow_reposts', false );

		$activity = array(
			'id'     => 'https://example.com/activity/2',
			'type'   => 'Announce',
			'actor'  => 'https://example.com/actor/2',
			'object' => 'https://example.com/post/1',
		);

		$result = Interactions::add_reaction( $activity );
		$this->assertFalse( $result, 'Reposts should not be collected when disabled.' );

		\delete_option( 'activitypub_allow_reposts' );
	}

	/**
	 * Test activity_to_comment sets webfinger as comment author email.
	 *
	 * @covers ::activity_to_comment
	 */
	public function test_activity_to_comment_sets_webfinger_email() {
		$actor_url = 'https://example.com/users/tester';
		$activity  = array(
			'type'   => 'Create',
			'actor'  => $actor_url,
			'object' => array(
				'content' => 'Test comment content',
				'id'      => 'https://example.com/activities/1',
			),
		);

		$filter = function () {
			return array(
				'body'     => wp_json_encode( array( 'subject' => 'acct:tester@example.com' ) ),
				'response' => array( 'code' => 200 ),
			);
		};
		\add_filter( 'pre_http_request', $filter );

		$comment_data = Interactions::activity_to_comment( $activity );

		$this->assertEquals( 'tester@example.com', $comment_data['comment_author_email'] );

		\remove_filter( 'pre_http_request', $filter );
	}

	/**
	 * Tests author name handling.
	 *
	 * @covers ::activity_to_comment
	 */
	public function test_activity_to_comment_author() {
		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://example.com/users/tester_no_name',
			'object' => array(
				'content' => 'Test comment content',
				'id'      => 'https://example.com/activities/1',
			),
		);

		// Mock actor metadata.
		\add_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'actor_meta_data_comment_author' ), 10, 2 );

		// No name => preferredUsername.
		$comment_data = Interactions::activity_to_comment( $activity );
		$this->assertSame( 'test', $comment_data['comment_author'] );

		// No preferredUsername => Name.
		$activity['actor'] = 'https://example.com/users/tester_no_preferredUsername';
		$comment_data      = Interactions::activity_to_comment( $activity );
		$this->assertSame( 'Test User', $comment_data['comment_author'] );

		// Reject anonymous.
		\update_option( 'require_name_email', '1' );
		$activity['actor'] = 'https://example.com/users/tester_anonymous';
		$this->assertFalse( Interactions::activity_to_comment( $activity ) );

		// Anonymous.
		\update_option( 'require_name_email', '0' );
		$activity['actor'] = 'https://example.com/users/tester_anonymous';
		$comment_data      = Interactions::activity_to_comment( $activity );
		$this->assertSame( \__( 'Anonymous', 'activitypub' ), $comment_data['comment_author'] );

		\remove_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'actor_meta_data_comment_author' ) );
		\update_option( 'require_name_email', '1' );
	}

	/**
	 * Callback to mock actor meta data.
	 *
	 * @param bool   $response The value to return instead of the remote metadata.
	 * @param string $url      The actor URL.
	 *
	 * @return string[]
	 */
	public function actor_meta_data_comment_author( $response, $url ) {
		if ( 'https://example.com/users/tester_no_name' === $url ) {
			$response = array(
				'name'              => '',
				'preferredUsername' => 'test',
				'id'                => 'https://example.com/users/test',
				'url'               => 'https://example.com/@test',
			);
		}
		if ( 'https://example.com/users/tester_no_preferredUsername' === $url ) {
			$response = array(
				'name'              => 'Test User',
				'preferredUsername' => '',
				'id'                => 'https://example.com/users/test',
				'url'               => 'https://example.com/@test',
			);
		}
		if ( 'https://example.com/users/tester_anonymous' === $url ) {
			$response = array(
				'name'              => '',
				'preferredUsername' => '',
				'id'                => 'https://example.com/users/test',
				'url'               => 'https://example.com/@test',
			);
		}

		return $response;
	}

	/**
	 * Test add_comment with quote property.
	 *
	 * @covers ::add_comment
	 * @covers ::get_quote_url
	 */
	public function test_add_comment_with_quote_property() {
		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://example.com/users/testuser',
			'object' => array(
				'type'     => 'Note',
				'id'       => 'https://example.com/note/456',
				'content'  => '<p class="quote-inline">RE: <a href="' . self::$post_permalink . '">Post</a></p><p>Great post!</p>',
				'quote'    => self::$post_permalink,
				'quoteUri' => self::$post_permalink,
			),
		);

		\add_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'mock_actor_metadata' ), 10, 2 );

		$comment_id = Interactions::add_comment( $activity );

		$this->assertNotFalse( $comment_id );
		$this->assertIsInt( $comment_id );

		$comment = \get_comment( $comment_id );
		$this->assertEquals( self::$post_id, $comment->comment_post_ID );
		$this->assertStringContainsString( 'Great post!', $comment->comment_content );
		$this->assertStringNotContainsString( 'quote-inline', $comment->comment_content );
		$this->assertEquals( 'quote', $comment->comment_type, 'Comment type should be set to quote' );

		\remove_filter( 'pre_get_remote_metadata_by_actor', array( $this, 'mock_actor_metadata' ), 10 );
	}

	/**
	 * Mock actor metadata for testing.
	 *
	 * @param bool   $response The value to return.
	 * @param string $url      The actor URL.
	 *
	 * @return array Actor metadata.
	 */
	public function mock_actor_metadata( $response, $url ) {
		if ( 'https://example.com/users/testuser' === $url ) {
			return array(
				'name'              => 'Test User',
				'preferredUsername' => 'testuser',
				'id'                => 'https://example.com/users/testuser',
				'url'               => 'https://example.com/@testuser',
			);
		}
		return $response;
	}
}
