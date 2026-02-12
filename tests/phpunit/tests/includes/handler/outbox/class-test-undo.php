<?php
/**
 * Test file for Outbox Undo Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler\Outbox;

use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Handler\Outbox\Undo;

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

		$this->user_id = self::factory()->user->create();
	}

	/**
	 * Test that handle_undo removes following relationship.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_follow_removes_following() {
		$actor_url = 'https://example.com/users/unfollow-test';

		// Mock the HTTP request.
		$fake_response = array(
			'type'              => 'Person',
			'id'                => $actor_url,
			'name'              => 'Unfollow Test',
			'preferredUsername' => 'unfollowtest',
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

		// Create the remote actor and add following meta.
		$remote_actor = Remote_Actors::fetch_by_uri( $actor_url );

		if ( \is_wp_error( $remote_actor ) ) {
			$this->fail( 'Could not create remote actor: ' . $remote_actor->get_error_message() );
		}

		\add_post_meta( $remote_actor->ID, Following::FOLLOWING_META_KEY, (string) $this->user_id );

		// Verify following exists.
		$following = \get_post_meta( $remote_actor->ID, Following::FOLLOWING_META_KEY, false );
		$this->assertContains( (string) $this->user_id, $following, 'User should be in following before undo.' );

		// Send Undo Follow.
		$data = array(
			'type'   => 'Undo',
			'object' => array(
				'type'   => 'Follow',
				'object' => $actor_url,
			),
		);

		Undo::handle_undo( $data, $this->user_id );

		// Verify following was removed.
		$following = \get_post_meta( $remote_actor->ID, Following::FOLLOWING_META_KEY, false );
		$this->assertNotContains( (string) $this->user_id, $following, 'User should be removed from following.' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}

	/**
	 * Test that handle_undo removes pending following.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_follow_removes_pending() {
		$actor_url = 'https://example.com/users/pending-undo';

		$fake_response = array(
			'type'              => 'Person',
			'id'                => $actor_url,
			'name'              => 'Pending Undo',
			'preferredUsername' => 'pendingundo',
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

		if ( \is_wp_error( $remote_actor ) ) {
			$this->fail( 'Could not create remote actor: ' . $remote_actor->get_error_message() );
		}

		\add_post_meta( $remote_actor->ID, Following::PENDING_META_KEY, (string) $this->user_id );

		// Verify pending exists.
		$pending = \get_post_meta( $remote_actor->ID, Following::PENDING_META_KEY, false );
		$this->assertContains( (string) $this->user_id, $pending, 'User should be in pending before undo.' );

		$data = array(
			'type'   => 'Undo',
			'object' => array(
				'type'   => 'Follow',
				'object' => $actor_url,
			),
		);

		Undo::handle_undo( $data, $this->user_id );

		$pending = \get_post_meta( $remote_actor->ID, Following::PENDING_META_KEY, false );
		$this->assertNotContains( (string) $this->user_id, $pending, 'User should be removed from pending.' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}

	/**
	 * Test that handle_undo fires action hook on success.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_fires_action() {
		$actor_url = 'https://example.com/users/undo-action-test';

		$fake_response = array(
			'type'              => 'Person',
			'id'                => $actor_url,
			'name'              => 'Undo Action',
			'preferredUsername' => 'undoaction',
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

		if ( \is_wp_error( $remote_actor ) ) {
			$this->fail( 'Could not create remote actor.' );
		}

		\add_post_meta( $remote_actor->ID, Following::FOLLOWING_META_KEY, (string) $this->user_id );

		$fired = false;

		$callback = function () use ( &$fired ) {
			$fired = true;
		};
		\add_action( 'activitypub_outbox_undo_follow_sent', $callback );

		$data = array(
			'type'   => 'Undo',
			'object' => array(
				'type'   => 'Follow',
				'object' => $actor_url,
			),
		);

		Undo::handle_undo( $data, $this->user_id );

		$this->assertTrue( $fired, 'activitypub_outbox_undo_follow_sent action should fire.' );

		\remove_action( 'activitypub_outbox_undo_follow_sent', $callback );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}

	/**
	 * Test that handle_undo ignores non-Follow types.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_ignores_non_follow() {
		$fired = false;

		$callback = function () use ( &$fired ) {
			$fired = true;
		};
		\add_action( 'activitypub_outbox_undo_follow_sent', $callback );

		$data = array(
			'type'   => 'Undo',
			'object' => array(
				'type'   => 'Like',
				'object' => 'https://example.com/note/123',
			),
		);

		Undo::handle_undo( $data, $this->user_id );

		$this->assertFalse( $fired, 'Action should not fire for non-Follow undo.' );

		\remove_action( 'activitypub_outbox_undo_follow_sent', $callback );
	}

	/**
	 * Test that handle_undo returns early for non-array object.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_non_array_object() {
		$data = array(
			'type'   => 'Undo',
			'object' => 'https://example.com/follow/123',
		);

		// Should not throw errors.
		Undo::handle_undo( $data, $this->user_id );
		$this->assertTrue( true );
	}

	/**
	 * Test that handle_undo returns early for empty target.
	 *
	 * @covers ::handle_undo
	 */
	public function test_handle_undo_empty_target() {
		$data = array(
			'type'   => 'Undo',
			'object' => array(
				'type'   => 'Follow',
				'object' => '',
			),
		);

		// Should not throw errors.
		Undo::handle_undo( $data, $this->user_id );
		$this->assertTrue( true );
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
