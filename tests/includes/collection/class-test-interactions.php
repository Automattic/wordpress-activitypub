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
		$this->assertEquals( 'https://example.com/icon', get_comment_meta( $basic_comment_id, 'avatar_url', true ) );
		$this->assertEquals( 'activitypub', get_comment_meta( $basic_comment_id, 'protocol', true ) );
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
		$interactions = Interactions::get_interaction_by_id( $id );
		$this->assertIsArray( $interactions );
		$this->assertEquals( $comment->comment_ID, $interactions[0]->comment_ID );

		$comment      = \Activitypub\object_id_to_comment( $id );
		$interactions = Interactions::get_interaction_by_id( $url );
		$this->assertIsArray( $interactions );
		$this->assertEquals( $comment->comment_ID, $interactions[0]->comment_ID );
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
}
