<?php
/**
 * Test Event Stream Trait.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Collection\Outbox;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server as OAuth_Server;
use Activitypub\Rest\Event_Stream;
use Activitypub\Rest\Verification;

use function Activitypub\add_to_outbox;

/**
 * Test Event Stream Trait.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Event_Stream
 */
class Test_Trait_Event_Stream extends \WP_UnitTestCase {

	/**
	 * Test class instance that uses the trait.
	 *
	 * @var object
	 */
	protected $instance;

	/**
	 * Test user ID.
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
		\get_user_by( 'ID', $this->user_id )->add_cap( 'activitypub' );

		// Prevent outbox processing from dispatching during tests.
		\remove_all_actions( 'activitypub_process_outbox' );

		// Create a test class that uses the trait, exposing protected methods.
		$this->instance = new class() extends \WP_REST_Controller {
			use Event_Stream;
			use Verification;

			/**
			 * The namespace of this controller's route.
			 *
			 * @var string
			 */
			protected $namespace = ACTIVITYPUB_REST_NAMESPACE;

			/**
			 * Expose get_event_type for testing.
			 *
			 * @param \WP_Post $item       The post item.
			 * @param string   $collection The collection type.
			 * @return string The event type.
			 */
			public function test_get_event_type( $item, $collection ) {
				return $this->get_event_type( $item, $collection );
			}

			/**
			 * Expose get_event_data for testing.
			 *
			 * @param \WP_Post $item       The post item.
			 * @param string   $collection The collection type.
			 * @return array|null The event data.
			 */
			public function test_get_event_data( $item, $collection ) {
				return $this->get_event_data( $item, $collection );
			}

			/**
			 * Expose get_new_items for testing.
			 *
			 * @param int    $user_id    The actor ID.
			 * @param string $collection The collection type.
			 * @param int    $since_id   Items after this ID.
			 * @return \WP_Post[] The new items.
			 */
			public function test_get_new_items( $user_id, $collection, $since_id ) {
				return $this->get_new_items( $user_id, $collection, $since_id );
			}

			/**
			 * Expose get_latest_item_id for testing.
			 *
			 * @param int    $user_id    The actor ID.
			 * @param string $collection The collection type.
			 * @return int The latest item ID.
			 */
			public function test_get_latest_item_id( $user_id, $collection ) {
				return $this->get_latest_item_id( $user_id, $collection );
			}

			/**
			 * Expose get_event_type_map for testing.
			 *
			 * @return array The event type map.
			 */
			public static function test_get_event_type_map() {
				return self::get_event_type_map();
			}
		};
	}

	/**
	 * Test that outbox stream route is registered.
	 *
	 * @covers ::get_stream_permissions_check
	 */
	public function test_outbox_stream_route_is_registered() {
		$routes = \rest_get_server()->get_routes();

		$this->assertArrayHasKey(
			'/' . ACTIVITYPUB_REST_NAMESPACE . '/(?:users|actors)/(?P<user_id>[-]?\d+)/outbox/stream',
			$routes,
			'Outbox stream route should be registered.'
		);
	}

	/**
	 * Test that inbox stream route is registered.
	 *
	 * @covers ::get_stream_permissions_check
	 */
	public function test_inbox_stream_route_is_registered() {
		$routes = \rest_get_server()->get_routes();

		$this->assertArrayHasKey(
			'/' . ACTIVITYPUB_REST_NAMESPACE . '/(?:users|actors)\/(?P<user_id>[-]?\d+)/inbox/stream',
			$routes,
			'Inbox stream route should be registered.'
		);
	}

	/**
	 * Test that eventStream URL is present in outbox response.
	 *
	 * @covers ::get_stream_url
	 */
	public function test_outbox_response_contains_event_stream_url() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$request  = new \WP_REST_Request( 'GET', sprintf( '/%s/actors/%s/outbox', ACTIVITYPUB_REST_NAMESPACE, $this->user_id ) );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'eventStream', $data, 'Outbox response should contain eventStream URL.' );
		$this->assertStringContainsString( '/outbox/stream', $data['eventStream'] );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );
	}

	/**
	 * Test that eventStream URL is present in inbox response.
	 *
	 * @covers ::get_stream_url
	 */
	public function test_inbox_response_contains_event_stream_url() {
		\add_filter( 'activitypub_oauth_check_permission', '__return_true' );

		\wp_set_current_user( $this->user_id );

		$request  = new \WP_REST_Request( 'GET', sprintf( '/%s/actors/%s/inbox', ACTIVITYPUB_REST_NAMESPACE, $this->user_id ) );
		$response = \rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'eventStream', $data, 'Inbox response should contain eventStream URL.' );
		$this->assertStringContainsString( '/inbox/stream', $data['eventStream'] );

		\remove_filter( 'activitypub_oauth_check_permission', '__return_true' );
	}

	/**
	 * Test get_event_type for outbox Create activity.
	 *
	 * @covers ::get_event_type
	 */
	public function test_get_event_type_outbox_create() {
		$post = self::factory()->post->create_and_get();
		\update_post_meta( $post->ID, '_activitypub_activity_type', 'Create' );

		$this->assertEquals( 'Add', $this->instance->test_get_event_type( $post, 'outbox' ) );
	}

	/**
	 * Test get_event_type for outbox Update activity.
	 *
	 * @covers ::get_event_type
	 */
	public function test_get_event_type_outbox_update() {
		$post = self::factory()->post->create_and_get();
		\update_post_meta( $post->ID, '_activitypub_activity_type', 'Update' );

		$this->assertEquals( 'Update', $this->instance->test_get_event_type( $post, 'outbox' ) );
	}

	/**
	 * Test get_event_type for outbox Delete activity.
	 *
	 * @covers ::get_event_type
	 */
	public function test_get_event_type_outbox_delete() {
		$post = self::factory()->post->create_and_get();
		\update_post_meta( $post->ID, '_activitypub_activity_type', 'Delete' );

		$this->assertEquals( 'Delete', $this->instance->test_get_event_type( $post, 'outbox' ) );
	}

	/**
	 * Test get_event_type for outbox Undo activity.
	 *
	 * @covers ::get_event_type
	 */
	public function test_get_event_type_outbox_undo() {
		$post = self::factory()->post->create_and_get();
		\update_post_meta( $post->ID, '_activitypub_activity_type', 'Undo' );

		$this->assertEquals( 'Remove', $this->instance->test_get_event_type( $post, 'outbox' ) );
	}

	/**
	 * Test get_event_type for outbox Announce activity.
	 *
	 * @covers ::get_event_type
	 */
	public function test_get_event_type_outbox_announce() {
		$post = self::factory()->post->create_and_get();
		\update_post_meta( $post->ID, '_activitypub_activity_type', 'Announce' );

		$this->assertEquals( 'Add', $this->instance->test_get_event_type( $post, 'outbox' ) );
	}

	/**
	 * Test get_event_type for outbox Like activity.
	 *
	 * @covers ::get_event_type
	 */
	public function test_get_event_type_outbox_like() {
		$post = self::factory()->post->create_and_get();
		\update_post_meta( $post->ID, '_activitypub_activity_type', 'Like' );

		$this->assertEquals( 'Add', $this->instance->test_get_event_type( $post, 'outbox' ) );
	}

	/**
	 * Test get_event_type defaults to Add for unknown types.
	 *
	 * @covers ::get_event_type
	 */
	public function test_get_event_type_outbox_unknown_defaults_to_add() {
		$post = self::factory()->post->create_and_get();
		\update_post_meta( $post->ID, '_activitypub_activity_type', 'Accept' );

		$this->assertEquals( 'Add', $this->instance->test_get_event_type( $post, 'outbox' ) );
	}

	/**
	 * Test get_event_type for inbox always returns Add.
	 *
	 * @covers ::get_event_type
	 */
	public function test_get_event_type_inbox_always_add() {
		$post = self::factory()->post->create_and_get();
		\update_post_meta( $post->ID, '_activitypub_activity_type', 'Delete' );

		$this->assertEquals( 'Add', $this->instance->test_get_event_type( $post, 'inbox' ) );
	}

	/**
	 * Test get_new_items returns items after since_id.
	 *
	 * @covers ::get_new_items
	 */
	public function test_get_new_items_returns_items_after_since_id() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);

		$outbox_id = add_to_outbox( \get_post( $post_id ), 'Create', $this->user_id );
		$this->assertIsInt( $outbox_id );

		// Items after since_id = 0 should include the new item.
		$items = $this->instance->test_get_new_items( $this->user_id, 'outbox', 0 );
		$this->assertNotEmpty( $items, 'Should return items when since_id is 0.' );

		// Items after the outbox ID should return nothing.
		$items = $this->instance->test_get_new_items( $this->user_id, 'outbox', $outbox_id );
		$this->assertEmpty( $items, 'Should return no items when since_id matches latest.' );
	}

	/**
	 * Test get_latest_item_id returns correct ID.
	 *
	 * @covers ::get_latest_item_id
	 */
	public function test_get_latest_item_id() {
		$post_id   = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);
		$outbox_id = add_to_outbox( \get_post( $post_id ), 'Create', $this->user_id );

		$latest = $this->instance->test_get_latest_item_id( $this->user_id, 'outbox' );
		$this->assertEquals( $outbox_id, $latest, 'Should return the latest outbox item ID.' );
	}

	/**
	 * Test that the outbox stream only returns the requested actor's own items.
	 *
	 * Regression: the outbox query previously filtered solely by the shared actor
	 * *type* meta ('user'), so one author's stream emitted every author's outbox
	 * items. The query must be scoped to the requesting actor.
	 *
	 * @covers ::get_new_items
	 * @covers ::get_latest_item_id
	 */
	public function test_outbox_stream_is_scoped_to_owner() {
		$other_user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'ID', $other_user_id )->add_cap( 'activitypub' );

		$own_post   = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);
		$own_outbox = add_to_outbox( \get_post( $own_post ), 'Create', $this->user_id );

		// Created after the owner's item, so it has a higher ID and would be the global "latest".
		$other_post   = self::factory()->post->create(
			array(
				'post_author' => $other_user_id,
				'post_status' => 'publish',
			)
		);
		$other_outbox = add_to_outbox( \get_post( $other_post ), 'Create', $other_user_id );

		$ids = \wp_list_pluck( $this->instance->test_get_new_items( $this->user_id, 'outbox', 0 ), 'ID' );
		$this->assertContains( $own_outbox, $ids, 'Owner should see their own outbox item.' );
		$this->assertNotContains( $other_outbox, $ids, "Owner must not see another user's outbox item." );

		$latest = $this->instance->test_get_latest_item_id( $this->user_id, 'outbox' );
		$this->assertSame( $own_outbox, $latest, "Latest item must be the owner's, not a newer item from another user." );
	}

	/**
	 * Test get_event_type_map returns expected mappings.
	 *
	 * @covers ::get_event_type_map
	 */
	public function test_get_event_type_map() {
		$map = $this->instance::test_get_event_type_map();

		$this->assertIsArray( $map );
		$this->assertEquals( 'Add', $map['Create'] );
		$this->assertEquals( 'Add', $map['Announce'] );
		$this->assertEquals( 'Add', $map['Like'] );
		$this->assertEquals( 'Update', $map['Update'] );
		$this->assertEquals( 'Delete', $map['Delete'] );
		$this->assertEquals( 'Remove', $map['Undo'] );
	}

	/**
	 * Test get_stream_url format.
	 *
	 * @covers ::get_stream_url
	 */
	public function test_get_stream_url() {
		$outbox_url = $this->instance->get_stream_url( $this->user_id, 'outbox' );
		$inbox_url  = $this->instance->get_stream_url( $this->user_id, 'inbox' );

		$this->assertStringContainsString( sprintf( 'actors/%d/outbox/stream', $this->user_id ), $outbox_url );
		$this->assertStringContainsString( sprintf( 'actors/%d/inbox/stream', $this->user_id ), $inbox_url );
	}

	/**
	 * Test get_event_data returns activity data for valid outbox item.
	 *
	 * @covers ::get_event_data
	 */
	public function test_get_event_data_returns_data_for_valid_outbox_item() {
		$post_id   = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);
		$outbox_id = add_to_outbox( \get_post( $post_id ), 'Create', $this->user_id );

		$data = $this->instance->test_get_event_data( \get_post( $outbox_id ), 'outbox' );

		$this->assertIsArray( $data );
		$this->assertEquals( 'Create', $data['type'] );
	}

	/**
	 * Test get_event_data returns data for inbox item.
	 *
	 * @covers ::get_event_data
	 */
	public function test_get_event_data_returns_data_for_inbox_item() {
		$activity = array(
			'type'   => 'Create',
			'actor'  => 'https://example.com/users/test',
			'object' => array(
				'type'    => 'Note',
				'content' => 'Hello',
			),
		);

		$post_id = self::factory()->post->create(
			array(
				'post_content' => \wp_json_encode( $activity ),
				'post_type'    => 'ap_inbox',
				'post_status'  => 'publish',
			)
		);

		$data = $this->instance->test_get_event_data( \get_post( $post_id ), 'inbox' );

		$this->assertIsArray( $data );
		$this->assertEquals( 'Create', $data['type'] );
	}

	/**
	 * Test get_event_data returns null for inbox item with invalid JSON.
	 *
	 * @covers ::get_event_data
	 */
	public function test_get_event_data_returns_null_for_invalid_inbox_json() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '',
				'post_type'    => 'ap_inbox',
				'post_status'  => 'publish',
			)
		);

		$data = $this->instance->test_get_event_data( \get_post( $post_id ), 'inbox' );
		$this->assertNull( $data, 'Should return null for empty inbox content.' );
	}

	/**
	 * Test authenticate_from_query_param ignores non-string values.
	 *
	 * @covers ::get_stream_permissions_check
	 */
	public function test_query_param_rejects_array_value() {
		$_GET['access_token'] = array( 'malicious' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Should not set HTTP_AUTHORIZATION for array values.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Testing raw superglobal state.
		$original_auth = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : null;

		\add_filter( 'activitypub_oauth_check_permission', '__return_true' );
		$this->instance->get_stream_permissions_check( new \WP_REST_Request() );
		\remove_filter( 'activitypub_oauth_check_permission', '__return_true' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Testing raw superglobal state.
		$current_auth = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? $_SERVER['HTTP_AUTHORIZATION'] : null;
		$this->assertEquals( $original_auth, $current_auth, 'Should not inject Authorization header for array access_token.' );

		unset( $_GET['access_token'] );
	}

	/**
	 * Test that the stream hides private activities from a token without the read scope.
	 *
	 * The stream is gated on `push`, which lets a client watch the collection. Seeing the
	 * owner's private activities is the authority the paged outbox requires `read` for, and
	 * streaming must not be a way around that.
	 *
	 * @covers ::get_new_items
	 */
	public function test_get_new_items_hides_private_without_read_scope() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
				'post_status' => 'publish',
			)
		);

		$outbox_id = add_to_outbox( \get_post( $post_id ), 'Create', $this->user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
		$this->assertIsInt( $outbox_id );

		$this->set_oauth_scopes( array( Scope::PUSH ) );
		$this->assertEmpty(
			$this->instance->test_get_new_items( $this->user_id, 'outbox', 0 ),
			'A push-only token must not receive a private activity.'
		);

		$this->set_oauth_scopes( array( Scope::PUSH, Scope::READ ) );
		$this->assertNotEmpty(
			$this->instance->test_get_new_items( $this->user_id, 'outbox', 0 ),
			'A token that also holds read is the positive control.'
		);

		$this->set_oauth_scopes( null );
		$this->assertNotEmpty(
			$this->instance->test_get_new_items( $this->user_id, 'outbox', 0 ),
			'A caller with no token is not scope-limited.'
		);
	}

	/**
	 * Put the OAuth Server into a state as though a token with these scopes authenticated.
	 *
	 * @param array|null $scopes Scopes the token carries, or null for no OAuth session.
	 */
	private function set_oauth_scopes( $scopes ) {
		$token = null;

		if ( null !== $scopes ) {
			$token = new class( $scopes ) {
				/**
				 * Scopes the token carries.
				 *
				 * @var array
				 */
				private $scopes;

				/**
				 * Constructor.
				 *
				 * @param array $scopes Scopes.
				 */
				public function __construct( $scopes ) {
					$this->scopes = $scopes;
				}

				/**
				 * Check scope.
				 *
				 * @param string $scope Scope to check.
				 * @return bool
				 */
				public function has_scope( $scope ) {
					return \in_array( $scope, $this->scopes, true );
				}
			};
		}

		$property = ( new \ReflectionClass( OAuth_Server::class ) )->getProperty( 'current_token' );
		$property->setAccessible( true );
		$property->setValue( null, $token );
	}

	/**
	 * Clear any OAuth session this class established.
	 */
	public function tear_down() {
		$this->set_oauth_scopes( null );

		parent::tear_down();
	}
}
