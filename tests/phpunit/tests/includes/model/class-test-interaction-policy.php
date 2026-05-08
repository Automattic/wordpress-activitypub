<?php
/**
 * Tests for actor-level interactionPolicy.canFeature output.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Model;

use Activitypub\Model\Blog;
use Activitypub\Model\User;

/**
 * Test class for actor-level interactionPolicy.canFeature.
 *
 * @group activitypub
 */
class Test_Interaction_Policy extends \WP_UnitTestCase {
	/**
	 * WordPress user ID for the test author.
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
	 * Reset the option after each test so other tests are unaffected.
	 */
	public function tear_down() {
		delete_option( 'activitypub_default_feature_policy' );
		parent::tear_down();
	}

	/**
	 * Default policy is ME (denied). The actor MUST advertise its own id as
	 * the only automatic-approval target — explicit denial per FEP-7aa9.
	 */
	public function test_user_actor_emits_canfeature_me_by_default() {
		$user   = new User( self::$user_id );
		$policy = $user->get_interaction_policy();

		$this->assertIsArray( $policy );
		$this->assertArrayHasKey( 'canFeature', $policy );
		$this->assertSame( array( $user->get_id() ), $policy['canFeature']['automaticApproval'] );
	}

	/**
	 * `anyone` policy emits the AS2 Public collection.
	 */
	public function test_user_actor_emits_canfeature_anyone_when_opted_in() {
		update_option( 'activitypub_default_feature_policy', ACTIVITYPUB_INTERACTION_POLICY_ANYONE );

		$user   = new User( self::$user_id );
		$policy = $user->get_interaction_policy();

		$this->assertSame(
			array( 'https://www.w3.org/ns/activitystreams#Public' ),
			$policy['canFeature']['automaticApproval']
		);
	}

	/**
	 * `followers` policy emits the actor's followers collection URL.
	 */
	public function test_user_actor_emits_canfeature_followers() {
		update_option( 'activitypub_default_feature_policy', ACTIVITYPUB_INTERACTION_POLICY_FOLLOWERS );

		$user   = new User( self::$user_id );
		$policy = $user->get_interaction_policy();

		$this->assertSame(
			array( $user->get_followers() ),
			$policy['canFeature']['automaticApproval']
		);
	}

	/**
	 * Blog actor inherits the same canFeature behavior.
	 */
	public function test_blog_actor_emits_canfeature() {
		$blog   = new Blog();
		$policy = $blog->get_interaction_policy();

		$this->assertIsArray( $policy );
		$this->assertArrayHasKey( 'canFeature', $policy );
	}
}
