<?php
/**
 * Unit tests for the Activitypub Accept handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Handler\Accept;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Following;

/**
 * Class Test_Accept
 *
 * @coversDefaultClass \Activitypub\Handler\Accept
 */
class Test_Accept extends \WP_UnitTestCase {

	/**
	 * Test user ID.
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
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		\wp_delete_user( self::$user_id );
	}

	/**
	 * Test validate_object returns false if type is missing.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_missing_type() {
		$request = $this->createMock( 'WP_REST_Request' );
		$request->method( 'get_json_params' )->willReturn( array() );
		$this->assertFalse( Accept::validate_object( true, 'param', $request ) );
	}

	/**
	 * Test validate_object returns true if type is not Accept.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_type_not_accept() {
		$request = $this->createMock( 'WP_REST_Request' );
		$request->method( 'get_json_params' )->willReturn( array( 'type' => 'Follow' ) );
		$this->assertTrue( Accept::validate_object( true, 'param', $request ) );
	}

	/**
	 * Test validate_object returns false if required fields are missing.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_missing_required_fields() {
		$request = $this->createMock( 'WP_REST_Request' );
		$request->method( 'get_json_params' )->willReturn(
			array(
				'type'  => 'Accept',
				'actor' => 'foo',
			)
		);
		$this->assertFalse( Accept::validate_object( true, 'param', $request ) );
	}

	/**
	 * Test validate_object returns true if all checks pass.
	 *
	 * @covers ::validate_object
	 */
	public function test_validate_object_success() {
		$request = $this->createMock( 'WP_REST_Request' );
		$request->method( 'get_json_params' )->willReturn(
			array(
				'type'   => 'Accept',
				'actor'  => 'foo',
				'object' => 'bar',
			)
		);
		$this->assertTrue( Accept::validate_object( true, 'param', $request ) );
	}

	/**
	 * Functional test: handle_accept moves user from pending to following meta.
	 */
	public function test_handle_accept_moves_user_from_pending_to_following() {
		$user_id    = self::$user_id;
		$object_url = 'https://example.com/actor/123';

		// Create remote actor post.
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Actors::POST_TYPE,
				'post_status' => 'publish',
				'guid'        => $object_url,
			)
		);

		// Add user to pending.
		\add_post_meta( $post_id, Following::PENDING_META_KEY, (string) $user_id );

		// Confirm precondition.
		$pending = \get_post_meta( $post_id, Following::PENDING_META_KEY, false );
		$this->assertContains( (string) $user_id, $pending );

		// Prepare accept array as expected by handle_accept.
		$accept = array(
			'type'   => 'Accept',
			'actor'  => 'https://example.com/actor/123',
			'object' => $object_url,
		);

		// Call the handler.
		Accept::handle_accept( $accept, $user_id );

		\clean_post_cache( $post_id );

		// Assert: user_id is now in _activitypub_followed_by.
		$following = \get_post_meta( $post_id, Following::FOLLOWING_META_KEY, false );
		$this->assertContains( (string) $user_id, $following );

		// Assert: user_id is no longer in _activitypub_followed_by_pending.
		$pending = \get_post_meta( $post_id, Following::PENDING_META_KEY, false );
		$this->assertNotContains( (string) $user_id, $pending );
	}
}
