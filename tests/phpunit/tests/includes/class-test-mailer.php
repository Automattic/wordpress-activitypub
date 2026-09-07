<?php
/**
 * Test Mailer Class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Actors;
use Activitypub\Handler\Create;
use Activitypub\Mailer;
use Activitypub\Rest\Server;
use Activitypub\Scheduler;
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
	}

	/**
	 * The reaction notification setting silences reaction emails (likes, reposts, quotes) without touching real replies.
	 *
	 * @covers ::maybe_prevent_reaction_notification
	 */
	public function test_reaction_notification_setting() {
		$like_id         = wp_insert_comment(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => 'like',
			)
		);
		$repost_id       = wp_insert_comment(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => 'repost',
			)
		);
		$quote_id        = wp_insert_comment(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => 'quote',
			)
		);
		$reply_id        = wp_insert_comment(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => 'comment',
			)
		);
		$legacy_reply_id = wp_insert_comment(
			array(
				'comment_post_ID' => self::$post_id,
				'comment_type'    => '',
			)
		);

		// Default (unset): reactions still notify, so behavior is unchanged for existing users.
		$this->assertTrue( Mailer::maybe_prevent_reaction_notification( true, $like_id ) );

		update_user_option( self::$user_id, 'activitypub_mailer_new_reaction', '0' );

		// Opt out silences every reaction type: like, repost, and quote.
		$this->assertFalse( Mailer::maybe_prevent_reaction_notification( true, $like_id ) );
		$this->assertFalse( Mailer::maybe_prevent_reaction_notification( true, $repost_id ) );
		$this->assertFalse( Mailer::maybe_prevent_reaction_notification( true, $quote_id ) );

		// Real replies are never affected, including legacy comments stored with an empty type.
		$this->assertTrue( Mailer::maybe_prevent_reaction_notification( true, $reply_id ) );
		$this->assertTrue( Mailer::maybe_prevent_reaction_notification( true, $legacy_reply_id ) );

		// The moderator notification is never gated by the author's reaction preference.
		$this->assertTrue( Mailer::maybe_prevent_comment_notification( true, $like_id ) );

		delete_user_option( self::$user_id, 'activitypub_mailer_new_reaction' );
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
	}

	/**
	 * Test that quote notifications include a link to the quoting post.
	 *
	 * @covers ::comment_notification_text
	 */
	public function test_comment_quote_notification_includes_quoting_post_link() {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'    => self::$post_id,
				'comment_type'       => 'quote',
				'comment_author'     => 'Quote Author',
				'comment_author_url' => 'https://example.com/author',
				'comment_author_IP'  => '127.0.0.1',
			)
		);

		update_comment_meta( $comment_id, 'protocol', 'activitypub' );
		update_comment_meta( $comment_id, 'source_url', 'https://example.com/quoting-post' );

		$text = Mailer::comment_notification_text( 'Default Message', $comment_id );

		$this->assertStringContainsString( 'Quoting post: https://example.com/quoting-post', $text );
	}

	/**
	 * Test that quote notifications omit the link when no source is stored.
	 *
	 * @covers ::comment_notification_text
	 */
	public function test_comment_quote_notification_without_source() {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'    => self::$post_id,
				'comment_type'       => 'quote',
				'comment_author'     => 'Quote Author',
				'comment_author_url' => 'https://example.com/author',
				'comment_author_IP'  => '127.0.0.1',
			)
		);

		update_comment_meta( $comment_id, 'protocol', 'activitypub' );

		$text = Mailer::comment_notification_text( 'Default Message', $comment_id );

		$this->assertStringNotContainsString( 'Quoting post:', $text );
	}

	/**
	 * Test that quote notifications fall back to the source ID when no source URL is stored.
	 *
	 * @covers ::comment_notification_text
	 */
	public function test_comment_quote_notification_falls_back_to_source_id() {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'    => self::$post_id,
				'comment_type'       => 'quote',
				'comment_author'     => 'Quote Author',
				'comment_author_url' => 'https://example.com/author',
				'comment_author_IP'  => '127.0.0.1',
			)
		);

		update_comment_meta( $comment_id, 'protocol', 'activitypub' );
		update_comment_meta( $comment_id, 'source_id', 'https://example.com/quoting-activity' );

		$text = Mailer::comment_notification_text( 'Default Message', $comment_id );

		$this->assertStringContainsString( 'https://example.com/quoting-activity', $text );
	}

	/**
	 * Test that non-quote notifications do not include a quoting-post link.
	 *
	 * @covers ::comment_notification_text
	 */
	public function test_comment_repost_notification_has_no_quoting_post_link() {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'    => self::$post_id,
				'comment_type'       => 'repost',
				'comment_author'     => 'Repost Author',
				'comment_author_url' => 'https://example.com/author',
				'comment_author_IP'  => '127.0.0.1',
			)
		);

		update_comment_meta( $comment_id, 'protocol', 'activitypub' );
		update_comment_meta( $comment_id, 'source_url', 'https://example.com/reposting-post' );

		$text = Mailer::comment_notification_text( 'Default Message', $comment_id );

		$this->assertStringNotContainsString( 'Quoting post:', $text );
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
		$remote_metadata_callback = function () {
			return array(
				'name'              => 'Test Follower',
				'url'               => 'https://example.com/author',
				'preferredUsername' => 'follower',
			);
		};
		add_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );

		// Capture email.
		$wp_mail_callback = function ( $args ) {
			$this->assertStringContainsString( 'Test Follower', $args['subject'] );
			$this->assertStringContainsString( 'https://example.com/author', $args['message'] );
			$this->assertEquals( get_user_by( 'id', self::$user_id )->user_email, $args['to'] );

			return $args;
		};
		add_filter( 'wp_mail', $wp_mail_callback );

		Mailer::new_follower( $activity, self::$user_id, true );

		// Clean up.
		remove_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );
		remove_filter( 'wp_mail', $wp_mail_callback );
	}

	/**
	 * Test new follower notification when the actor has no url and name.
	 *
	 * @covers ::new_follower
	 */
	public function test_new_follower_no_url_and_name() {
		$activity = array(
			'type'   => 'Follow',
			'actor'  => 'https://example.com/author',
			'object' => 'https://example.com/follow/1',
		);

		// Mock remote metadata.
		$remote_metadata_callback = function () {
			return array(
				'id'                => 'https://example.com/author',
				'preferredUsername' => 'follower',
			);
		};
		add_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );

		// Capture email.
		$wp_mail_callback = function ( $args ) {
			$this->assertStringContainsString( 'follower', $args['subject'] );
			$this->assertStringContainsString( 'https://example.com/author', $args['message'] );
			$this->assertEquals( get_user_by( 'id', self::$user_id )->user_email, $args['to'] );

			return $args;
		};
		add_filter( 'wp_mail', $wp_mail_callback );

		Mailer::new_follower( $activity, self::$user_id, true );

		// Clean up.
		remove_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );
		remove_filter( 'wp_mail', $wp_mail_callback );
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
		$this->assertEquals( 10, \has_action( 'activitypub_handled_follow', array( Mailer::class, 'new_follower' ) ) );
		$this->assertEquals( 10, \has_action( 'activitypub_handled_inbox_create', array( Mailer::class, 'direct_message' ) ) );
		$this->assertEquals( 20, \has_action( 'activitypub_handled_inbox_create', array( Mailer::class, 'mention' ) ) );

		// Notifications must not run on the pre-storage hook, which fires again for every redelivery.
		$this->assertFalse( \has_action( 'activitypub_inbox_create', array( Mailer::class, 'direct_message' ) ) );
		$this->assertFalse( \has_action( 'activitypub_inbox_create', array( Mailer::class, 'mention' ) ) );

		/*
		 * The mention reply check reads the comment that Create::handle_create() writes, so it has
		 * to run after it on the same hook.
		 */
		$this->assertGreaterThan(
			\has_action( 'activitypub_handled_inbox_create', array( Create::class, 'handle_create' ) ),
			\has_action( 'activitypub_handled_inbox_create', array( Mailer::class, 'mention' ) )
		);
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
					'type'   => 'Create',
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
					'type'   => 'Create',
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
					'type'   => 'Create',
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
					'type'   => 'Create',
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
					'type'   => 'Create',
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
					'type'   => 'Create',
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
					'type'   => 'Create',
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
		$remote_metadata_callback = function () {
			return array(
				'type' => 'Person',
				'name' => 'Test Sender',
				'url'  => 'https://example.com/author',
			);
		};
		add_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );
		add_filter( 'wp_mail', array( $mock, 'filter' ), 1 );

		if ( $send_email ) {
			// Capture email.
			$wp_mail_send_callback = function ( $args ) use ( $user_id, $activity ) {
				$this->assertStringContainsString( 'Direct Message', $args['subject'] );
				$this->assertStringContainsString( 'Test Sender', $args['subject'] );
				$this->assertStringContainsString( $activity['object']['content'], $args['message'] );
				$this->assertStringContainsString( 'https://example.com/author', $args['message'] );
				$this->assertEquals( get_user_by( 'id', $user_id )->user_email, $args['to'] );

				return $args;
			};
			add_filter( 'wp_mail', $wp_mail_send_callback );
		} else {
			$wp_mail_fail_callback = function () {
				$this->fail( 'Email should not be sent for public activity' );
			};
			add_filter( 'wp_mail', $wp_mail_fail_callback );
		}

		Mailer::direct_message( $activity, $user_id );

		$this->assertEquals( $send_email ? 1 : 0, $mock->get_call_count() );

		// Clean up.
		remove_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );
		remove_filter( 'wp_mail', array( $mock, 'filter' ), 1 );
		if ( $send_email ) {
			remove_filter( 'wp_mail', $wp_mail_send_callback );
		} else {
			remove_filter( 'wp_mail', $wp_mail_fail_callback );
		}
	}

	/**
	 * Test direct message notification from Bridgy.
	 *
	 * @covers ::direct_message
	 */
	public function test_direct_message_from_bridgy() {
		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://example.com/author',
			'object' => array(
				'id'      => 'https://example.com/post/1',
				'content' => 'Test direct message',
			),
			'to'     => array( Actors::get_by_id( self::$user_id )->get_id() ),
		);

		// Mock remote metadata.
		$remote_metadata_callback = function () {
			return array(
				'type' => 'Person',
				'name' => 'Test Sender',
				'url'  => array(
					'https://fed.brid.gy/r/https://example.com/author',
					'acct:author@example.com',
				),
			);
		};
		add_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );

		// Capture email.
		$wp_mail_callback = function ( $args ) {
			$this->assertStringContainsString(
				'<a href="https://fed.brid.gy/r/https://example.com/author">@Test Sender@fed.brid.gy</a>',
				$args['message']
			);

			return $args;
		};
		add_filter( 'wp_mail', $wp_mail_callback );

		// Call the method.
		Mailer::direct_message( $activity, self::$user_id );

		// Clean up.
		remove_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );
		remove_filter( 'wp_mail', $wp_mail_callback );
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
				'<p>Interesting story from <span class="h-card"><a href="https://example.com/@test" class="u-url mention">@<span>test</span></a></span> about people who don&#039;t own their own domain.</p> <p>&quot;This is not a new issue, of course, but Service’s implementation shows limitations.&quot;</p>',
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
			'type'   => 'Create',
			'actor'  => 'https://example.com/author',
			'object' => array(
				'id'      => 'https://example.com/post/1',
				'type'    => 'Note',
				'content' => $text,
			),
			'to'     => array( Actors::get_by_id( $user_id )->get_id() ),
		);

		// Mock remote metadata.
		$remote_metadata_callback = function () {
			return array(
				'type' => 'Person',
				'name' => 'Test Sender',
				'url'  => 'https://example.com/author',
			);
		};
		add_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );

		// Capture email.
		$wp_mail_callback = function ( $args ) use ( $expected, $user_id ) {
			$this->assertStringContainsString( $expected, $args['message'] );
			$this->assertEquals( get_user_by( 'id', $user_id )->user_email, $args['to'] );

			return $args;
		};
		add_filter( 'wp_mail', $wp_mail_callback );

		Mailer::direct_message( $activity, $user_id );

		// Clean up.
		remove_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );
		remove_filter( 'wp_mail', $wp_mail_callback );
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
		Mailer::new_follower( $activity, self::$user_id, true );

		// Assert no email was sent.
		$this->assertEquals( 0, $mock->get_call_count() );

		// Clean up.
		remove_action( 'wp_before_load_template', array( $mock, 'action' ) );
		delete_user_option( self::$user_id, 'activitypub_mailer_new_follower' );
	}

	/**
	 * Test direct message notification when user option is disabled.
	 *
	 * @covers ::direct_message
	 */
	public function test_direct_message_with_disabled_option() {
		$activity = array(
			'type'   => 'Create',
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
		remove_action( 'wp_before_load_template', array( $mock, 'action' ) );
		delete_user_option( self::$user_id, 'activitypub_mailer_new_dm' );
	}

	/**
	 * Test mention notification when user option is disabled.
	 *
	 * @covers ::mention
	 */
	public function test_mention_with_disabled_option() {
		$activity = array(
			'type'   => 'Create',
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
		remove_action( 'wp_before_load_template', array( $mock, 'action' ) );
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
		Mailer::new_follower( $activity, Actors::BLOG_USER_ID, true );

		// Assert no email was sent.
		$this->assertEquals( 0, $mock->get_call_count() );

		// Clean up.
		remove_action( 'wp_before_load_template', array( $mock, 'action' ) );
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
			'type'   => 'Create',
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
		remove_action( 'wp_before_load_template', array( $mock, 'action' ) );
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
			'type'   => 'Create',
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
		remove_action( 'wp_before_load_template', array( $mock, 'action' ) );
		delete_option( 'activitypub_blog_user_mailer_new_mention' );
		delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test new follower notification with array of user IDs.
	 *
	 * @covers ::new_follower
	 */
	public function test_new_follower_with_array() {
		$activity = array(
			'type'   => 'Follow',
			'actor'  => 'https://example.com/author',
			'object' => 'https://example.com/follow/1',
		);

		// Mock remote metadata.
		$remote_metadata_callback = function () {
			return array(
				'name'              => 'Test Follower',
				'url'               => 'https://example.com/author',
				'preferredUsername' => 'follower',
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );

		// Capture email.
		$wp_mail_callback = function ( $args ) {
			$this->assertStringContainsString( 'Test Follower', $args['subject'] );
			$this->assertStringContainsString( 'https://example.com/author', $args['message'] );
			$this->assertEquals( \get_user_by( 'id', self::$user_id )->user_email, $args['to'] );

			return $args;
		};
		\add_filter( 'wp_mail', $wp_mail_callback );

		// Pass array of user IDs (follows are always for single user, but handler passes array).
		Mailer::new_follower( $activity, array( self::$user_id ), true );

		// Clean up.
		\remove_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );
		\remove_filter( 'wp_mail', $wp_mail_callback );
	}

	/**
	 * Test direct message notification with array of user IDs.
	 *
	 * @covers ::direct_message
	 */
	public function test_direct_message_with_array() {
		$user_id = self::$user_id;

		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://example.com/author',
			'object' => array(
				'id'      => 'https://example.com/post/1',
				'content' => 'Test direct message',
			),
			'to'     => array( Actors::get_by_id( $user_id )->get_id() ),
		);

		// Mock remote metadata.
		$remote_metadata_callback = function () {
			return array(
				'type' => 'Person',
				'name' => 'Test Sender',
				'url'  => 'https://example.com/author',
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );

		$mock = new \MockAction();
		\add_filter( 'wp_mail', array( $mock, 'filter' ), 1 );

		// Capture email.
		$wp_mail_callback = function ( $args ) use ( $user_id ) {
			$this->assertStringContainsString( 'Direct Message', $args['subject'] );
			$this->assertStringContainsString( 'Test Sender', $args['subject'] );
			$this->assertEquals( \get_user_by( 'id', $user_id )->user_email, $args['to'] );

			return $args;
		};
		\add_filter( 'wp_mail', $wp_mail_callback );

		// Pass array of user IDs.
		Mailer::direct_message( $activity, array( $user_id ) );

		$this->assertEquals( 1, $mock->get_call_count() );

		// Clean up.
		\remove_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );
		\remove_filter( 'wp_mail', array( $mock, 'filter' ), 1 );
		\remove_filter( 'wp_mail', $wp_mail_callback );
	}

	/**
	 * Test mention notification with array of user IDs.
	 *
	 * @covers ::mention
	 */
	public function test_mention_with_array() {
		$user_id = self::$user_id;

		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://example.com/author',
			'object' => array(
				'id'      => 'https://example.com/post/1',
				'content' => 'Test mention',
				'tag'     => array(
					array(
						'type' => 'Mention',
						'href' => Actors::get_by_id( $user_id )->get_id(),
						'name' => '@test',
					),
				),
			),
			'cc'     => array( Actors::get_by_id( $user_id )->get_id() ),
		);

		// Mock remote metadata.
		$remote_metadata_callback = function () {
			return array(
				'type' => 'Person',
				'name' => 'Test Sender',
				'url'  => 'https://example.com/author',
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );

		$mock = new \MockAction();
		\add_filter( 'wp_mail', array( $mock, 'filter' ), 1 );

		// Capture email.
		$wp_mail_callback = function ( $args ) use ( $user_id ) {
			$this->assertStringContainsString( 'Mention', $args['subject'] );
			$this->assertStringContainsString( 'Test Sender', $args['subject'] );
			$this->assertEquals( \get_user_by( 'id', $user_id )->user_email, $args['to'] );

			return $args;
		};
		\add_filter( 'wp_mail', $wp_mail_callback );

		// Pass array of user IDs.
		Mailer::mention( $activity, array( $user_id ) );

		$this->assertEquals( 1, $mock->get_call_count() );

		// Clean up.
		\remove_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );
		\remove_filter( 'wp_mail', array( $mock, 'filter' ), 1 );
		\remove_filter( 'wp_mail', $wp_mail_callback );
	}

	/**
	 * Test direct message with multiple recipients filters correctly.
	 *
	 * @covers ::direct_message
	 */
	public function test_direct_message_filters_recipients() {
		$user_id = self::$user_id;

		// Create a second user not in the TO field.
		$other_user_id = self::factory()->user->create(
			array(
				'role'       => 'author',
				'meta_input' => array(
					$GLOBALS['wpdb']->get_blog_prefix() . 'activitypub_mailer_new_dm' => 1,
				),
			)
		);

		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://example.com/author',
			'object' => array(
				'id'      => 'https://example.com/post/1',
				'content' => 'Test direct message',
			),
			// Only user_id is in TO, not other_user_id.
			'to'     => array( Actors::get_by_id( $user_id )->get_id() ),
		);

		// Mock remote metadata.
		$remote_metadata_callback = function () {
			return array(
				'name' => 'Test Sender',
				'url'  => 'https://example.com/author',
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );

		$mock = new \MockAction();
		\add_filter( 'wp_mail', array( $mock, 'filter' ), 1 );

		// Capture email and verify only the correct user gets it.
		$wp_mail_callback = function ( $args ) use ( $user_id, $other_user_id ) {
			$this->assertEquals( \get_user_by( 'id', $user_id )->user_email, $args['to'] );
			$this->assertNotEquals( \get_user_by( 'id', $other_user_id )->user_email, $args['to'] );

			return $args;
		};
		\add_filter( 'wp_mail', $wp_mail_callback );

		// Pass array with both users, but only one should receive email.
		Mailer::direct_message( $activity, array( $user_id, $other_user_id ) );

		// Should only send 1 email (to user_id, not other_user_id).
		$this->assertEquals( 1, $mock->get_call_count() );

		// Clean up.
		\remove_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );
		\remove_filter( 'wp_mail', array( $mock, 'filter' ), 1 );
		\remove_filter( 'wp_mail', $wp_mail_callback );
	}

	/**
	 * Test mention with multiple recipients filters correctly.
	 *
	 * @covers ::mention
	 */
	public function test_mention_filters_recipients() {
		$user_id = self::$user_id;

		// Create a second user not in the CC field.
		$other_user_id = self::factory()->user->create(
			array(
				'role'       => 'author',
				'meta_input' => array(
					$GLOBALS['wpdb']->get_blog_prefix() . 'activitypub_mailer_new_mention' => 1,
				),
			)
		);

		$activity = array(
			'actor'  => 'https://example.com/author',
			'object' => array(
				'id'      => 'https://example.com/post/1',
				'content' => 'Test mention',
				'tag'     => array(
					array(
						'type' => 'Mention',
						'href' => Actors::get_by_id( $user_id )->get_id(),
						'name' => '@test',
					),
				),
			),
			// Only user_id is in CC, not other_user_id.
			'cc'     => array( Actors::get_by_id( $user_id )->get_id() ),
		);

		// Mock remote metadata.
		$remote_metadata_callback = function () {
			return array(
				'name' => 'Test Sender',
				'url'  => 'https://example.com/author',
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );

		$mock = new \MockAction();
		\add_filter( 'wp_mail', array( $mock, 'filter' ), 1 );

		// Capture email and verify only the correct user gets it.
		$wp_mail_callback = function ( $args ) use ( $user_id, $other_user_id ) {
			$this->assertEquals( \get_user_by( 'id', $user_id )->user_email, $args['to'] );
			$this->assertNotEquals( \get_user_by( 'id', $other_user_id )->user_email, $args['to'] );

			return $args;
		};
		\add_filter( 'wp_mail', $wp_mail_callback );

		// Pass array with both users, but only one should receive email.
		Mailer::mention( $activity, array( $user_id, $other_user_id ) );

		// Should only send 1 email (to user_id, not other_user_id).
		$this->assertEquals( 1, $mock->get_call_count() );

		// Clean up.
		\remove_filter( 'pre_get_remote_metadata_by_actor', $remote_metadata_callback );
		\remove_filter( 'wp_mail', array( $mock, 'filter' ), 1 );
		\remove_filter( 'wp_mail', $wp_mail_callback );
	}

	/**
	 * Test that email notifications are prevented for comments on ap_post.
	 *
	 * @covers ::maybe_prevent_comment_notification
	 */
	public function test_prevent_email_notifications_for_ap_post_comments() {
		// Create an ap_post.
		$ap_post_id = self::factory()->post->create(
			array(
				'post_type'   => 'ap_post',
				'post_status' => 'publish',
			)
		);

		// Create a comment on the ap_post.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $ap_post_id,
				'comment_approved' => '1',
			)
		);

		// Test notify_post_author filter.
		$notify_author = \apply_filters( 'notify_post_author', true, $comment_id );
		$this->assertFalse( $notify_author, 'Email notifications to post author should be prevented for ap_post comments' );

		// Test notify_moderator filter.
		$notify_moderator = \apply_filters( 'notify_moderator', true, $comment_id );
		$this->assertFalse( $notify_moderator, 'Email notifications to moderator should be prevented for ap_post comments' );
	}

	/**
	 * Test that email notifications are NOT prevented for comments on regular posts.
	 *
	 * @covers ::maybe_prevent_comment_notification
	 */
	public function test_allow_email_notifications_for_regular_post_comments() {
		// Create a regular post.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => 1,
			)
		);

		// Create a comment on the regular post.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
			)
		);

		// Test notify_post_author filter.
		$notify_author = \apply_filters( 'notify_post_author', true, $comment_id );
		$this->assertTrue( $notify_author, 'Email notifications to post author should be allowed for regular post comments' );

		// Test notify_moderator filter.
		$notify_moderator = \apply_filters( 'notify_moderator', true, $comment_id );
		$this->assertTrue( $notify_moderator, 'Email notifications to moderator should be allowed for regular post comments' );
	}

	/**
	 * Test that email notifications respect existing false values.
	 *
	 * @covers ::maybe_prevent_comment_notification
	 */
	public function test_respect_existing_notification_settings() {
		// Create an ap_post.
		$ap_post_id = self::factory()->post->create(
			array(
				'post_type'   => 'ap_post',
				'post_status' => 'publish',
			)
		);

		// Create a comment on the ap_post.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $ap_post_id,
				'comment_approved' => '1',
			)
		);

		// Test that if notifications are already disabled, they stay disabled.
		$notify_author = \apply_filters( 'notify_post_author', false, $comment_id );
		$this->assertFalse( $notify_author, 'Should respect already disabled notifications' );

		$notify_moderator = \apply_filters( 'notify_moderator', false, $comment_id );
		$this->assertFalse( $notify_moderator, 'Should respect already disabled notifications' );
	}

	/**
	 * Test that users in CC without actual mention tags do not receive mention notifications.
	 *
	 * This tests the bug fix where users added to CC (e.g., because they follow the actor)
	 * were incorrectly receiving mention notifications even when not actually mentioned.
	 *
	 * @covers ::mention
	 */
	public function test_mention_requires_tag_not_just_cc() {
		$user_id = self::$user_id;

		// Activity with user in CC but NOT mentioned in tags.
		$activity = array(
			'actor'  => 'https://example.com/sports-account',
			'object' => array(
				'id'      => 'https://example.com/sports-account/posts/123',
				'type'    => 'Note',
				'content' => '<p>Join @user1 and @user2 on our stream...</p>',
				'tag'     => array(
					// Other users mentioned, but NOT the local user.
					array(
						'type' => 'Mention',
						'href' => 'https://example.com/user1',
						'name' => 'user1@example.com',
					),
					array(
						'type' => 'Mention',
						'href' => 'https://example.com/user2',
						'name' => 'user2@example.com',
					),
				),
			),
			// User is in CC (e.g., because they follow the actor).
			'cc'     => array( Actors::get_by_id( $user_id )->get_id() ),
		);

		// Mock remote metadata.
		$metadata_filter = function () {
			return array(
				'name' => 'Sports Account',
				'url'  => 'https://example.com/sports-account',
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $metadata_filter );

		$mock = new \MockAction();
		\add_filter( 'wp_mail', array( $mock, 'filter' ), 1 );

		// Trigger mention notification.
		Mailer::mention( $activity, $user_id );

		// Should NOT send any email because user is not actually mentioned in tags.
		$this->assertEquals( 0, $mock->get_call_count(), 'User in CC without mention tag should not receive notification' );

		// Clean up.
		\remove_filter( 'pre_get_remote_metadata_by_actor', $metadata_filter );
		\remove_filter( 'wp_mail', array( $mock, 'filter' ), 1 );
	}

	/**
	 * Test that users with actual mention tags DO receive mention notifications.
	 *
	 * @covers ::mention
	 */
	public function test_mention_with_tag_sends_notification() {
		$user_id = self::$user_id;

		// Activity with user properly mentioned in both CC and tags.
		$activity = array(
			'actor'  => 'https://example.com/author',
			'object' => array(
				'id'      => 'https://example.com/post/1',
				'type'    => 'Note',
				'content' => '<p>Hello @testuser, how are you?</p>',
				'tag'     => array(
					array(
						'type' => 'Mention',
						'href' => Actors::get_by_id( $user_id )->get_id(),
						'name' => '@testuser',
					),
				),
			),
			'cc'     => array( Actors::get_by_id( $user_id )->get_id() ),
		);

		// Mock remote metadata.
		$metadata_filter = function () {
			return array(
				'name' => 'Test Author',
				'url'  => 'https://example.com/author',
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $metadata_filter );

		$mock = new \MockAction();
		\add_filter( 'wp_mail', array( $mock, 'filter' ), 1 );

		// Capture email.
		$mail_filter = function ( $args ) use ( $user_id ) {
			$this->assertStringContainsString( 'Mention', $args['subject'] );
			$this->assertStringContainsString( 'Test Author', $args['subject'] );
			$this->assertEquals( \get_user_by( 'id', $user_id )->user_email, $args['to'] );
			return $args;
		};
		\add_filter( 'wp_mail', $mail_filter );

		// Trigger mention notification.
		Mailer::mention( $activity, $user_id );

		// Should send 1 email because user is properly mentioned.
		$this->assertEquals( 1, $mock->get_call_count(), 'User properly mentioned in tags should receive notification' );

		// Clean up.
		\remove_filter( 'pre_get_remote_metadata_by_actor', $metadata_filter );
		\remove_filter( 'wp_mail', array( $mock, 'filter' ), 1 );
		\remove_filter( 'wp_mail', $mail_filter );
	}

	/**
	 * Deliver an activity to a user's inbox the way a remote server would.
	 *
	 * @param array $data    The activity.
	 * @param int   $user_id The recipient.
	 */
	private function deliver_to_inbox( $data, $user_id ) {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );
		Server::init();

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/users/' . $user_id . '/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $data ) );
		\rest_get_server()->dispatch( $request );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Run the event the inbox queued, the way WP-Cron would.
	 *
	 * Deliveries only store and queue, so nothing is handed to the handlers until this runs.
	 *
	 * @param string $activity_id The activity ID.
	 */
	private function run_inbox_queue( $activity_id ) {
		$args      = array( $activity_id );
		$timestamp = \wp_next_scheduled( 'activitypub_inbox_create_item', $args );

		if ( ! $timestamp ) {
			return;
		}

		\wp_unschedule_event( $timestamp, 'activitypub_inbox_create_item', $args );
		Scheduler::process_inbox_activity( $activity_id );
	}

	/**
	 * A redelivered Direct Message only notifies once, all the way through the inbox.
	 *
	 * @covers ::direct_message
	 */
	public function test_a_redelivered_direct_message_only_notifies_once() {
		$user_id = self::$user_id;
		$data    = array(
			'id'     => 'https://example.com/activity/inbox-dm-1',
			'type'   => 'Create',
			'actor'  => 'https://example.com/author',
			'to'     => array( Actors::get_by_id( $user_id )->get_id() ),
			'object' => array(
				'id'      => 'https://example.com/note/inbox-dm-1',
				'type'    => 'Note',
				'content' => '<p>Hello there.</p>',
			),
		);

		$count = $this->count_mails(
			function () use ( $data, $user_id ) {
				$this->deliver_to_inbox( $data, $user_id );
				$this->deliver_to_inbox( $data, $user_id );
				$this->run_inbox_queue( $data['id'] );
			}
		);

		$this->assertEquals( 1, $count, 'A Direct Message delivered twice should only notify once.' );
	}

	/**
	 * A Direct Message delivered to the shared inbox still notifies.
	 *
	 * Most servers deliver here rather than to a per-actor inbox, and this path hands the
	 * notification hook every recipient at once instead of one per call.
	 *
	 * @covers ::direct_message
	 */
	public function test_a_direct_message_to_the_shared_inbox_still_notifies() {
		$user_id = self::$user_id;
		$data    = array(
			'id'     => 'https://example.com/activity/shared-dm-1',
			'type'   => 'Create',
			'actor'  => 'https://example.com/author',
			'to'     => array( Actors::get_by_id( $user_id )->get_id() ),
			'object' => array(
				'id'      => 'https://example.com/note/shared-dm-1',
				'type'    => 'Note',
				'content' => '<p>Hello there.</p>',
			),
		);

		$count = $this->count_mails(
			function () use ( $data ) {
				\add_filter( 'activitypub_defer_signature_verification', '__return_true' );
				Server::init();

				$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
				$request->set_header( 'Content-Type', 'application/activity+json' );
				$request->set_body( \wp_json_encode( $data ) );
				\rest_get_server()->dispatch( $request );

				\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
			}
		);

		$this->assertEquals( 1, $count, 'A Direct Message to the shared inbox should notify.' );
	}

	/**
	 * A mention inside a reply does not notify, because the reply becomes a comment instead.
	 *
	 * This is what the priority of the `mention` hook is for: it has to run after
	 * Create::handle_create() has stored the comment, or the reply check never sees it.
	 *
	 * @covers ::mention
	 */
	public function test_a_mention_in_a_reply_does_not_notify() {
		$user_id  = self::$user_id;
		$actor_id = Actors::get_by_id( $user_id )->get_id();
		$data     = array(
			'id'     => 'https://example.com/activity/inbox-reply-1',
			'type'   => 'Create',
			'actor'  => 'https://example.com/author',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc'     => array( $actor_id ),
			'object' => array(
				'id'        => 'https://example.com/note/inbox-reply-1',
				'type'      => 'Note',
				'url'       => 'https://example.com/note/inbox-reply-1',
				'inReplyTo' => \get_permalink( self::$post_id ),
				'content'   => '<p>Hello @testuser.</p>',
				'tag'       => array(
					array(
						'type' => 'Mention',
						'href' => $actor_id,
						'name' => '@testuser',
					),
				),
			),
		);

		$count = $this->count_mails(
			function () use ( $data, $user_id ) {
				$this->deliver_to_inbox( $data, $user_id );
				$this->run_inbox_queue( $data['id'] );
			},
			'Mention'
		);

		$this->assertEquals( 0, $count, 'A reply stored as a comment should not also send a mention email.' );
	}


	/**
	 * Count the emails sent while running a callback.
	 *
	 * @param callable $callback The code to run.
	 * @param string   $subject  Optional. Only count emails whose subject contains this. Default ''.
	 * @return int The number of emails sent.
	 */
	private function count_mails( $callback, $subject = '' ) {
		$metadata_filter = function () {
			return array(
				'name' => 'Test Author',
				'url'  => 'https://example.com/author',
			);
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $metadata_filter );

		$count      = 0;
		$count_mail = function ( $args ) use ( &$count, $subject ) {
			if ( '' === $subject || false !== \strpos( $args['subject'], $subject ) ) {
				++$count;
			}

			return $args;
		};
		\add_filter( 'wp_mail', $count_mail, 1 );

		$callback();

		\remove_filter( 'wp_mail', $count_mail, 1 );
		\remove_filter( 'pre_get_remote_metadata_by_actor', $metadata_filter );

		return $count;
	}
}
