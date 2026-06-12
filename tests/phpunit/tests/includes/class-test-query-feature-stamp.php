<?php
/**
 * Test for FeatureAuthorization stamp resolution.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activity\Extended_Object\Feature_Authorization;
use Activitypub\Collection\Actors;
use Activitypub\Handler\Feature_Request;
use Activitypub\Query;
use Activitypub\Router;

/**
 * Test class for FeatureAuthorization stamp resolution via Query.
 *
 * @coversDefaultClass \Activitypub\Query
 *
 * @group activitypub
 */
class Test_Query_Feature_Stamp extends \WP_UnitTestCase {
	/**
	 * The test user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Create a test user with the activitypub capability once for the class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Reset the Query singleton between tests.
	 */
	public function tear_down() {
		$instance_property = new \ReflectionProperty( Query::class, 'instance' );
		$instance_property->setAccessible( true );
		$instance_property->setValue( null, null );

		parent::tear_down();
	}

	/**
	 * A valid actor+stamp pair resolves to a FeatureAuthorization object.
	 *
	 * @covers ::get_activitypub_object
	 */
	public function test_actor_stamp_resolves_to_feature_authorization() {
		$instrument = 'https://other.example.com/users/curator/featured/77';
		$umeta_id   = add_user_meta( self::$user_id, '_activitypub_featured_by', $instrument );

		set_query_var( 'actor', self::$user_id );
		set_query_var( 'stamp', $umeta_id );
		$GLOBALS['wp_query']->queried_object    = get_user_by( 'id', self::$user_id );
		$GLOBALS['wp_query']->queried_object_id = self::$user_id;

		$object = Query::get_instance()->get_activitypub_object();

		$this->assertInstanceOf( Feature_Authorization::class, $object );
		$array = $object->to_array();
		$this->assertSame( 'FeatureAuthorization', $array['type'] );
		$this->assertSame( $instrument, $array['interactingObject'] );
	}

	/**
	 * A stamp belonging to a different actor is rejected.
	 *
	 * @covers ::get_activitypub_object
	 */
	public function test_cross_actor_stamp_id_is_rejected() {
		$other_user = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', $other_user )->add_cap( 'activitypub' );

		$umeta_id = add_user_meta( $other_user, '_activitypub_featured_by', 'https://x/y/1' );

		set_query_var( 'actor', self::$user_id );
		set_query_var( 'stamp', $umeta_id );
		$GLOBALS['wp_query']->queried_object    = get_user_by( 'id', self::$user_id );
		$GLOBALS['wp_query']->queried_object_id = self::$user_id;

		$object = Query::get_instance()->get_activitypub_object();
		$this->assertNotInstanceOf( Feature_Authorization::class, $object );
	}

	/**
	 * A non-existent stamp ID returns a non-FeatureAuthorization object.
	 *
	 * @covers ::get_activitypub_object
	 */
	public function test_missing_stamp_returns_null_object() {
		set_query_var( 'actor', self::$user_id );
		set_query_var( 'stamp', 999999999 );
		$GLOBALS['wp_query']->queried_object    = get_user_by( 'id', self::$user_id );
		$GLOBALS['wp_query']->queried_object_id = self::$user_id;

		$object = Query::get_instance()->get_activitypub_object();
		$this->assertNotInstanceOf( Feature_Authorization::class, $object );
	}

	/**
	 * End-to-end: a stamp URL goes through the same lifecycle as a real
	 * request (go_to → template_redirect → Query) and produces a
	 * FeatureAuthorization. This guards against the router reverting to
	 * the pre-guard state where it would 404 the request before content
	 * negotiation could resolve the stamp.
	 *
	 * @covers ::get_activitypub_object
	 * @covers \Activitypub\Router::template_redirect
	 */
	public function test_stamp_url_routes_and_resolves_end_to_end() {
		$instrument = 'https://other.example.com/users/curator/featured/integration';
		$umeta_id   = add_user_meta( self::$user_id, '_activitypub_featured_by', $instrument );

		$stamp_url = add_query_arg(
			array(
				'actor' => self::$user_id,
				'stamp' => $umeta_id,
			),
			home_url( '/' )
		);

		$this->go_to( $stamp_url );

		/*
		 * The pre-guard router would have called set_404() here for any
		 * non-numeric-username site. Just calling template_redirect without
		 * exception means the guard fired and let the request through to
		 * content negotiation.
		 */
		Router::template_redirect();

		$object = Query::get_instance()->get_activitypub_object();
		$this->assertInstanceOf( Feature_Authorization::class, $object );
		$this->assertSame( $instrument, $object->to_array()['interactingObject'] );
	}

	/**
	 * A blog-actor stamp (actor=0) resolves to a FeatureAuthorization object.
	 *
	 * Regression test: `0` is falsy as a query var, so the blog actor was
	 * previously never routed to the stamp resolver.
	 *
	 * @covers ::get_activitypub_object
	 */
	public function test_blog_actor_stamp_resolves_to_feature_authorization() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$instrument = 'https://other.example.com/users/curator/featured/88';
		$stamp_id   = Feature_Request::add_stamp( Actors::BLOG_USER_ID, $instrument );

		set_query_var( 'actor', '0' );
		set_query_var( 'stamp', $stamp_id );

		$object = Query::get_instance()->get_activitypub_object();

		$this->assertInstanceOf( Feature_Authorization::class, $object );
		$array = $object->to_array();
		$this->assertSame( 'FeatureAuthorization', $array['type'] );
		$this->assertSame( $instrument, $array['interactingObject'] );
		$this->assertSame( Actors::get_by_id( Actors::BLOG_USER_ID )->get_id(), $array['attributedTo'] );
	}

	/**
	 * Stamps do not leak across actor types: a user's stamp ID cannot be
	 * resolved as the blog actor, and vice versa.
	 *
	 * @covers \Activitypub\Handler\Feature_Request::get_stamp
	 */
	public function test_stamps_do_not_leak_across_actor_types() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$umeta_id      = add_user_meta( self::$user_id, '_activitypub_featured_by', 'https://x/y/user' );
		$blog_stamp_id = Feature_Request::add_stamp( Actors::BLOG_USER_ID, 'https://x/y/blog' );

		// A user stamp can never be served from the blog store, and vice versa.
		$this->assertNotSame( 'https://x/y/user', Feature_Request::get_stamp( Actors::BLOG_USER_ID, $umeta_id ), 'A user stamp must not resolve for the blog actor.' );
		$this->assertNotSame( 'https://x/y/blog', Feature_Request::get_stamp( self::$user_id, $blog_stamp_id ), 'A blog stamp must not resolve for a user actor.' );

		// Lookups stay scoped to the requested actor.
		$this->assertSame( 'https://x/y/blog', Feature_Request::get_stamp( Actors::BLOG_USER_ID, $blog_stamp_id ) );
		$this->assertSame( 'https://x/y/user', Feature_Request::get_stamp( self::$user_id, $umeta_id ) );
	}

	/**
	 * End-to-end: a blog-actor stamp URL (?actor=0&stamp=N) goes through the
	 * request lifecycle and produces a FeatureAuthorization.
	 *
	 * @covers ::get_activitypub_object
	 * @covers \Activitypub\Router::template_redirect
	 */
	public function test_blog_stamp_url_routes_and_resolves_end_to_end() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$instrument = 'https://other.example.com/users/curator/featured/blog-integration';
		$stamp_id   = Feature_Request::add_stamp( Actors::BLOG_USER_ID, $instrument );

		$stamp_url = add_query_arg(
			array(
				'actor' => 0,
				'stamp' => $stamp_id,
			),
			home_url( '/' )
		);

		$this->go_to( $stamp_url );
		Router::template_redirect();

		$object = Query::get_instance()->get_activitypub_object();
		$this->assertInstanceOf( Feature_Authorization::class, $object );
		$this->assertSame( $instrument, $object->to_array()['interactingObject'] );
	}
}
