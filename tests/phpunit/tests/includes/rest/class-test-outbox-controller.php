<?php
/**
 * Outbox REST API endpoint test file.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Outbox;
use Activitypub\Rest\Outbox_Controller;
use Activitypub\Tests\Test_REST_Controller_Testcase;

/**
 * Tests for Outbox REST API endpoint.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Outbox_Controller
 */
class Test_Outbox_Controller extends Test_REST_Controller_Testcase {

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
		// Ensure the post scheduler hook is present (may be removed by other test classes).
		if ( ! \has_action( 'wp_after_insert_post', array( \Activitypub\Scheduler\Post::class, 'triage' ) ) ) {
			\add_action( 'wp_after_insert_post', array( \Activitypub\Scheduler\Post::class, 'triage' ), 33, 4 );
		}

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
		\add_filter( 'activitypub_oauth_check_permission', '__return_true' );

		// Ensure the post scheduler hook is present (may be removed by other test classes).
		if ( ! \has_action( 'wp_after_insert_post', array( \Activitypub\Scheduler\Post::class, 'triage' ) ) ) {
			\add_action( 'wp_after_insert_post', array( \Activitypub\Scheduler\Post::class, 'triage' ), 33, 4 );
		}
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

		// Empty collections skip pagination metadata.
		// Use a fresh user with no outbox entries to test empty collection behavior.
		$empty_user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'ID', $empty_user_id )->add_cap( 'activitypub' );

		$request = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . $empty_user_id . '/outbox' );
		$request->set_param( 'per_page', 3 );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayNotHasKey( 'first', $data );
		$this->assertArrayNotHasKey( 'last', $data );
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

		$outbox_array_filter = function ( $response ) use ( &$filter_called ) {
			$filter_called = true;
			return $response;
		};
		\add_filter( 'activitypub_rest_outbox_array', $outbox_array_filter );

		$outbox_pre_action = function () use ( &$pre_called ) {
			$pre_called = true;
		};
		\add_action( 'activitypub_rest_outbox_pre', $outbox_pre_action );

		$outbox_post_action = function () use ( &$post_called ) {
			$post_called = true;
		};
		\add_action( 'activitypub_rest_outbox_post', $outbox_post_action );

		$request = new \WP_REST_Request( 'GET', sprintf( '/%s/actors/%s/outbox', ACTIVITYPUB_REST_NAMESPACE, self::$user_id ) );
		\rest_get_server()->dispatch( $request );

		$this->assertTrue( $filter_called, 'activitypub_rest_outbox_array filter was not called.' );
		$this->assertTrue( $pre_called, 'activitypub_rest_outbox_pre action was not called.' );
		$this->assertTrue( $post_called, 'activitypub_outbox_post action was not called.' );

		\remove_filter( 'activitypub_rest_outbox_array', $outbox_array_filter );
		\remove_action( 'activitypub_rest_outbox_pre', $outbox_pre_action );
		\remove_action( 'activitypub_rest_outbox_post', $outbox_post_action );
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
		self::factory()->post->create(
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

		self::factory()->post->create(
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
	}

	/**
	 * Test getting items with correct actor type filtering.
	 *
	 * @covers ::get_items
	 */
	public function test_get_items_actor_type_filtering() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		// Create a post with blog actor type.
		self::factory()->post->create(
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

		$this->assertEquals( 200, $response->get_status() );

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
		self::factory()->post->create(
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

	/**
	 * Test C2S POST creates Note with proper object ID.
	 *
	 * When a client submits a Create activity with an object that has no ID,
	 * the server should create a WordPress post and use its permalink as the
	 * object ID (not generate a random /objects/uuid URL).
	 *
	 * @covers ::create_item
	 */
	public function test_c2s_create_note_object_id() {
		$user = \Activitypub\Collection\Actors::get_by_id( self::$user_id );

		$data = array(
			'type'   => 'Create',
			'actor'  => $user->get_id(),
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'    => 'Note',
				'content' => 'Hello from C2S test!',
				// No ID provided - server should set it to the post permalink.
			),
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/outbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $data ) );

		\wp_set_current_user( self::$user_id );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );

		$response_data = $response->get_data();

		// The object should have an ID that's a post permalink, not /objects/uuid.
		$this->assertArrayHasKey( 'object', $response_data );
		$object = $response_data['object'];

		if ( is_array( $object ) ) {
			$this->assertArrayHasKey( 'id', $object );
			$this->assertStringNotContainsString( '/objects/', $object['id'], 'Object ID should not be a /objects/uuid URL' );
			$this->assertStringContainsString( '?p=', $object['id'], 'Object ID should be a post permalink' );
		}
	}

	/**
	 * Test C2S POST creates Note with 'status' post format.
	 *
	 * When a client submits a Note via C2S, the created WordPress post
	 * should have the 'status' post format so that the transformer maps
	 * it back to a Note type.
	 *
	 * @covers ::create_item
	 */
	public function test_c2s_create_note_sets_status_post_format() {
		$user = \Activitypub\Collection\Actors::get_by_id( self::$user_id );

		$data = array(
			'type'   => 'Create',
			'actor'  => $user->get_id(),
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'    => 'Note',
				'content' => 'A short status note via C2S.',
			),
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/outbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $data ) );

		\wp_set_current_user( self::$user_id );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );

		$response_data = $response->get_data();
		$object        = $response_data['object'];

		// Find the created post by its permalink.
		if ( is_array( $object ) && ! empty( $object['id'] ) ) {
			$post_id = \url_to_postid( $object['id'] );
			$this->assertGreaterThan( 0, $post_id, 'Should find a post from the object ID.' );
			$this->assertSame( 'status', \get_post_format( $post_id ), 'Note should have status post format.' );
		}
	}

	/**
	 * Test C2S POST creates Article without post format.
	 *
	 * When a client submits an Article via C2S, the created WordPress post
	 * should not have a post format set (standard format).
	 *
	 * @covers ::create_item
	 */
	public function test_c2s_create_article_has_no_post_format() {
		$user = \Activitypub\Collection\Actors::get_by_id( self::$user_id );

		$data = array(
			'type'   => 'Create',
			'actor'  => $user->get_id(),
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'object' => array(
				'type'    => 'Article',
				'name'    => 'My Article Title',
				'content' => '<p>This is a full article with a title.</p>',
			),
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/outbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $data ) );

		\wp_set_current_user( self::$user_id );

		$response = \rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );

		$response_data = $response->get_data();
		$object        = $response_data['object'];

		// Find the created post by its permalink.
		if ( is_array( $object ) && ! empty( $object['id'] ) ) {
			$post_id = \url_to_postid( $object['id'] );
			$this->assertGreaterThan( 0, $post_id, 'Should find a post from the object ID.' );
			$this->assertFalse( \get_post_format( $post_id ), 'Article should have standard (no) post format.' );
		}
	}

	/**
	 * Test C2S POST with an Arrive activity creates a WordPress post.
	 *
	 * Ensures the Arrive handler creates a check-in post with location
	 * geodata and stores the activity in the outbox.
	 *
	 * @covers ::create_item
	 */
	public function test_c2s_arrive_creates_post_with_geodata() {
		$user = \Activitypub\Collection\Actors::get_by_id( self::$user_id );

		$data = array(
			'@context' => 'https://www.w3.org/ns/activitystreams',
			'type'     => 'Arrive',
			'actor'    => array(
				'id'   => $user->get_id(),
				'name' => $user->get_name(),
				'url'  => $user->get_url(),
			),
			'location' => array(
				'type'      => 'Place',
				'id'        => 'https://places.pub/relation/659839',
				'name'      => 'Ettlingen',
				'latitude'  => 48.9408,
				'longitude' => 8.4075,
			),
			'content'  => 'Arrived.',
			'to'       => 'https://www.w3.org/ns/activitystreams#Public',
			'cc'       => $user->get_followers(),
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/outbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $data ) );

		\wp_set_current_user( self::$user_id );

		$response = \rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$response_data = $response->get_data();
		$this->assertSame( 'Arrive', $response_data['type'], 'Activity type should be preserved as Arrive.' );
		$this->assertArrayHasKey( 'url', $response_data, 'Arrive should include a url to the blog post.' );

		// Find the blog post created as a side effect.
		$post_id = \url_to_postid( $response_data['url'] );
		$this->assertGreaterThan( 0, $post_id, 'Arrive should create a WordPress post as side effect.' );

		$this->assertSame( 'status', \get_post_format( $post_id ), 'Arrive post should have status format.' );
		$this->assertStringContainsString( 'Ettlingen', \get_the_title( $post_id ), 'Post title should contain location name.' );

		// Verify geodata meta.
		$this->assertSame( 'Ettlingen', \get_post_meta( $post_id, 'geo_address', true ) );
		$this->assertEquals( 48.9408, (float) \get_post_meta( $post_id, 'geo_latitude', true ) );
		$this->assertEquals( 8.4075, (float) \get_post_meta( $post_id, 'geo_longitude', true ) );
		$this->assertSame( '1', \get_post_meta( $post_id, 'geo_public', true ) );
	}

	/**
	 * Test C2S POST with Arrive activity without coordinates.
	 *
	 * Ensures the handler works when the location only has a name
	 * but no latitude/longitude, as is common with checkin.swf.pub.
	 *
	 * @covers ::create_item
	 */
	public function test_c2s_arrive_with_name_only_location() {
		$user = \Activitypub\Collection\Actors::get_by_id( self::$user_id );

		$data = array(
			'@context'   => 'https://www.w3.org/ns/activitystreams',
			'type'       => 'Arrive',
			'actor'      => $user->get_id(),
			'location'   => array(
				'id'   => 'https://places.pub/relation/659839',
				'name' => 'Ettlingen',
			),
			'content'    => 'Hello!',
			'summaryMap' => array(
				'en' => 'Arrived at Ettlingen',
			),
			'to'         => 'https://www.w3.org/ns/activitystreams#Public',
			'cc'         => $user->get_followers(),
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/outbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $data ) );

		\wp_set_current_user( self::$user_id );

		$response = \rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		// Find the blog post created as a side effect.
		$response_data = $response->get_data();
		$this->assertSame( 'Arrive', $response_data['type'], 'Activity type should be preserved as Arrive.' );

		$post_id = \url_to_postid( $response_data['url'] );
		$this->assertGreaterThan( 0, $post_id, 'Arrive should create a WordPress post as side effect.' );

		// Verify geodata - address saved but no coordinates.
		$this->assertSame( 'Ettlingen', \get_post_meta( $post_id, 'geo_address', true ) );
		$this->assertEmpty( \get_post_meta( $post_id, 'geo_latitude', true ), 'No latitude when not provided.' );
		$this->assertEmpty( \get_post_meta( $post_id, 'geo_longitude', true ), 'No longitude when not provided.' );
		$this->assertSame( '1', \get_post_meta( $post_id, 'geo_public', true ), 'geo_public set when name is present.' );
	}

	/**
	 * Test C2S POST with exact checkin.swf.pub payload.
	 *
	 * The checkin app sends inline actor objects, string to/cc,
	 * summaryMap without summary, and content. This test verifies
	 * that Posts::create receives all required fields after the
	 * outbox controller transforms the data.
	 *
	 * @covers ::create_item
	 */
	public function test_c2s_arrive_with_checkin_app_payload() {
		$user = \Activitypub\Collection\Actors::get_by_id( self::$user_id );

		$data = array(
			'@context'   => 'https://www.w3.org/ns/activitystreams',
			'actor'      => array(
				'id'   => $user->get_id(),
				'name' => $user->get_name(),
				'url'  => $user->get_url(),
			),
			'type'       => 'Arrive',
			'location'   => array(
				'id'   => 'https://places.pub/relation/659839',
				'name' => 'Ettlingen',
				'url'  => 'https://places.pub/relation/659839',
			),
			'content'    => 'Great coffee here!',
			'to'         => 'https://www.w3.org/ns/activitystreams#Public',
			'cc'         => $user->get_followers(),
			'summaryMap' => array(
				'en' => $user->get_name() . ' arrived at Ettlingen',
			),
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/outbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $data ) );

		\wp_set_current_user( self::$user_id );

		$response = \rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status(), 'Outbox POST should return 201.' );

		$response_data = $response->get_data();
		$this->assertSame( 'Arrive', $response_data['type'] );
		$this->assertArrayHasKey( 'url', $response_data, 'Arrive should have a url pointing to the blog post.' );

		// Blog post should exist with correct content.
		$post_id = \url_to_postid( $response_data['url'] );
		$this->assertGreaterThan( 0, $post_id, 'Blog post should be created.' );

		$post = \get_post( $post_id );
		$this->assertStringContainsString( 'Ettlingen', $post->post_title );
		$this->assertNotEmpty( $post->post_content, 'Post content should not be empty.' );
		$this->assertSame( 'status', \get_post_format( $post_id ) );

		// Geodata should be saved.
		$this->assertSame( 'Ettlingen', \get_post_meta( $post_id, 'geo_address', true ) );
		$this->assertSame( '1', \get_post_meta( $post_id, 'geo_public', true ) );
	}

	/**
	 * Test C2S POST with Arrive without content uses summary fallback.
	 *
	 * When the checkin app omits content but provides summaryMap,
	 * the localized summary should be used as post content.
	 *
	 * @covers ::create_item
	 */
	public function test_c2s_arrive_without_content_uses_summary() {
		$user = \Activitypub\Collection\Actors::get_by_id( self::$user_id );

		$data = array(
			'@context'   => 'https://www.w3.org/ns/activitystreams',
			'type'       => 'Arrive',
			'actor'      => $user->get_id(),
			'location'   => array(
				'id'   => 'https://places.pub/relation/123',
				'name' => 'Berlin',
			),
			'summaryMap' => array(
				'en' => $user->get_name() . ' arrived at Berlin',
			),
			'to'         => 'https://www.w3.org/ns/activitystreams#Public',
			'cc'         => $user->get_followers(),
		);

		$request = new \WP_REST_Request( 'POST', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/' . self::$user_id . '/outbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $data ) );

		\wp_set_current_user( self::$user_id );

		$response = \rest_get_server()->dispatch( $request );
		$this->assertEquals( 201, $response->get_status() );

		$response_data = $response->get_data();
		$post_id       = \url_to_postid( $response_data['url'] );
		$this->assertGreaterThan( 0, $post_id, 'Blog post should be created even without content.' );

		$post = \get_post( $post_id );
		$this->assertStringContainsString( 'Berlin', $post->post_title );
		// The summary should be used as content fallback.
		$this->assertNotEmpty( $post->post_content, 'Post should have content from summary fallback.' );
	}

	/**
	 * Test that totalItems for the blog actor excludes anonymous comments.
	 *
	 * @covers ::overload_total_items
	 */
	public function test_blog_actor_total_items_excludes_anonymous_comments() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		\wp_set_current_user( 0 );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => self::$user_id,
				'post_status' => 'publish',
			)
		);
		\update_post_meta( $post_id, 'activitypub_status', 'federated' );

		// Create a federated comment from a local user (should be counted).
		$local_comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'user_id'         => self::$user_id,
			)
		);
		\update_comment_meta( $local_comment_id, 'activitypub_status', 'federated' );

		$request  = new \WP_REST_Request( 'GET', '/' . ACTIVITYPUB_REST_NAMESPACE . '/actors/0/outbox' );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'totalItems', $data );

		$count_before = $data['totalItems'];

		// Create a federated comment from an anonymous/remote user (should NOT be counted).
		$remote_comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'    => $post_id,
				'user_id'            => 0,
				'comment_author'     => 'Remote User',
				'comment_author_url' => 'https://remote.example/user',
			)
		);
		\update_comment_meta( $remote_comment_id, 'activitypub_status', 'federated' );

		$response2 = \rest_get_server()->dispatch( $request );
		$data2     = $response2->get_data();

		$this->assertEquals( $count_before, $data2['totalItems'], 'Remote comment should not inflate totalItems.' );
	}
}
