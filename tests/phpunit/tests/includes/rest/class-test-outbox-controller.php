<?php
/**
 * Outbox REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Outbox;
use Activitypub\Rest\Outbox_Controller;

/**
 * Tests for Outbox REST API endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Outbox_Controller
 */
class Test_Outbox_Controller extends \Activitypub\Tests\Test_REST_Controller_Testcase {

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	public static $user_id;

	/**
	 * Test post IDs.
	 *
	 * @var int[]
	 */
	public static $post_ids;

	/**
	 * Set up class test fixtures.
	 */
	public static function set_up_before_class() {
		self::$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'ID', self::$user_id )->add_cap( 'activitypub' );
		\wp_set_current_user( self::$user_id );

		self::$post_ids = self::factory()->post->create_many( 10, array( 'post_author' => self::$user_id ) );
	}

	/**
	 * Clean up test fixtures.
	 */
	public static function tear_down_after_class() {
		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );

		parent::tear_down_after_class();
	}

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Test route registration.
	 *
	 * @covers ::register_routes
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . ACTIVITYPUB_REST_NAMESPACE . '/(?:users|actors)/(?P<user_id>[-]?\d+)/outbox', $routes );
	}

	/**
	 * Test user ID validation.
	 *
	 * @covers ::validate_user_id
	 */
	public function test_validate_user_id() {
		$actor_mode = \get_option( 'activitypub_actor_mode' );
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$controller = new Outbox_Controller();
		$this->assertTrue( $controller->validate_user_id( 0 ) );
		$this->assertTrue( $controller->validate_user_id( '1' ) );
		$this->assertWPError( $controller->validate_user_id( 'user-1' ) );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );
		$this->assertWPError( $controller->validate_user_id( 0 ) );
		$this->assertTrue( $controller->validate_user_id( 1 ) );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		$this->assertTrue( $controller->validate_user_id( '0' ) );
		$this->assertWPError( $controller->validate_user_id( 1 ) );

		\update_option( 'activitypub_actor_mode', $actor_mode );
	}

	/**
	 * Test getting items.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items() {
		$request  = new \WP_REST_Request( 'GET', sprintf( '/%s/actors/%s/outbox', ACTIVITYPUB_REST_NAMESPACE, self::$user_id ) );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test schema.
	 *
	 * @covers ::get_collection_schema
	 */
	public function test_get_collection_schema() {
		$request  = new \WP_REST_Request( 'GET', sprintf( '/%s/actors/%s/outbox', ACTIVITYPUB_REST_NAMESPACE, self::$user_id ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$schema   = ( new Outbox_Controller() )->get_item_schema();

		$valid = \rest_validate_value_from_schema( $data, $schema );
		$this->assertNotWPError( $valid, 'Response failed schema validation: ' . ( \is_wp_error( $valid ) ? $valid->get_error_message() : '' ) );
	}

	/**
	 * Test getting items with pagination.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_pagination() {
		$request = new \WP_REST_Request( 'GET', sprintf( '/%s/actors/%s/outbox', ACTIVITYPUB_REST_NAMESPACE, self::$user_id ) );
		$request->set_param( 'page', 2 );
		$request->set_param( 'per_page', 3 );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'prev', $data );
		$this->assertArrayHasKey( 'next', $data );
		$this->assertStringContainsString( 'page=1', $data['prev'] );
		$this->assertStringContainsString( 'page=3', $data['next'] );

		// Empty collection - with the new behavior, even empty collections have pagination links.
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/1/outbox' );
		$request->set_param( 'per_page', 3 );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'first', $data );
		$this->assertArrayHasKey( 'last', $data );
	}

	/**
	 * Test getting items response structure.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_response_structure() {
		$request  = new \WP_REST_Request( 'GET', sprintf( '/%s/actors/%s/outbox', ACTIVITYPUB_REST_NAMESPACE, self::$user_id ) );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( '@context', $data );
		$this->assertArrayHasKey( 'id', $data );
		$this->assertArrayHasKey( 'type', $data );
		$this->assertArrayHasKey( 'totalItems', $data );
		// Collection (without page param) should not have orderedItems, only links to pages.
		$this->assertArrayNotHasKey( 'orderedItems', $data );
		$this->assertArrayHasKey( 'first', $data );
		$this->assertArrayHasKey( 'last', $data );
		$this->assertEquals( 'OrderedCollection', $data['type'] );

		$headers = $response->get_headers();
		$this->assertEquals( 'application/activity+json; charset=' . \get_option( 'blog_charset' ), $headers['Content-Type'] );
	}

	/**
	 * Test getting items for specific user.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_specific_user() {
		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/outbox' );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertStringContainsString( (string) self::$user_id, $data['actor'] );
	}

	/**
	 * Test outbox filters.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_filters() {
		$filter_called = false;
		$pre_called    = false;
		$post_called   = false;

		\add_filter(
			'activitypub_rest_outbox_array',
			function ( $response ) use ( &$filter_called ) {
				$filter_called = true;
				return $response;
			}
		);

		\add_action(
			'activitypub_rest_outbox_pre',
			function () use ( &$pre_called ) {
				$pre_called = true;
			}
		);

		\add_action(
			'activitypub_rest_outbox_post',
			function () use ( &$post_called ) {
				$post_called = true;
			}
		);

		$request = new \WP_REST_Request( 'GET', sprintf( '/%s/actors/%s/outbox', ACTIVITYPUB_REST_NAMESPACE, self::$user_id ) );
		\rest_get_server()->dispatch( $request );

		$this->assertTrue( $filter_called, 'activitypub_rest_outbox_array filter was not called.' );
		$this->assertTrue( $pre_called, 'activitypub_rest_outbox_pre action was not called.' );
		$this->assertTrue( $post_called, 'activitypub_outbox_post action was not called.' );

		\remove_all_filters( 'activitypub_rest_outbox_array' );
		\remove_all_actions( 'activitypub_rest_outbox_pre' );
		\remove_all_actions( 'activitypub_rest_outbox_post' );
		\remove_all_actions( 'activitypub_outbox_post' );
	}

	/**
	 * Test getting items with minimum per_page.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_minimum_per_page() {
		$request = new \WP_REST_Request( 'GET', sprintf( '/%s/actors/%s/outbox', ACTIVITYPUB_REST_NAMESPACE, self::$user_id ) );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 1 );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 1, $data['orderedItems'] );
	}

	/**
	 * Test getting items with maximum per_page.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_maximum_per_page() {
		$request = new \WP_REST_Request( 'GET', sprintf( '/%s/actors/%s/outbox', ACTIVITYPUB_REST_NAMESPACE, self::$user_id ) );
		$request->set_param( 'per_page', 100 );
		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Data provider for test_get_items_activity_type.
	 *
	 * @return array[] Test parameters.
	 */
	public function data_activity_types() {
		return array(
			'create_activity'   => array(
				'type'    => 'Create',
				'object'  => array(
					'id'      => 'https://example.org/note/1',
					'type'    => 'Note',
					'content' => 'Test content',
				),
				'allowed' => true,
			),
			'announce_activity' => array(
				'type'    => 'Announce',
				'object'  => 'https://example.org/note/2',
				'allowed' => true,
			),
			'like_activity'     => array(
				'type'    => 'Like',
				'object'  => 'https://example.org/note/3',
				'allowed' => true,
			),
			'update_activity'   => array(
				'type'    => 'Update',
				'object'  => array(
					'id'      => 'https://example.org/note/4',
					'type'    => 'Note',
					'content' => 'Updated content',
				),
				'allowed' => true,
			),
			'delete_activity'   => array(
				'type'    => 'Delete',
				'object'  => 'https://example.org/note/5',
				'allowed' => false,
			),
			'follow_activity'   => array(
				'type'    => 'Follow',
				'object'  => 'https://example.org/user/6',
				'allowed' => false,
			),
		);
	}

	/**
	 * Test getting items with different activity types.
	 *
	 * @covers ::get_items
	 * @dataProvider data_activity_types
	 *
	 * @param string       $type     Activity type.
	 * @param string|array $activity Activity object.
	 * @param bool         $allowed  Whether the activity type is allowed for public users.
	 */
	public function test_get_items_activity_type( $type, $activity, $allowed ) {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => $user_id,
				'post_type'    => Outbox::POST_TYPE,
				'post_status'  => 'pending',
				'post_title'   => "https://example.org/activity/{$type}",
				'post_content' => \wp_json_encode(
					array(
						'@context' => array( 'https://www.w3.org/ns/activitystreams' ),
						'id'       => "https://example.org/activity/{$type}",
						'type'     => $type,
						'actor'    => 'https://example.org/user/' . $user_id,
						'object'   => $activity,
					)
				),
				'meta_input'   => array(
					'_activitypub_activity_type'     => $type,
					'_activitypub_activity_actor'    => 'user',
					'activitypub_content_visibility' => \ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
				),
			)
		);

		// Test as logged-out user.
		\wp_set_current_user( 0 );
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/outbox' );

		if ( $allowed ) {
			// For allowed activities, request a page to verify they appear in orderedItems.
			$request->set_param( 'page', 1 ); // Need to request a page to get orderedItems.
			$request->set_param( 'per_page', 10 ); // Need per_page for pagination calculation.
			$response = \rest_get_server()->dispatch( $request );
			$data     = $response->get_data();

			$this->assertEquals( 200, $response->get_status() );
			$activity_types = \wp_list_pluck( $data['orderedItems'], 'type' );
			$this->assertContains( $type, $activity_types, sprintf( 'Activity type "%s" should be visible to logged-out users.', $type ) );
		} else {
			// For disallowed activities, check the collection without pagination to verify totalItems is 0.
			$response = \rest_get_server()->dispatch( $request );
			$data     = $response->get_data();

			$this->assertEquals( 200, $response->get_status() );
			$this->assertEquals( 0, $data['totalItems'], sprintf( 'Activity type "%s" should not be visible to logged-out users (totalItems should be 0).', $type ) );
		}

		// Test as logged-in user with activitypub capability.
		\wp_set_current_user( $user_id );
		$this->assertTrue( \current_user_can( 'activitypub' ) );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/outbox' );
		$request->set_param( 'page', 1 ); // Need to request a page to get orderedItems.
		$request->set_param( 'per_page', 10 ); // Need per_page for pagination calculation.
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$activity_types = \wp_list_pluck( $data['orderedItems'], 'type' );

		$this->assertContains( $type, $activity_types, sprintf( 'Activity type "%s" should be visible to users with activitypub capability.', $type ) );

		\wp_delete_post( $post_id, true );
		\wp_delete_user( $user_id );
	}

	/**
	 * Data provider for test_get_items_content_visibility.
	 *
	 * @return array[] Test parameters.
	 */
	public function data_content_visibility() {
		return array(
			'no_visibility' => array(
				'visibility'      => null,
				'public_visible'  => true,
				'private_visible' => true,
			),
			'public'        => array(
				'visibility'      => \ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
				'public_visible'  => true,
				'private_visible' => true,
			),
			'quiet_public'  => array(
				'visibility'      => \ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC,
				'public_visible'  => false,
				'private_visible' => true,
			),
			'private'       => array(
				'visibility'      => \ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE,
				'public_visible'  => false,
				'private_visible' => true,
			),
			'local'         => array(
				'visibility'      => \ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL,
				'public_visible'  => false,
				'private_visible' => true,
			),
		);
	}

	/**
	 * Test content visibility for logged-in and logged-out users.
	 *
	 * @covers ::get_items
	 * @dataProvider data_content_visibility
	 *
	 * @param string|null $visibility      Content visibility setting.
	 * @param bool        $public_visible  Whether content should be visible to public users.
	 * @param bool        $private_visible Whether content should be visible to users with activitypub capability.
	 */
	public function test_get_items_content_visibility( $visibility, $public_visible, $private_visible ) {
		$user_id    = self::factory()->user->create( array( 'role' => 'author' ) );
		$meta_input = array(
			'_activitypub_activity_type'  => 'Create',
			'_activitypub_activity_actor' => 'user',
		);

		if ( null !== $visibility ) {
			$meta_input['activitypub_content_visibility'] = $visibility;
		}

		$post_id = self::factory()->post->create(
			array(
				'post_author'  => $user_id,
				'post_type'    => Outbox::POST_TYPE,
				'post_status'  => 'pending',
				'post_title'   => 'https://example.org/activity/1',
				'post_content' => \wp_json_encode(
					array(
						'@context' => array( 'https://www.w3.org/ns/activitystreams' ),
						'id'       => 'https://example.org/activity/1',
						'type'     => 'Create',
						'actor'    => 'https://example.org/user/' . $user_id,
						'object'   => array(
							'id'      => 'https://example.org/note/1',
							'type'    => 'Note',
							'content' => 'Test content',
						),
					)
				),
				'meta_input'   => $meta_input,
			)
		);

		// Test as logged-out user.
		\wp_set_current_user( 0 );
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/outbox' );

		if ( $public_visible ) {
			// For publicly visible content, request a page to verify it appears in orderedItems.
			$request->set_param( 'page', 1 ); // Need to request a page to get orderedItems.
			$request->set_param( 'per_page', 10 ); // Need per_page for pagination calculation.
			$response = \rest_get_server()->dispatch( $request );
			$data     = $response->get_data();

			$this->assertEquals( 200, $response->get_status() );
			$this->assertSame(
				1,
				(int) \count( $data['orderedItems'] ),
				sprintf(
					'Content with visibility "%s" should be visible to logged-out users.',
					$visibility ?? 'none'
				)
			);
		} else {
			// For non-public content, check the collection without pagination to verify totalItems is 0.
			$response = \rest_get_server()->dispatch( $request );
			$data     = $response->get_data();

			$this->assertEquals( 200, $response->get_status() );
			$this->assertEquals(
				0,
				$data['totalItems'],
				sprintf(
					'Content with visibility "%s" should not be visible to logged-out users (totalItems should be 0).',
					$visibility ?? 'none'
				)
			);
		}

		// Test as logged-in user with activitypub capability.
		\wp_set_current_user( $user_id );
		$this->assertTrue( \current_user_can( 'activitypub' ) );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $user_id . '/outbox' );
		$request->set_param( 'page', 1 ); // Need to request a page to get orderedItems.
		$request->set_param( 'per_page', 10 ); // Need per_page for pagination calculation.
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame(
			(int) $private_visible,
			(int) \count( $data['orderedItems'] ),
			sprintf(
				'Content with visibility "%s" should%s be visible to users with activitypub capability.',
				$visibility ?? 'none',
				$private_visible ? '' : ' not'
			)
		);

		\wp_delete_post( $post_id, true );
		\wp_delete_user( $user_id );
	}

	/**
	 * Test getting items with correct actor type filtering.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_actor_type_filtering() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		// Create a post with blog actor type.
		$blog_post_id = self::factory()->post->create(
			array(
				'post_author'  => 0,
				'post_type'    => Outbox::POST_TYPE,
				'post_status'  => 'pending',
				'post_title'   => 'https://example.org/activity/2',
				'post_content' => wp_json_encode(
					array(
						'@context' => array( 'https://www.w3.org/ns/activitystreams' ),
						'id'       => 'https://example.org/activity/2',
						'type'     => 'Create',
						'actor'    => 'https://example.org/blog',
						'object'   => array(
							'id'      => 'https://example.org/note/2',
							'type'    => 'Note',
							'content' => 'Test content',
						),
					)
				),
				'meta_input'   => array(
					'_activitypub_activity_type'     => 'Create',
					'_activitypub_activity_actor'    => 'blog',
					'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
				),
			)
		);

		// Test user outbox only returns user actor type.
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/outbox' );
		$request->set_param( 'page', 1 ); // Need to request a page to get orderedItems.
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 10, $data['orderedItems'] );

		// Test blog outbox only returns blog actor type.
		$request = new \WP_REST_Request( 'GET', sprintf( '/%s/actors/0/outbox', ACTIVITYPUB_REST_NAMESPACE ) );
		$request->set_param( 'page', 1 ); // Need to request a page to get orderedItems.
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );

		\wp_delete_post( $blog_post_id, true );
		\delete_option( 'activitypub_actor_mode' );
	}

	/**
	 * Test meta query behavior for non-privileged users.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_meta_query_for_non_privileged_users() {
		$viewer_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		// Create a private post.
		$private_post_id = self::factory()->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_type'    => Outbox::POST_TYPE,
				'post_status'  => 'draft',
				'post_title'   => 'https://example.org/activity/2',
				'post_content' => wp_json_encode(
					array(
						'@context' => array( 'https://www.w3.org/ns/activitystreams' ),
						'id'       => 'https://example.org/activity/2',
						'type'     => 'Follow',
						'actor'    => 'https://example.org/user/' . self::$user_id,
						'object'   => 'https://example.org/user/123',
					)
				),
				'meta_input'   => array(
					'_activitypub_activity_type'     => 'Follow',
					'_activitypub_activity_actor'    => 'user',
					'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL,
				),
			)
		);

		// Test as non-privileged user.
		wp_set_current_user( $viewer_id );
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/outbox' );
		$request->set_param( 'page', 1 ); // Need to request a page to get orderedItems.
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 10, $data['orderedItems'] );

		// Test as privileged user.
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/outbox' );
		$request->set_param( 'page', 1 ); // Need to request a page to get orderedItems.
		$request->set_param( 'per_page', 20 ); // Need per_page for pagination calculation.
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 11, $data['orderedItems'] );

		\wp_delete_post( $private_post_id, true );
		\wp_delete_user( $viewer_id );
		\wp_delete_user( $admin_id );
	}

	/**
	 * Test get_item method.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_item() {
		// Controller does not implement get_item().
	}

	/**
	 * Test get_item_schema method.
	 *
	 * @doesNotPerformAssertions
	 */
	public function test_get_item_schema() {
		// Controller does not implement get_item_schema().
	}
}
