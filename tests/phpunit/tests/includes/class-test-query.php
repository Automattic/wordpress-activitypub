<?php
/**
 * Test file for Query class.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Outbox;
use Activitypub\Query;
use WP_UnitTestCase;

/**
 * Test class for Query.
 *
 * @coversDefaultClass \Activitypub\Query
 */
class Test_Query extends \WP_UnitTestCase {
	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

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
				'post_author'  => self::$user_id,
				'post_title'   => 'Test Post',
				'post_content' => 'Test Content',
				'post_status'  => 'publish',
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
	 * Test get_instance method.
	 *
	 * @covers ::get_instance
	 */
	public function test_get_instance() {
		$instance1 = Query::get_instance();
		$instance2 = Query::get_instance();

		$this->assertInstanceOf( Query::class, $instance1 );
		$this->assertSame( $instance1, $instance2, 'Multiple calls should return same instance' );
	}

	/**
	 * Test get_activitypub_object method.
	 *
	 * @covers ::get_activitypub_object
	 */
	public function test_get_activitypub_object() {
		// Test with post.
		Query::get_instance()->__destruct();
		$this->go_to( get_permalink( self::$post_id ) );
		$query = Query::get_instance();

		$object = $query->get_activitypub_object();
		$this->assertNotNull( $object );
		$this->assertEquals( get_permalink( self::$post_id ), $object->get_id() );
	}

	/**
	 * Test get_activitypub_object_id method.
	 *
	 * @covers ::get_activitypub_object_id
	 */
	public function test_get_activitypub_object_id() {
		// Test with no queried object.
		Query::get_instance()->__destruct();
		$query = Query::get_instance();
		$this->assertNull( $query->get_activitypub_object_id() );

		// Set up post query.
		Query::get_instance()->__destruct();
		$this->go_to( get_permalink( self::$post_id ) );
		$query = Query::get_instance();

		$this->assertEquals( get_permalink( self::$post_id ), $query->get_activitypub_object_id() );
	}

	/**
	 * Test get_activitypub_object_id method for authors.
	 *
	 * @covers ::get_activitypub_object_id
	 */
	public function test_get_activitypub_object_id_for_author() {
		$author_url = get_author_posts_url( self::$user_id );
		$this->go_to( $author_url );
		$query = Query::get_instance();

		$this->assertEquals( $author_url, $query->get_activitypub_object_id() );
	}

	/**
	 * Test get_queried_object method.
	 *
	 * @covers ::get_queried_object
	 */
	public function test_get_queried_object() {
		// Test with post.
		Query::get_instance()->__destruct();
		$this->go_to( get_permalink( self::$post_id ) );
		$query  = Query::get_instance();
		$object = $query->get_queried_object();

		$this->assertInstanceOf( 'WP_Post', $object );
		$this->assertEquals( self::$post_id, $object->ID );

		// Test with author.
		Query::get_instance()->__destruct();
		$this->go_to( get_author_posts_url( self::$user_id ) );
		$query  = Query::get_instance();
		$object = $query->get_queried_object();

		$this->assertInstanceOf( 'WP_User', $object );
		$this->assertEquals( self::$user_id, $object->ID );
	}

	/**
	 * Test is_activitypub_request method.
	 *
	 * @covers ::is_activitypub_request
	 */
	public function test_is_activitypub_request() {
		// Test without ActivityPub headers.
		Query::get_instance()->__destruct();
		$this->assertFalse( Query::get_instance()->is_activitypub_request() );

		// Test with ActivityPub query var.
		Query::get_instance()->__destruct();
		$_GET['activitypub'] = '';
		$this->assertTrue( Query::get_instance()->is_activitypub_request() );
		unset( $_GET['activitypub'] );

		Query::get_instance()->__destruct();
		$this->go_to( get_permalink( self::$post_id ) );
		set_query_var( 'activitypub', '1' );
		$this->assertTrue( Query::get_instance()->is_activitypub_request() );
		set_query_var( 'activitypub', '' );

		// Test with Accept header.
		Query::get_instance()->__destruct();
		$_SERVER['HTTP_ACCEPT'] = 'application/activity+json';
		$this->go_to( get_permalink( self::$post_id ) );
		$this->assertTrue( Query::get_instance()->is_activitypub_request() );

		Query::get_instance()->__destruct();
		$_SERVER['HTTP_ACCEPT'] = 'application/ld+json';
		$this->go_to( get_permalink( self::$post_id ) );
		$this->assertTrue( Query::get_instance()->is_activitypub_request() );

		Query::get_instance()->__destruct();
		$_SERVER['HTTP_ACCEPT'] = 'application/json';
		$this->go_to( get_permalink( self::$post_id ) );
		$this->assertTrue( Query::get_instance()->is_activitypub_request() );

		Query::get_instance()->__destruct();
		$_SERVER['HTTP_ACCEPT'] = 'text/html';
		$this->go_to( get_permalink( self::$post_id ) );
		$this->assertFalse( Query::get_instance()->is_activitypub_request() );

		unset( $_SERVER['HTTP_ACCEPT'] );
	}

	/**
	 * Test maybe_get_virtual_object method.
	 *
	 * @covers ::maybe_get_virtual_object
	 */
	public function test_maybe_get_virtual_object() {
		$reflection = new \ReflectionClass( Query::class );
		$method     = $reflection->getMethod( 'maybe_get_virtual_object' );
		$method->setAccessible( true );

		$query = Query::get_instance();

		// Test with invalid URL.
		$_SERVER['REQUEST_URI'] = '/invalid/url';
		$this->assertNull( $method->invoke( $query ) );

		// Test with author URL.
		$_SERVER['REQUEST_URI'] = '/?author=' . self::$user_id;
		$object                 = $method->invoke( $query );
		$this->assertNotNull( $object );
		$this->assertEquals( get_author_posts_url( self::$user_id ), $object->get_id() );

		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test comment activitypub object.
	 *
	 * @covers ::get_activitypub_object
	 */
	public function test_comment_activitypub_object() {
		Query::get_instance()->__destruct();
		// New comment.
		$comment_id = wp_insert_comment(
			array(
				'user_id'          => self::$user_id,
				'comment_post_ID'  => self::$post_id,
				'comment_author'   => 'Test Author',
				'comment_content'  => 'Test Content',
				'comment_approved' => 1,
				'comment_type'     => 'comment',
				'comment_meta'     => array(
					'activitypub_status' => 'federated',
				),
			)
		);

		$this->go_to( home_url( '/?c=' . $comment_id ) );
		$query = Query::get_instance();

		$object = $query->get_activitypub_object();
		$this->assertNotNull( $object );
		$this->assertEquals( '<p>Test Content</p>', $object->get_content() );

		// Test unsupported comment.
		Query::get_instance()->__destruct();

		// New comment.
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'  => self::$post_id,
				'comment_author'   => 'Test Author',
				'comment_content'  => 'Test Content 2',
				'comment_approved' => 1,
				'comment_type'     => 'comment',
			)
		);

		$this->go_to( home_url( '/?c=' . $comment_id ) );
		$this->assertNull( Query::get_instance()->get_activitypub_object() );
	}

	/**
	 * Test user at URL activity object.
	 *
	 * @covers ::get_activitypub_object
	 */
	public function test_user_at_url_activity_object() {
		$user_id = self::factory()->user->create(
			array(
				'user_login' => 'testuser',
				'role'       => 'author',
			)
		);

		Query::get_instance()->__destruct();
		$user   = get_user_by( 'id', $user_id );
		$at_url = home_url( '/@' . $user->user_login . '/?activitypub' );

		$this->go_to( $at_url );
		$this->assertNotNull( Query::get_instance()->get_activitypub_object() );

		\wp_delete_user( $user_id );
	}

	/**
	 * Test user activitypub object.
	 *
	 * @covers ::get_activitypub_object
	 */
	public function test_user_activitypub_object() {
		Query::get_instance()->__destruct();
		$this->go_to( get_author_posts_url( self::$user_id ) );
		$this->assertNotNull( Query::get_instance()->get_activitypub_object() );

		Query::get_instance()->__destruct();
		$user = get_user_by( 'id', self::$user_id );
		$user->remove_cap( 'activitypub' );
		$this->go_to( get_author_posts_url( self::$user_id ) );
		$this->assertNull( Query::get_instance()->get_activitypub_object() );

		$user->add_cap( 'activitypub' );
	}

	/**
	 * Test post activitypub object.
	 *
	 * @covers ::get_activitypub_object
	 */
	public function test_post_activity_object() {
		Query::get_instance()->__destruct();
		$this->go_to( get_permalink( self::$post_id ) );
		$this->assertNotNull( Query::get_instance()->get_activitypub_object() );

		Query::get_instance()->__destruct();
		add_post_meta( self::$post_id, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );
		$this->go_to( get_permalink( self::$post_id ) );
		$this->assertNull( Query::get_instance()->get_activitypub_object() );

		Query::get_instance()->__destruct();
		delete_post_meta( self::$post_id, 'activitypub_content_visibility' );
		$this->go_to( get_permalink( self::$post_id ) );
		$this->assertNotNull( Query::get_instance()->get_activitypub_object() );
	}

	/**
	 * Test outbox item visibility.
	 *
	 * @covers ::get_activitypub_object
	 */
	public function test_outbox_item_visibility() {
		$post_id     = self::factory()->post->create( array( 'post_author' => self::$user_id ) );
		$outbox_item = \current(
			\get_posts(
				array(
					'post_type'      => Outbox::POST_TYPE,
					'posts_per_page' => 1,
					'post_status'    => 'pending',
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			)
		);

		Query::get_instance()->__destruct();
		$this->go_to( get_permalink( $outbox_item->ID ) );
		$this->assertNotNull( Query::get_instance()->get_activitypub_object() );

		// Private Activity.
		\update_post_meta( $outbox_item->ID, 'activitypub_content_visibility', ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL );

		Query::get_instance()->__destruct();
		$this->go_to( get_permalink( $outbox_item->ID ) );
		$this->assertNull( Query::get_instance()->get_activitypub_object() );

		// Private Activity Type.
		\update_post_meta( $outbox_item->ID, '_activitypub_activity_type', 'Delete' );

		Query::get_instance()->__destruct();
		$this->go_to( get_permalink( $outbox_item->ID ) );
		$this->assertNull( Query::get_instance()->get_activitypub_object() );

		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test should_negotiate_content method.
	 *
	 * @covers ::should_negotiate_content
	 */
	public function test_should_negotiate_content() {
		\add_option( 'permalink_structure', '/%postname%/' );

		$this->assertTrue( Query::get_instance()->should_negotiate_content() );

		\update_option( 'activitypub_content_negotiation', '0' );
		$_SERVER['REQUEST_URI'] = get_permalink( self::$post_id );
		$this->assertFalse( Query::get_instance()->should_negotiate_content() );

		\update_option( 'activitypub_content_negotiation', '1' );

		$_SERVER['REQUEST_URI'] = home_url( '/?p=' . self::$post_id );
		$this->assertTrue( Query::get_instance()->should_negotiate_content() );

		unset( $_SERVER['REQUEST_URI'] );

		\update_option( 'activitypub_content_negotiation', '0' );

		$_SERVER['REQUEST_URI'] = home_url( '/?author=' . self::$user_id );
		$this->assertTrue( Query::get_instance()->should_negotiate_content() );

		unset( $_SERVER['REQUEST_URI'] );

		\delete_option( 'activitypub_content_negotiation' );
		\delete_option( 'permalink_structure' );
	}

	/**
	 * Test maybe_get_stamp method for QuoteAuthorization objects.
	 *
	 * @covers ::maybe_get_stamp
	 * @covers ::get_activitypub_object
	 * @covers ::get_activitypub_object_id
	 */
	public function test_maybe_get_stamp() {
		// Create a post meta entry to simulate a quote authorization stamp.
		$meta_id = \add_post_meta( self::$post_id, '_activitypub_quoted_by', 'https://remote.example.com/posts/456' );

		// Test with valid stamp query parameter.
		Query::get_instance()->__destruct();
		$this->go_to( home_url( '/?p=' . self::$post_id . '&stamp=' . $meta_id ) );
		\set_query_var( 'stamp', $meta_id );

		$query  = Query::get_instance();
		$object = $query->get_activitypub_object();

		// Test that we get a QuoteAuthorization object.
		$this->assertNotNull( $object, 'Should create QuoteAuthorization object for valid stamp' );
		$this->assertEquals( 'QuoteAuthorization', $object->get_type(), 'Should be QuoteAuthorization type' );

		// Test the object properties.
		$expected_id = \add_query_arg(
			array(
				'p'     => self::$post_id,
				'stamp' => $meta_id,
			),
			\home_url( '/' )
		);
		$this->assertEquals( $expected_id, $object->get_id(), 'Should have correct stamp URI as ID' );

		// Test object ID separately.
		$this->assertEquals( $expected_id, $query->get_activitypub_object_id(), 'Should return correct object ID' );

		// Test with invalid stamp.
		Query::get_instance()->__destruct();
		$this->go_to( home_url( '/?p=' . self::$post_id . '&stamp=999999' ) );
		\set_query_var( 'stamp', '999999' );

		$query  = Query::get_instance();
		$object = $query->get_activitypub_object();

		// Should fall back to regular post object.
		$this->assertNotNull( $object, 'Should fall back to post object for invalid stamp' );
		$this->assertNotEquals( 'QuoteAuthorization', $object->get_type(), 'Should not be QuoteAuthorization for invalid stamp' );

		// Test without stamp parameter.
		Query::get_instance()->__destruct();
		$this->go_to( home_url( '/?p=' . self::$post_id ) );

		$query  = Query::get_instance();
		$object = $query->get_activitypub_object();

		// Should get regular post object.
		$this->assertNotNull( $object, 'Should get post object without stamp parameter' );
		$this->assertNotEquals( 'QuoteAuthorization', $object->get_type(), 'Should not be QuoteAuthorization without stamp parameter' );

		// Clean up.
		\delete_post_meta( self::$post_id, '_activitypub_quoted_by' );
	}

	/**
	 * Test maybe_get_stamp with non-existent meta ID.
	 *
	 * @covers ::maybe_get_stamp
	 */
	public function test_maybe_get_stamp_invalid_meta() {
		// Test with non-existent meta ID.
		Query::get_instance()->__destruct();
		$this->go_to( home_url( '/?p=' . self::$post_id . '&stamp=999999' ) );
		\set_query_var( 'stamp', '999999' );

		$reflection = new \ReflectionClass( Query::class );
		$method     = $reflection->getMethod( 'maybe_get_stamp' );
		$method->setAccessible( true );

		$query  = Query::get_instance();
		$result = $method->invoke( $query );

		$this->assertFalse( $result, 'Should return false for non-existent meta ID' );
	}

	/**
	 * Test maybe_get_stamp with invalid post author.
	 *
	 * @covers ::maybe_get_stamp
	 */
	public function test_maybe_get_stamp_invalid_author() {
		// Create a post with invalid author.
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => 999999, // Non-existent user ID.
				'post_title'   => 'Test Post Invalid Author',
				'post_content' => 'Test Content',
				'post_status'  => 'publish',
			)
		);

		$meta_id = \add_post_meta( $post_id, '_activitypub_quoted_by', 'https://remote.example.com/posts/456' );

		Query::get_instance()->__destruct();
		$this->go_to( home_url( '/?p=' . $post_id . '&stamp=' . $meta_id ) );
		\set_query_var( 'stamp', $meta_id );

		$reflection = new \ReflectionClass( Query::class );
		$method     = $reflection->getMethod( 'maybe_get_stamp' );
		$method->setAccessible( true );

		$query  = Query::get_instance();
		$result = $method->invoke( $query );

		$this->assertFalse( $result, 'Should return false for invalid post author' );

		// Clean up.
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test get_activitypub_object method for home page in Actor mode.
	 *
	 * @covers ::get_activitypub_object
	 */
	public function test_home_page_actor_mode() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );

		$actor_queries = array();

		// Track database queries using the 'query' filter.
		$query_filter = function ( $query ) use ( &$actor_queries ) {
			if ( strpos( $query, 'ap_actor' ) !== false ) {
				$actor_queries[] = $query;
			}
			return $query;
		};

		add_filter( 'query', $query_filter );

		Query::get_instance()->__destruct();
		$this->go_to( home_url( '/' ) );
		$object = Query::get_instance()->get_activitypub_object();

		remove_filter( 'query', $query_filter );

		$message = 'Should not query Remote_Actors table for home page.';
		if ( ! empty( $actor_queries ) ) {
			$message .= ' Found queries: ' . wp_json_encode( $actor_queries );
		}

		$this->assertNull( $object, 'Home page should return null, because the Blog user is disabled.' );
		$this->assertEmpty( $actor_queries, $message );

		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test get_activitypub_object method for home page in Actor and Blog mode.
	 *
	 * @covers ::get_activitypub_object
	 */
	public function test_home_page_actor_and_blog_mode() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		$actor_queries = array();

		// Track database queries using the 'query' filter.
		$query_filter = function ( $query ) use ( &$actor_queries ) {
			if ( strpos( $query, 'ap_actor' ) !== false ) {
				$actor_queries[] = $query;
			}
			return $query;
		};

		\add_filter( 'query', $query_filter );

		Query::get_instance()->__destruct();
		$this->go_to( home_url( '/' ) );
		$object = Query::get_instance()->get_activitypub_object();

		\remove_filter( 'query', $query_filter );

		$message = 'Should not query Remote_Actors table for home page.';
		if ( ! empty( $actor_queries ) ) {
			$message .= ' Found queries: ' . wp_json_encode( $actor_queries );
		}

		$this->assertNotNull( $object, 'Home page should return an object' );
		$this->assertEmpty( $actor_queries, $message );

		\delete_option( 'activitypub_actor_mode' );
	}
}
