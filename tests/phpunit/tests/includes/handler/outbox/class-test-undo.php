<?php
/**
 * Test file for Outbox Undo Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler\Outbox;

use Activitypub\Collection\Following;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Handler\Outbox\Undo;
use Activitypub\Scheduler\Post;

/**
 * Test class for Outbox Undo Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Outbox\Undo
 */
class Test_Undo extends \WP_UnitTestCase {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $user_id;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$this->user_id = self::factory()->user->create();
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );

		parent::tear_down();
	}

	/**
	 * Create a fake outbox Follow item and return its GUID.
	 *
	 * @param string $target_url The follow target actor URL.
	 * @return string The outbox item GUID.
	 */
	private function create_outbox_follow( $target_url ) {
		$activity = array(
			'type'   => 'Follow',
			'object' => $target_url,
		);

		$guid = 'http://example.org/outbox/follow-' . \wp_generate_password( 8, false );

		$post_id = \wp_insert_post(
			array(
				'post_type'    => Outbox::POST_TYPE,
				'post_title'   => '[Follow] Test',
				'post_content' => \wp_json_encode( $activity ),
				'post_author'  => $this->user_id,
				'post_status'  => 'publish',
				'guid'         => $guid,
				'meta_input'   => array(
					'_activitypub_activity_type'     => 'Follow',
					'_activitypub_activity_actor'    => 'user',
					'_activitypub_object_id'         => $target_url,
					'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
				),
			)
		);

		return \get_the_guid( $post_id );
	}

	/**
	 * Helper to create a mock remote actor.
	 *
	 * @param string $actor_url The actor URL.
	 * @return \WP_Post The remote actor post.
	 */
	private function create_remote_actor( $actor_url ) {
		$fake_response = array(
			'type'              => 'Person',
			'id'                => $actor_url,
			'name'              => 'Test Actor',
			'preferredUsername' => 'testactor',
			'inbox'             => $actor_url . '/inbox',
			'outbox'            => $actor_url . '/outbox',
			'followers'         => $actor_url . '/followers',
			'following'         => $actor_url . '/following',
			'publicKey'         => array(
				'id'           => $actor_url . '#main-key',
				'owner'        => $actor_url,
				'publicKeyPem' => "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0Rdj53hR4AdsiRcqt1Fd\n-----END PUBLIC KEY-----",
			),
		);

		$filter = function () use ( $fake_response ) {
			return $fake_response;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$remote_actor = Remote_Actors::fetch_by_uri( $actor_url );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		if ( \is_wp_error( $remote_actor ) ) {
			$this->fail( 'Could not create remote actor: ' . $remote_actor->get_error_message() );
		}

		return $remote_actor;
	}

	/**
	 * Test that handle_undo removes following relationship.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_follow_removes_following() {
		$actor_url    = 'https://example.com/users/unfollow-test';
		$remote_actor = $this->create_remote_actor( $actor_url );
		$follow_guid  = $this->create_outbox_follow( $actor_url );

		\add_post_meta( $remote_actor->ID, Following::FOLLOWING_META_KEY, (string) $this->user_id );

		// Verify following exists.
		$following = \get_post_meta( $remote_actor->ID, Following::FOLLOWING_META_KEY, false );
		$this->assertContains( (string) $this->user_id, $following, 'User should be in following before undo.' );

		$data = array(
			'type'   => 'Undo',
			'object' => $follow_guid,
		);

		Undo::handle_undo( $data, $this->user_id );

		// Verify following was removed.
		$following = \get_post_meta( $remote_actor->ID, Following::FOLLOWING_META_KEY, false );
		$this->assertNotContains( (string) $this->user_id, $following, 'User should be removed from following.' );
	}

	/**
	 * Test that handle_undo removes pending following.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_follow_removes_pending() {
		$actor_url    = 'https://example.com/users/pending-undo';
		$remote_actor = $this->create_remote_actor( $actor_url );
		$follow_guid  = $this->create_outbox_follow( $actor_url );

		\add_post_meta( $remote_actor->ID, Following::PENDING_META_KEY, (string) $this->user_id );

		// Verify pending exists.
		$pending = \get_post_meta( $remote_actor->ID, Following::PENDING_META_KEY, false );
		$this->assertContains( (string) $this->user_id, $pending, 'User should be in pending before undo.' );

		$data = array(
			'type'   => 'Undo',
			'object' => $follow_guid,
		);

		Undo::handle_undo( $data, $this->user_id );

		$pending = \get_post_meta( $remote_actor->ID, Following::PENDING_META_KEY, false );
		$this->assertNotContains( (string) $this->user_id, $pending, 'User should be removed from pending.' );
	}

	/**
	 * Test that handle_undo returns data for unknown outbox item.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_unknown_guid_returns_data() {
		$data = array(
			'type'   => 'Undo',
			'object' => 'https://example.com/unknown-activity/999',
		);

		$result = Undo::handle_undo( $data, $this->user_id );

		$this->assertSame( $data, $result, 'Should return original data for unknown GUID.' );
	}

	/**
	 * Test that handle_undo returns data for empty object.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_empty_object() {
		$data = array(
			'type'   => 'Undo',
			'object' => '',
		);

		$result = Undo::handle_undo( $data, $this->user_id );

		$this->assertSame( $data, $result, 'Should return original data for empty object.' );
	}

	/**
	 * Test that handle_undo resolves object from array with id.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_resolves_object_array() {
		$actor_url    = 'https://example.com/users/undo-array-test';
		$remote_actor = $this->create_remote_actor( $actor_url );
		$follow_guid  = $this->create_outbox_follow( $actor_url );

		\add_post_meta( $remote_actor->ID, Following::FOLLOWING_META_KEY, (string) $this->user_id );

		// Pass object as array with id (object_to_uri resolves this).
		$data = array(
			'type'   => 'Undo',
			'object' => array(
				'id'   => $follow_guid,
				'type' => 'Follow',
			),
		);

		Undo::handle_undo( $data, $this->user_id );

		$following = \get_post_meta( $remote_actor->ID, Following::FOLLOWING_META_KEY, false );
		$this->assertNotContains( (string) $this->user_id, $following, 'User should be removed from following.' );
	}

	/**
	 * Test that init registers the filter.
	 *
	 * @covers ::init
	 */
	public function test_init_registers_filter() {
		Undo::init();

		$this->assertNotFalse(
			\has_filter( 'activitypub_outbox_undo', array( Undo::class, 'handle_undo' ) ),
			'Filter should be registered.'
		);
	}
}
