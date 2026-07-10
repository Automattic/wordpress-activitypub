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
	 * Blog actor inherits the same canFeature behavior, including default-deny.
	 */
	public function test_blog_actor_emits_canfeature() {
		$blog   = new Blog();
		$policy = $blog->get_interaction_policy();

		$this->assertIsArray( $policy );
		$this->assertArrayHasKey( 'canFeature', $policy );
		$this->assertSame(
			array( $blog->get_id() ),
			$policy['canFeature']['automaticApproval'],
			'Blog actor must default to explicit denial (its own id as the only approved target).'
		);
	}

	/**
	 * The generic Activity\Actor carries no application logic: it must not
	 * compute a canFeature policy from the local site option, since it also
	 * represents remote actors.
	 */
	public function test_generic_actor_does_not_emit_local_canfeature() {
		\update_option( 'activitypub_default_feature_policy', ACTIVITYPUB_INTERACTION_POLICY_ANYONE );

		$actor = \Activitypub\Activity\Actor::init_from_array(
			array(
				'id'   => 'https://remote.example/users/jane',
				'type' => 'Person',
			)
		);

		$this->assertNull( $actor->get_interaction_policy(), 'A generic actor must not inherit the local canFeature policy.' );
	}

	/**
	 * The Application actor advertises no canFeature policy (absence of
	 * policy means no consent per FEP-7aa9).
	 */
	public function test_application_actor_emits_no_interaction_policy() {
		// Model\Application is deprecated in favor of \Activitypub\Application; the constructor emits the notice.
		$this->setExpectedDeprecated( 'Activitypub\Model\Application' );

		\update_option( 'activitypub_default_feature_policy', ACTIVITYPUB_INTERACTION_POLICY_ANYONE );

		$application = new \Activitypub\Model\Application();

		$this->assertNull( $application->get_interaction_policy() );
		$this->assertArrayNotHasKey( 'interactionPolicy', $application->to_array( false ) );
	}
}
