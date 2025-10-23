<?php
/**
 * Unit tests for the Activitypub Accept handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler;

use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Handler\Accept;

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
	 * Test validate_object with various scenarios.
	 *
	 * @dataProvider validate_object_provider
	 * @covers ::validate_object
	 *
	 * @param array  $request_data     The request data to test.
	 * @param bool   $input_valid      The input valid state.
	 * @param bool   $expected_result  The expected validation result.
	 * @param string $description      Description of the test case.
	 */
	public function test_validate_object( $request_data, $input_valid, $expected_result, $description ) {
		$request = $this->createMock( 'WP_REST_Request' );
		$request->method( 'get_json_params' )->willReturn( $request_data );

		$result = Accept::validate_object( $input_valid, 'param', $request );

		$this->assertEquals( $expected_result, $result, $description );
	}

	/**
	 * Data provider for validate_object tests.
	 *
	 * @return array Test cases with request data, input valid state, expected result, and description.
	 */
	public function validate_object_provider() {
		return array(
			// Invalid cases.
			'missing_type'            => array(
				array(),
				true,
				false,
				'Should return false when type is missing',
			),
			'missing_required_fields' => array(
				array(
					'type'  => 'Accept',
					'actor' => 'foo',
				),
				true,
				false,
				'Should return false when required fields are missing',
			),
			// Valid cases - non-Accept type should pass through.
			'type_not_accept'         => array(
				array( 'type' => 'Follow' ),
				true,
				true,
				'Should return true when type is not Accept',
			),
			// Valid Accept activity.
			'valid_accept_activity'   => array(
				array(
					'type'   => 'Accept',
					'actor'  => 'foo',
					'object' => array(
						'id'     => 'bar',
						'actor'  => 'foo',
						'type'   => 'Follow',
						'object' => 'foo',
					),
				),
				true,
				true,
				'Should return true for valid Accept activity',
			),
			// Test with input_valid false.
			'input_valid_false'       => array(
				array( 'type' => 'Follow' ),
				false,
				false,
				'Should preserve input_valid when type is not Accept',
			),
		);
	}

	/**
	 * Functional test: handle_accept moves user from pending to following meta.
	 */
	public function test_handle_accept_moves_user_from_pending_to_following() {
		$user_id     = self::$user_id;
		$object_guid = 'https://example.com/actor/123';
		$outbox_guid = 'https://example.com/outbox/123';

		$outbox_post_id = $this->factory->post->create(
			array(
				'post_type'   => 'ap_outbox',
				'post_status' => 'publish',
				'guid'        => $outbox_guid,
			)
		);

		\add_post_meta( $outbox_post_id, '_activitypub_activity_type', 'Follow' );

		// Create remote actor post.
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_status' => 'publish',
				'guid'        => $object_guid,
			)
		);

		// Add user to pending.
		\add_post_meta( $post_id, Following::PENDING_META_KEY, (string) $user_id );

		// Confirm precondition.
		$pending = \get_post_meta( $post_id, Following::PENDING_META_KEY, false );
		$this->assertContains( (string) $user_id, $pending );

		// Prepare accept array as expected by handle_accept, using the real outbox guid.
		$accept = array(
			'type'   => 'Accept',
			'actor'  => 'https://example.net/actor/123',
			'object' => array(
				'id'     => $outbox_guid,
				'actor'  => 'https://example.com/actor/123',
				'type'   => 'Follow',
				'object' => $object_guid,
			),
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
