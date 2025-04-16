<?php
/**
 * Test Mailer Class.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests;

use Activitypub\Mailer;
use Activitypub\Collection\Actors;
use Activitypub\Notification;
use WP_UnitTestCase;

/**
 * Test Mailer class.
 *
 * @coversDefaultClass \Activitypub\Mailer
 */
class Test_Mailer extends WP_UnitTestCase {
	/**
	 * A test post.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * A test user.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		$blog_prefix = $GLOBALS['wpdb']->get_blog_prefix();

		self::$user_id = $factory->user->create(
			array(
				'role'       => 'author',
				'meta_input' => array(
					$blog_prefix . 'activitypub_mailer_new_dm'       => 1,
					$blog_prefix . 'activitypub_mailer_new_follower' => 1,
					$blog_prefix . 'activitypub_mailer_new_mention'  => 1,
				),
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author' => self::$user_id,
				'post_title'  => 'Test Post',
			)
		);
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post_id, true );
		wp_delete_user( self::$user_id );
	}

	/**
	 * Test comment notification subject for ActivityPub comments.
	 *
	 * @covers ::comment_notification_subject
	 */
	public function test_comment_like_notification() {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'    => self::$post_id,
				'comment_type'       => 'like',
				'comment_author'     => 'Test Author',
				'comment_author_url' => 'https://example.com/author',
				'comment_author_IP'  => '127.0.0.1',
			)
		);

		update_comment_meta( $comment_id, 'protocol', 'activitypub' );

		$subject = Mailer::comment_notification_subject( 'Default Subject', $comment_id );

		$this->assertStringContainsString( 'Like', $subject );
		$this->assertStringContainsString( 'Test Post', $subject );
		$this->assertStringContainsString( get_option( 'blogname' ), $subject );

		$text = Mailer::comment_notification_text( 'Default Message', $comment_id );

		$this->assertStringContainsString( 'Test Post', $text );
		$this->assertStringContainsString( 'Test Author', $text );
		$this->assertStringContainsString( 'Like', $text );
		$this->assertStringContainsString( 'https://example.com/author', $text );
		$this->assertStringContainsString( '127.0.0.1', $text );

		// Test with non-ActivityPub comment.
		$regular_comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => self::$post_id,
			)
		);

		$subject = Mailer::comment_notification_subject( 'Default Subject', $regular_comment_id );
		$this->assertEquals( 'Default Subject', $subject );

		// Clean up.
		wp_delete_comment( $comment_id, true );
		wp_delete_comment( $regular_comment_id, true );
	}

	/**
	 * Test comment notification text for ActivityPub comments.
	 *
	 * @covers ::comment_notification_text
	 */
	public function test_comment_repost_notification() {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'    => self::$post_id,
				'comment_type'       => 'repost',
				'comment_author'     => 'Test Author',
				'comment_author_url' => 'https://example.com/author',
				'comment_author_IP'  => '127.0.0.1',
			)
		);

		update_comment_meta( $comment_id, 'protocol', 'activitypub' );

		$subject = Mailer::comment_notification_subject( 'Default Subject', $comment_id );

		$this->assertStringContainsString( 'Repost', $subject );
		$this->assertStringContainsString( 'Test Post', $subject );
		$this->assertStringContainsString( get_option( 'blogname' ), $subject );

		$text = Mailer::comment_notification_text( 'Default Message', $comment_id );

		$this->assertStringContainsString( 'Test Post', $text );
		$this->assertStringContainsString( 'Test Author', $text );
		$this->assertStringContainsString( 'Repost', $text );
		$this->assertStringContainsString( 'https://example.com/author', $text );
		$this->assertStringContainsString( '127.0.0.1', $text );

		// Test with non-ActivityPub comment.
		$regular_comment_id = wp_insert_comment(
			array(
				'comment_post_ID' => self::$post_id,
			)
		);

		$text = Mailer::comment_notification_text( 'Default Message', $regular_comment_id );
		$this->assertEquals( 'Default Message', $text );

		// Clean up.
		wp_delete_comment( $comment_id, true );
		wp_delete_comment( $regular_comment_id, true );
	}

	/**
	 * Test new follower notification.
	 *
	 * @covers ::new_follower
	 */
	public function test_new_follower() {
		$activity = array(
			'type'   => 'Follow',
			'actor'  => 'https://example.com/author',
			'object' => 'https://example.com/follow/1',
		);

		// Mock remote metadata.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () {
				return array(
					'name'              => 'Test Follower',
					'url'               => 'https://example.com/author',
					'preferredUsername' => 'follower',
				);
			}
		);

		// Capture email.
		add_filter(
			'wp_mail',
			function ( $args ) {
				$this->assertStringContainsString( 'Test Follower', $args['subject'] );
				$this->assertStringContainsString( 'https://example.com/author', $args['message'] );
				$this->assertEquals( get_user_by( 'id', self::$user_id )->user_email, $args['to'] );
				return $args;
			}
		);

		Mailer::new_follower( $activity, self::$user_id );

		// Clean up.
		remove_all_filters( 'pre_get_remote_metadata_by_actor' );
		remove_all_filters( 'wp_mail' );
	}

	/**
	 * Test initialization of filters and actions.
	 *
	 * @covers ::init
	 */
	public function test_init() {
		Mailer::init();

		$this->assertEquals( 10, \has_filter( 'comment_notification_subject', array( Mailer::class, 'comment_notification_subject' ) ) );
		$this->assertEquals( 10, \has_filter( 'comment_notification_text', array( Mailer::class, 'comment_notification_text' ) ) );
		$this->assertEquals( 10, \has_action( 'activitypub_inbox_follow', array( Mailer::class, 'new_follower' ) ) );
		$this->assertEquals( 10, \has_action( 'activitypub_inbox_create', array( Mailer::class, 'direct_message' ) ) );
		$this->assertEquals( 10, \has_action( 'activitypub_inbox_create', array( Mailer::class, 'mention' ) ) );
	}

	/**
	 * Data provider for direct message notification.
	 *
	 * @return array
	 */
	public function direct_message_provider() {
		return array(
			'to'               => array(
				true,
				array(
					'actor'  => 'https://example.com/author',
					'object' => array(
						'id'      => 'https://example.com/post/1',
						'content' => 'Test direct message',
					),
					'to'     => array( 'user_url' ),
				),
			),
			'none'             => array(
				false,
				array(
					'actor'  => 'https://example.com/author',
					'object' => array(
						'id'      => 'https://example.com/post/1',
						'content' => 'Test direct message',
					),
				),
			),
			'public+reply'     => array(
				false,
				array(
					'actor'  => 'https://example.com/author',
					'object' => array(
						'id'        => 'https://example.com/post/1',
						'content'   => 'Test public reply',
						'inReplyTo' => 'https://example.com/post/1',
					),
					'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
				),
			),
			'public+reply+cc'  => array(
				false,
				array(
					'actor'  => 'https://example.com/author',
					'object' => array(
						'id'        => 'https://example.com/post/1',
						'content'   => 'Test public reply',
						'inReplyTo' => 'https://example.com/post/1',
					),
					'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
					'cc'     => array( 'user_url' ),
				),
			),
			'public+followers' => array(
				false,
				array(
					'actor'  => 'https://example.com/author',
					'object' => array(
						'id'        => 'https://example.com/post/1',
						'content'   => 'Test public activity',
						'inReplyTo' => null,
					),
					'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
					'cc'     => array( 'https://example.com/followers' ),
				),
			),
			'followers'        => array(
				false,
				array(
					'actor'  => 'https://example.com/author',
					'object' => array(
						'id'        => 'https://example.com/post/1',
						'content'   => 'Test activity just to followers',
						'inReplyTo' => null,
					),
					'to'     => array( 'https://example.com/followers' ),
				),
			),
			'reply+cc'         => array(
				false,
				array(
					'actor'  => 'https://example.com/author',
					'object' => array(
						'id'        => 'https://example.com/post/1',
						'content'   => 'Reply activity to me and to followers',
						'inReplyTo' => 'https://example.com/post/1',
					),
					'to'     => array( 'https://example.com/followers' ),
					'cc'     => array( 'user_url' ),
				),
			),
		);
	}

	/**
	 * Test direct message notification.
	 *
	 * @param bool  $send_email Whether email should be sent.
	 * @param array $activity   Activity object.
	 * @dataProvider direct_message_provider
	 * @covers ::direct_message
	 */
	public function test_direct_message( $send_email, $activity ) {
		$user_id = self::$user_id;
		$mock    = new \MockAction();

		// We need to replace back in the user URL because the user_id is not available in the data provider.
		$replace = function ( $url ) use ( $user_id ) {
			if ( 'user_url' === $url ) {
				return Actors::get_by_id( $user_id )->get_id();

			}
			return $url;
		};

		foreach ( $activity as $key => $value ) {
			if ( is_array( $value ) ) {
				$activity[ $key ] = array_map( $replace, $value );
			} else {
				$activity[ $key ] = $replace( $value );
			}
		}

		// Mock remote metadata.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () {
				return array(
					'name' => 'Test Sender',
					'url'  => 'https://example.com/author',
				);
			}
		);
		add_filter( 'wp_mail', array( $mock, 'filter' ), 1 );

		if ( $send_email ) {
			// Capture email.
			add_filter(
				'wp_mail',
				function ( $args ) use ( $user_id, $activity ) {
					$this->assertStringContainsString( 'Direct Message', $args['subject'] );
					$this->assertStringContainsString( 'Test Sender', $args['subject'] );
					$this->assertStringContainsString( $activity['object']['content'], $args['message'] );
					$this->assertStringContainsString( 'https://example.com/author', $args['message'] );
					$this->assertEquals( get_user_by( 'id', $user_id )->user_email, $args['to'] );
					return $args;
				}
			);
		} else {
			add_filter(
				'wp_mail',
				function ( $args ) {
					$this->fail( 'Email should not be sent for public activity' );
					return $args;
				}
			);

		}

		Mailer::direct_message( $activity, $user_id );

		$this->assertEquals( $send_email ? 1 : 0, $mock->get_call_count() );

		// Clean up.
		remove_all_filters( 'pre_get_remote_metadata_by_actor' );
		remove_all_filters( 'wp_mail' );
		wp_delete_user( $user_id );
	}

	/**
	 * Data provider for direct message notification text.
	 *
	 * @return array
	 */
	public function direct_message_text_provider() {
		return array(
			'HTML entities' => array(
				json_decode( '"<p>Interesting story from <span class=\"h-card\" translate=\"no\"><a href=\"https:\/\/example.com\/@test\" class=\"u-url mention\">@<span>test<\/span><\/a><\/span> about people who don&#39;t own their own domain.<\/p><p>&quot;This is not a new issue, of course, but Service\u2019s implementation shows limitations.&quot;<\/p>"' ),
				'<p>Interesting story from <span class="h-card"><a href="https://example.com/@test" class="u-url mention">@<span>test</span></a></span> about people who don&#039;t own their own domain.</p><p>&quot;This is not a new issue, of course, but Service’s implementation shows limitations.&quot;</p>',
			),
			'invalid HTML'  => array(
				json_decode( '"<ptest"' ),
				'',
			),
		);
	}

	/**
	 * Test direct message notification text.
	 *
	 * @param string $text     Text to test.
	 * @param string $expected Expected result.
	 *
	 * @covers ::direct_message
	 * @dataProvider direct_message_text_provider
	 */
	public function test_direct_message_text( $text, $expected ) {
		$user_id = self::$user_id;

		$activity = array(
			'actor'  => 'https://example.com/author',
			'object' => array(
				'id'      => 'https://example.com/post/1',
				'type'    => 'Note',
				'content' => $text,
			),
			'to'     => array( Actors::get_by_id( $user_id )->get_id() ),
		);

		// Mock remote metadata.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () {
				return array(
					'name' => 'Test Sender',
					'url'  => 'https://example.com/author',
				);
			}
		);

		// Capture email.
		add_filter(
			'wp_mail',
			function ( $args ) use ( $expected, $user_id ) {
				$this->assertStringContainsString( $expected, $args['message'] );
				$this->assertEquals( get_user_by( 'id', $user_id )->user_email, $args['to'] );
				return $args;
			}
		);

		Mailer::direct_message( $activity, $user_id );

		// Clean up.
		remove_all_filters( 'pre_get_remote_metadata_by_actor' );
		remove_all_filters( 'wp_mail' );
		wp_delete_user( $user_id );
	}

	/**
	 * Test new follower notification when user option is disabled.
	 *
	 * @covers ::new_follower
	 */
	public function test_new_follower_with_disabled_option() {
		$activity = array(
			'type'   => 'Follow',
			'actor'  => 'https://example.com/author',
			'object' => 'https://example.com/follow/1',
		);

		// Set user option to false.
		update_user_option( self::$user_id, 'activitypub_mailer_new_follower', false );

		// Add a filter to fail the test if an email is sent.
		$mock = new \MockAction();
		add_action( 'wp_before_load_template', array( $mock, 'action' ) );

		// Call the method.
		Mailer::new_follower( $activity, self::$user_id );

		// Assert no email was sent.
		$this->assertEquals( 0, $mock->get_call_count() );

		// Clean up.
		remove_all_filters( 'pre_get_remote_metadata_by_actor' );
		remove_all_filters( 'wp_mail' );
		delete_user_option( self::$user_id, 'activitypub_mailer_new_follower' );
	}

	/**
	 * Test direct message notification when user option is disabled.
	 *
	 * @covers ::direct_message
	 */
	public function test_direct_message_with_disabled_option() {
		$activity = array(
			'actor'  => 'https://example.com/author',
			'object' => array(
				'id'      => 'https://example.com/post/1',
				'content' => 'Test direct message',
			),
			'to'     => array( Actors::get_by_id( self::$user_id )->get_id() ),
		);

		// Set user option to false.
		update_user_option( self::$user_id, 'activitypub_mailer_new_dm', false );

		// Add a filter to fail the test if an email is sent.
		$mock = new \MockAction();
		add_action( 'wp_before_load_template', array( $mock, 'action' ) );

		// Call the method.
		Mailer::direct_message( $activity, self::$user_id );

		// Assert no email was sent.
		$this->assertEquals( 0, $mock->get_call_count() );

		// Clean up.
		remove_all_filters( 'wp_before_load_template' );
		delete_user_option( self::$user_id, 'activitypub_mailer_new_dm' );
	}

	/**
	 * Test mention notification when user option is disabled.
	 *
	 * @covers ::mention
	 */
	public function test_mention_with_disabled_option() {
		$activity = array(
			'actor'  => 'https://example.com/author',
			'object' => array(
				'id'      => 'https://example.com/post/1',
				'content' => 'Test mention',
			),
			'cc'     => array( Actors::get_by_id( self::$user_id )->get_id() ),
		);

		// Set user option to false.
		update_user_option( self::$user_id, 'activitypub_mailer_new_mention', false );

		// Add a filter to fail the test if an email is sent.
		$mock = new \MockAction();
		add_action( 'wp_before_load_template', array( $mock, 'action' ) );

		// Call the method.
		Mailer::mention( $activity, self::$user_id );

		// Assert no email was sent.
		$this->assertEquals( 0, $mock->get_call_count() );

		// Clean up.
		remove_all_filters( 'wp_before_load_template' );
		delete_user_option( self::$user_id, 'activitypub_mailer_new_mention' );
	}

	/**
	 * Test new follower notification for blog user when option is disabled.
	 *
	 * @covers ::new_follower
	 */
	public function test_blog_new_follower_with_disabled_option() {
		// Set blog option to false (0).
		update_option( 'activitypub_blog_user_mailer_new_follower', '0' );
		update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$activity = array(
			'type'   => 'Follow',
			'actor'  => 'https://example.com/author',
			'object' => 'https://example.com/follow/1',
		);

		// Add a filter to fail the test if an email is sent.
		$mock = new \MockAction();
		add_action( 'wp_before_load_template', array( $mock, 'action' ) );

		// Call the method with blog user ID.
		Mailer::new_follower( $activity, Actors::BLOG_USER_ID );

		// Assert no email was sent.
		$this->assertEquals( 0, $mock->get_call_count() );

		// Clean up.
		remove_all_filters( 'wp_before_load_template' );
		delete_option( 'activitypub_blog_user_mailer_new_follower' );
		delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test direct message notification for blog user when option is disabled.
	 *
	 * @covers ::direct_message
	 */
	public function test_blog_direct_message_with_disabled_option() {
		// Set blog option to false (0).
		update_option( 'activitypub_blog_user_mailer_new_dm', '0' );
		update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$activity = array(
			'actor'  => 'https://example.com/author',
			'object' => array(
				'id'      => 'https://example.com/post/1',
				'content' => 'Test direct message',
			),
			'to'     => array( Actors::get_by_id( Actors::BLOG_USER_ID )->get_id() ),
		);

		// Add a filter to fail the test if an email is sent.
		$mock = new \MockAction();
		add_action( 'wp_before_load_template', array( $mock, 'action' ) );

		// Call the method with blog user ID.
		Mailer::direct_message( $activity, Actors::BLOG_USER_ID );

		// Assert no email was sent.
		$this->assertEquals( 0, $mock->get_call_count() );

		// Clean up.
		remove_all_filters( 'wp_before_load_template' );
		delete_option( 'activitypub_blog_user_mailer_new_dm' );
		delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test mention notification for blog user when option is disabled.
	 *
	 * @covers ::mention
	 */
	public function test_blog_mention_with_disabled_option() {
		// Set blog option to false (0).
		update_option( 'activitypub_blog_user_mailer_new_mention', '0' );
		update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$activity = array(
			'actor'  => 'https://example.com/author',
			'object' => array(
				'id'      => 'https://example.com/post/1',
				'content' => 'Test mention',
			),
			'cc'     => array( Actors::get_by_id( Actors::BLOG_USER_ID )->get_id() ),
		);

		// Add a filter to fail the test if an email is sent.
		$mock = new \MockAction();
		add_action( 'wp_before_load_template', array( $mock, 'action' ) );

		// Call the method with blog user ID.
		Mailer::mention( $activity, Actors::BLOG_USER_ID );

		// Assert no email was sent.
		$this->assertEquals( 0, $mock->get_call_count() );

		// Clean up.
		remove_all_filters( 'wp_before_load_template' );
		delete_option( 'activitypub_blog_user_mailer_new_mention' );
		delete_option( 'activitypub_actor_mode' );
	}
}
