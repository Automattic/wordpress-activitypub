<?php
/**
 * Test file for Outbox Block Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler\Outbox;

use Activitypub\Handler\Outbox\Block;
use Activitypub\Moderation;

/**
 * Test class for Outbox Block Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Outbox\Block
 */
class Test_Block extends \WP_UnitTestCase {

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
	 * Test that handle_block blocks an actor and adds to outbox.
	 *
	 * @covers ::handle_block
	 */
	public function test_handle_block_blocks_actor() {
		$actor_url = 'https://example.com/users/spammer';

		$fake_response = array(
			'type'              => 'Person',
			'id'                => $actor_url,
			'name'              => 'Spammer',
			'preferredUsername' => 'spammer',
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

		try {
			$data = array(
				'type'   => 'Block',
				'object' => $actor_url,
			);

			$result = Block::handle_block( $data, $this->user_id );

			// Should return an outbox post ID.
			$this->assertIsInt( $result );
			$this->assertGreaterThan( 0, $result );

			// Verify the actor is blocked.
			$this->assertTrue(
				Moderation::is_actor_blocked( $actor_url, $this->user_id ),
				'Actor should be blocked.'
			);
		} finally {
			\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
		}
	}

	/**
	 * Test that handle_block returns data for empty object.
	 *
	 * @covers ::handle_block
	 */
	public function test_handle_block_empty_object() {
		$data = array(
			'type'   => 'Block',
			'object' => '',
		);

		$result = Block::handle_block( $data, $this->user_id );

		$this->assertSame( $data, $result, 'Should return original data for empty object.' );
	}

	/**
	 * Test that handle_block sets private addressing.
	 *
	 * @covers ::handle_block
	 */
	public function test_handle_block_private_addressing() {
		$actor_url = 'https://example.com/users/blockme';

		$fake_response = array(
			'type'              => 'Person',
			'id'                => $actor_url,
			'name'              => 'Block Me',
			'preferredUsername' => 'blockme',
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

		try {
			$data = array(
				'type'   => 'Block',
				'object' => $actor_url,
				'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
				'cc'     => array( 'https://example.com/followers' ),
			);

			$result = Block::handle_block( $data, $this->user_id );

			// Verify the outbox item has private visibility.
			$this->assertIsInt( $result );
			$visibility = \get_post_meta( $result, 'activitypub_content_visibility', true );
			$this->assertEquals( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE, $visibility );
		} finally {
			\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );
		}
	}

	/**
	 * Test that init registers the filter.
	 *
	 * @covers ::init
	 */
	public function test_init_registers_filter() {
		Block::init();

		$this->assertNotFalse(
			\has_filter( 'activitypub_outbox_block', array( Block::class, 'handle_block' ) ),
			'Filter should be registered.'
		);
	}
}
