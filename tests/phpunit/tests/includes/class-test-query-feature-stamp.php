<?php
/**
 * Test for FeatureAuthorization stamp resolution.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activity\Extended_Object\Feature_Authorization;
use Activitypub\Query;

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
}
