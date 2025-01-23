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
	 * @param WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create(
			array(
				'role' => 'author',
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
		$notification = new Notification(
			'follow',
			'https://example.com/author',
			array(
				'object' => 'https://example.com/follow/1',
			),
			self::$user_id
		);

		// Mock remote metadata.
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () {
				return array(
					'name' => 'Test Follower',
					'url'  => 'https://example.com/author',
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

		Mailer::new_follower( $notification );

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
		\delete_option( 'activitypub_mailer_new_follower' );
		\delete_option( 'activitypub_mailer_new_dm' );

		Mailer::init();

		$this->assertEquals( 10, \has_filter( 'comment_notification_subject', array( Mailer::class, 'comment_notification_subject' ) ) );
		$this->assertEquals( 10, \has_filter( 'comment_notification_text', array( Mailer::class, 'comment_notification_text' ) ) );
		$this->assertEquals( 10, \has_action( 'activitypub_notification_follow', array( Mailer::class, 'new_follower' ) ) );
		$this->assertEquals( 10, \has_action( 'activitypub_inbox_create', array( Mailer::class, 'direct_message' ) ) );

		\remove_action( 'activitypub_notification_follow', array( Mailer::class, 'new_follower' ) );
		\remove_action( 'activitypub_inbox_create', array( Mailer::class, 'direct_message' ) );

		\update_option( 'activitypub_mailer_new_follower', '0' );
		\update_option( 'activitypub_mailer_new_dm', '0' );

		Mailer::init();

		$this->assertEquals( false, \has_action( 'activitypub_notification_follow', array( Mailer::class, 'new_follower' ) ) );
		$this->assertEquals( false, \has_action( 'activitypub_inbox_create', array( Mailer::class, 'direct_message' ) ) );
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
						'content' => 'Test direct message',
					),
				),
			),
			'public+reply'     => array(
				false,
				array(
					'actor'  => 'https://example.com/author',
					'object' => array(
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
				'Interesting story from @test about people who don\'t own their own domain.' . PHP_EOL . PHP_EOL . '"This is not a new issue, of course, but Service’s implementation shows limitations."',
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
}
