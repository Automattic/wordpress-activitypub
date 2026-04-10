<?php
/**
 * Test file for Outbox Follow Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler\Outbox;

use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Handler\Outbox\Follow;

/**
 * Test class for Outbox Follow Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Outbox\Follow
 */
class Test_Follow extends \WP_UnitTestCase {

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

		$this->user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$user = \get_user_by( 'id', $this->user_id );
		$user->add_cap( 'activitypub' );

		// Prevent outbox processing from dispatching during tests.
		\remove_all_actions( 'activitypub_process_outbox' );
	}

	/**
	 * Test that handle_follow returns data for empty object.
	 *
	 * @covers ::handle_follow
	 */
	public function test_handle_follow_empty_object() {
		$data = array(
			'type'   => 'Follow',
			'object' => '',
		);

		$result = Follow::handle_follow( $data, $this->user_id );

		$this->assertSame( $data, $result, 'Should return original data for empty object.' );
	}

	/**
	 * Test that handle_follow adds pending following.
	 *
	 * @covers ::handle_follow
	 */
	public function test_handle_follow_adds_pending() {
		$actor_url = 'https://example.com/users/testuser';

		$fake_response = array(
			'type'              => 'Person',
			'id'                => $actor_url,
			'name'              => 'Test User',
			'preferredUsername' => 'testuser',
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

		$data = array(
			'type'   => 'Follow',
			'object' => $actor_url,
		);

		$result = Follow::handle_follow( $data, $this->user_id );

		// Should return an outbox post ID.
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		// Check the remote actor was created and pending meta was added.
		$remote_actor = Remote_Actors::get_by_uri( $actor_url );
		$this->assertNotWPError( $remote_actor );

		$pending = \get_post_meta( $remote_actor->ID, Following::PENDING_META_KEY, false );
		$this->assertContains( (string) $this->user_id, $pending, 'User should be in pending following.' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}

	/**
	 * Test that handle_follow does not duplicate pending entries.
	 *
	 * @covers ::handle_follow
	 */
	public function test_handle_follow_does_not_duplicate() {
		$actor_url = 'https://example.com/users/nodup';

		$fake_response = array(
			'type'              => 'Person',
			'id'                => $actor_url,
			'name'              => 'No Dup',
			'preferredUsername' => 'nodup',
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

		$data = array(
			'type'   => 'Follow',
			'object' => $actor_url,
		);

		// Follow twice.
		Follow::handle_follow( $data, $this->user_id );
		Follow::handle_follow( $data, $this->user_id );

		$remote_actor = Remote_Actors::get_by_uri( $actor_url );
		$this->assertNotWPError( $remote_actor );

		$pending = \get_post_meta( $remote_actor->ID, Following::PENDING_META_KEY, false );
		$count   = array_count_values( $pending );
		$this->assertEquals( 1, $count[ (string) $this->user_id ] ?? 0, 'User should only appear once in pending.' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
	}

	/**
	 * Test that init registers the filter.
	 *
	 * @covers ::init
	 */
	public function test_init_registers_filter() {
		Follow::init();

		$this->assertNotFalse(
			\has_filter( 'activitypub_outbox_follow', array( Follow::class, 'handle_follow' ) ),
			'Filter should be registered.'
		);
	}
}
