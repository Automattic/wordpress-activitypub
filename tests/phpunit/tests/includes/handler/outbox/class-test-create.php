<?php
/**
 * Test file for Outbox Create Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler\Outbox;

use Activitypub\Handler\Outbox\Create;
use Activitypub\Scheduler\Post;

/**
 * Test class for Outbox Create Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Outbox\Create
 */
class Test_Create extends \WP_UnitTestCase {

	/**
	 * Test outgoing Note creates a post with status post format.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_note_creates_post_with_status_format() {
		// Prevent wp_insert_post() from triggering the full outbox chain.
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$user_id  = self::factory()->user->create();
		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>Hello from the Fediverse!</p>',
			),
		);

		$result = Create::handle_create( $activity, $user_id );

		$this->assertInstanceOf( 'WP_Post', $result );
		$this->assertEquals( 'status', \get_post_format( $result->ID ) );
		$this->assertStringContainsString( 'Hello from the Fediverse!', $result->post_content );

		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );
	}

	/**
	 * Test outgoing Article creates a post without post format.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_article_creates_post_without_format() {
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$user_id  = self::factory()->user->create();
		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'    => 'Article',
				'name'    => 'My Article Title',
				'content' => '<p>Article body here.</p>',
			),
		);

		$result = Create::handle_create( $activity, $user_id );

		$this->assertInstanceOf( 'WP_Post', $result );
		$this->assertFalse( \get_post_format( $result->ID ) );
		$this->assertEquals( 'My Article Title', $result->post_title );

		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );
	}

	/**
	 * Test outgoing private visibility returns false.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_private_visibility_returns_false() {
		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://example.com/users/recipient' ), // Private message.
			'object' => array(
				'type'    => 'Note',
				'content' => 'Private note.',
				'to'      => array( 'https://example.com/users/recipient' ),
			),
		);

		$result = Create::handle_create( $activity, 1, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );

		$this->assertFalse( $result );
	}

	/**
	 * Test outgoing non-Note/Article types return null.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_unsupported_type_returns_null() {
		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'    => 'Event',
				'content' => 'An event.',
			),
		);

		$result = Create::handle_create( $activity, 1 );

		$this->assertNull( $result );
	}

	/**
	 * Test outgoing reply to non-local URL is not handled.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_reply_to_remote_url() {
		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'      => 'Note',
				'content'   => 'A reply.',
				'inReplyTo' => 'https://example.com/note/123',
			),
		);

		$result = Create::handle_create( $activity, 1 );

		// Reply to non-local URL: no local post found, returns false.
		$this->assertFalse( $result );
	}

	/**
	 * Test outgoing invalid (non-array) object returns WP_Error.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_invalid_object_returns_error() {
		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => 'https://example.com/note/1',
		);

		$result = Create::handle_create( $activity, 1 );

		$this->assertWPError( $result );
		$this->assertEquals( 'invalid_object', $result->get_error_code() );
	}

	/**
	 * Test outgoing post sets content and title correctly.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_post_content_and_title() {
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$user_id  = self::factory()->user->create();
		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'    => 'Article',
				'name'    => 'Specific Title',
				'content' => '<p>Specific content here.</p>',
				'summary' => 'A brief summary.',
			),
		);

		$result = Create::handle_create( $activity, $user_id );

		$this->assertInstanceOf( 'WP_Post', $result );
		$this->assertEquals( 'Specific Title', $result->post_title );
		$this->assertEquals( '<p>Specific content here.</p>', $result->post_content );
		$this->assertEquals( 'A brief summary.', $result->post_excerpt );

		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );
	}

	/**
	 * Test outgoing post auto-generates title from content when name is empty.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_post_generates_title_from_content() {
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$user_id  = self::factory()->user->create();
		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'    => 'Note',
				'content' => '<p>This is a short note without a title field.</p>',
			),
		);

		$result = Create::handle_create( $activity, $user_id );

		$this->assertInstanceOf( 'WP_Post', $result );
		$this->assertNotEmpty( $result->post_title );
		$this->assertStringContainsString( 'This is a short', $result->post_title );

		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );
	}

	/**
	 * Test outgoing post fires activitypub_outbox_created_post action.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_post_fires_action() {
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$user_id = self::factory()->user->create();
		$fired   = false;

		$callback = function () use ( &$fired ) {
			$fired = true;
		};
		\add_action( 'activitypub_outbox_created_post', $callback );

		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'    => 'Note',
				'content' => 'Testing action hook.',
			),
		);

		Create::handle_create( $activity, $user_id );

		$this->assertTrue( $fired, 'activitypub_outbox_created_post action should fire.' );

		\remove_action( 'activitypub_outbox_created_post', $callback );
		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );
	}

	/**
	 * Test outgoing post sets user_id as post_author.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_post_sets_author() {
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$user_id  = self::factory()->user->create();
		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'    => 'Note',
				'content' => 'Author test.',
			),
		);

		$result = Create::handle_create( $activity, $user_id );

		$this->assertInstanceOf( 'WP_Post', $result );
		$this->assertEquals( $user_id, (int) $result->post_author );

		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );
	}

	/**
	 * Test outgoing reply to local post creates a comment.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_reply_to_local_post() {
		\remove_action( 'wp_insert_comment', array( \Activitypub\Scheduler\Comment::class, 'schedule_comment_activity_on_insert' ) );

		$user_id  = self::factory()->user->create();
		$post_id  = self::factory()->post->create( array( 'post_author' => $user_id ) );
		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'      => 'Note',
				'content'   => '<p>This is a reply.</p>',
				'inReplyTo' => \get_permalink( $post_id ),
			),
		);

		$result = Create::handle_create( $activity, $user_id );

		$this->assertInstanceOf( 'WP_Comment', $result );
		$this->assertEquals( $post_id, (int) $result->comment_post_ID );
		$this->assertEquals( 0, (int) $result->comment_parent );
		$this->assertEquals( $user_id, (int) $result->user_id );
		$this->assertStringContainsString( 'This is a reply.', $result->comment_content );
		// C2S comments should NOT have protocol meta (only remote/inbox comments do).
		$this->assertEmpty( \get_comment_meta( $result->comment_ID, 'protocol', true ) );

		\add_action( 'wp_insert_comment', array( \Activitypub\Scheduler\Comment::class, 'schedule_comment_activity_on_insert' ), 10, 2 );
	}

	/**
	 * Test outgoing reply to local comment creates a nested comment.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_reply_to_local_comment() {
		\remove_action( 'wp_insert_comment', array( \Activitypub\Scheduler\Comment::class, 'schedule_comment_activity_on_insert' ) );

		$user_id    = self::factory()->user->create();
		$post_id    = self::factory()->post->create( array( 'post_author' => $user_id ) );
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'user_id'         => $user_id,
			)
		);

		// Build the comment URL using the ?c= format that url_to_commentid() resolves.
		$comment_url = \home_url( '?c=' . $comment_id );

		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'      => 'Note',
				'content'   => '<p>Nested reply.</p>',
				'inReplyTo' => $comment_url,
			),
		);

		$result = Create::handle_create( $activity, $user_id );

		$this->assertInstanceOf( 'WP_Comment', $result );
		$this->assertEquals( $post_id, (int) $result->comment_post_ID );
		$this->assertEquals( $comment_id, (int) $result->comment_parent );
		$this->assertEquals( $user_id, (int) $result->user_id );

		\add_action( 'wp_insert_comment', array( \Activitypub\Scheduler\Comment::class, 'schedule_comment_activity_on_insert' ), 10, 2 );
	}

	/**
	 * Test outgoing reply uses local user data for comment author.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_reply_uses_local_user_data() {
		\remove_action( 'wp_insert_comment', array( \Activitypub\Scheduler\Comment::class, 'schedule_comment_activity_on_insert' ) );

		$user_id = self::factory()->user->create(
			array(
				'display_name' => 'Test Author',
				'user_email'   => 'test@example.org',
				'user_url'     => 'https://example.org',
			)
		);
		$post_id = self::factory()->post->create( array( 'post_author' => $user_id ) );

		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'      => 'Note',
				'content'   => 'Author test reply.',
				'inReplyTo' => \get_permalink( $post_id ),
			),
		);

		$result = Create::handle_create( $activity, $user_id );

		$this->assertInstanceOf( 'WP_Comment', $result );
		$this->assertEquals( 'Test Author', $result->comment_author );
		$this->assertEquals( 'test@example.org', $result->comment_author_email );
		$this->assertEquals( 'https://example.org', $result->comment_author_url );

		\add_action( 'wp_insert_comment', array( \Activitypub\Scheduler\Comment::class, 'schedule_comment_activity_on_insert' ), 10, 2 );
	}

	/**
	 * Test outgoing quotes return null.
	 *
	 * @covers ::handle_create
	 */
	public function test_outgoing_quote_returns_null() {
		$activity = array(
			'type'   => 'Create',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'     => 'Note',
				'content'  => 'A quote post.',
				'quoteUrl' => 'https://example.com/note/456',
			),
		);

		$result = Create::handle_create( $activity, 1 );

		$this->assertNull( $result );
	}
}
