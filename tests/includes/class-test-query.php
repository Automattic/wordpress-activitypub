<?php
/**
 * Test file for Query class.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests;

use Activitypub\Query;
use WP_UnitTestCase;

/**
 * Test class for Query.
 *
 * @coversDefaultClass \Activitypub\Query
 */
class Test_Query extends WP_UnitTestCase {
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
	 * Test has_activitypub_object method.
	 *
	 * @covers ::has_activitypub_object
	 */
	public function test_has_activitypub_object() {
		Query::get_instance()->__destruct();
		$this->go_to( site_url( '/404' ) );

		// Test with no queried object
		$this->assertFalse( Query::get_instance()->has_activitypub_object() );

		Query::get_instance()->__destruct();
		// Set up post query
		$this->go_to( get_permalink( self::$post_id ) );

		$this->assertTrue( Query::get_instance()->has_activitypub_object() );
	}

	/**
	 * Test get_activitypub_object method.
	 *
	 * @covers ::get_activitypub_object
	 */
	public function test_get_activitypub_object() {
		Query::get_instance()->__destruct();
		// Set up post query
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
		Query::get_instance()->__destruct();
		// Test with no queried object
		$query = Query::get_instance();
		$this->assertNull( $query->get_activitypub_object_id() );

		Query::get_instance()->__destruct();
		// Set up post query
		$this->go_to( get_permalink( self::$post_id ) );
		$query = Query::get_instance();

		$this->assertEquals( get_permalink( self::$post_id ), $query->get_activitypub_object_id() );
	}

	/**
	 * Test get_queried_object method.
	 *
	 * @covers ::get_queried_object
	 */
	public function test_get_queried_object() {
		Query::get_instance()->__destruct();

		// Test with post
		$this->go_to( get_permalink( self::$post_id ) );
		$query = Query::get_instance();
		$object = $query->get_queried_object();

		$this->assertInstanceOf( 'WP_Post', $object );
		$this->assertEquals( self::$post_id, $object->ID );

		Query::get_instance()->__destruct();
		// Test with author
		$this->go_to( get_author_posts_url( self::$user_id ) );
		$query = Query::get_instance();
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
		Query::get_instance()->__destruct();
		// Test without ActivityPub headers
		$this->assertFalse( Query::get_instance()->is_activitypub_request() );

		Query::get_instance()->__destruct();
		// Test with ActivityPub query var
		set_query_var( 'activitypub', '1' );
		$this->assertTrue( Query::get_instance()->is_activitypub_request() );
		set_query_var( 'activitypub', '' );

		Query::get_instance()->__destruct();
		// Test with Accept header
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
		$method = $reflection->getMethod( 'maybe_get_virtual_object' );
		$method->setAccessible( true );

		$query = Query::get_instance();

		// Test with invalid URL
		$_SERVER['REQUEST_URI'] = '/invalid/url';
		$this->assertNull( $method->invoke( $query ) );

		// Test with author URL
		$_SERVER['REQUEST_URI'] = '/?author=' . self::$user_id;
		$object = $method->invoke( $query );
		$this->assertNotNull( $object );
		$this->assertEquals( get_author_posts_url( self::$user_id ), $object->get_id() );

		unset( $_SERVER['REQUEST_URI'] );
	}
}
