<?php
/**
 * Test Reader Permission Trait.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\OAuth\Scope;
use Activitypub\Rest\Reader_Permission;
use Activitypub\Tests\OAuth_Token_Stub;

/**
 * Test Reader Permission Trait.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Reader_Permission
 */
class Test_Trait_Reader_Permission extends \WP_UnitTestCase {
	use OAuth_Token_Stub;

	/**
	 * Test class instance that uses the trait.
	 *
	 * @var object
	 */
	protected $instance;

	/**
	 * A user who can use ActivityPub.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		$this->user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', $this->user_id )->add_cap( 'activitypub' );

		$this->instance = new class() extends \WP_REST_Controller {
			use Reader_Permission;

			/**
			 * Expose check_reader_capability for testing.
			 *
			 * @return true|\WP_Error
			 */
			public function test_check_reader_capability() {
				return $this->check_reader_capability();
			}
		};
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		\wp_set_current_user( 0 );
		$this->set_oauth_current_token( null );

		parent::tear_down();
	}

	/**
	 * A WordPress session is not limited by scope.
	 *
	 * @covers ::check_reader_capability
	 */
	public function test_session_without_token_may_read() {
		\wp_set_current_user( $this->user_id );

		$this->assertTrue( $this->instance->test_check_reader_capability() );
	}

	/**
	 * A token logs its user in, and may read only with the read scope.
	 *
	 * @covers ::check_reader_capability
	 */
	public function test_token_needs_the_read_scope() {
		\wp_set_current_user( $this->user_id );

		$this->set_oauth_current_token( $this->mock_oauth_token( array( Scope::READ ), $this->user_id ) );
		$this->assertTrue( $this->instance->test_check_reader_capability(), 'A read token may read.' );

		foreach ( array( Scope::WRITE, Scope::PUSH ) as $scope ) {
			$this->set_oauth_current_token( $this->mock_oauth_token( array( $scope ), $this->user_id ) );
			$this->assertWPError( $this->instance->test_check_reader_capability(), \sprintf( 'A %s token may not read.', $scope ) );
		}
	}
}
