<?php
/**
 * External delivery tests.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\External_Delivery;

/**
 * External delivery test case.
 *
 * @coversDefaultClass \Activitypub\External_Delivery
 */
class Test_External_Delivery extends \WP_UnitTestCase {
	/**
	 * Exact fixture spool rows.
	 *
	 * @var int[]
	 */
	private $spool_ids = array();

	/** Register the isolated transport surface. */
	public function set_up() {
		parent::set_up();
		External_Delivery::init();
	}

	/** Clean exact fixture rows and events. */
	public function tear_down() {
		foreach ( $this->spool_ids as $post_id ) {
			\wp_clear_scheduled_hook( External_Delivery::PROCESS_HOOK, array( $post_id, 1 ) );
			\wp_delete_post( $post_id, true );
		}
		parent::tear_down();
	}

	/** Reject secret material at the persistence boundary. */
	public function test_rejects_private_key_input() {
		$result = \Activitypub\deliver_activity(
			$this->payload(),
			\array_merge( $this->sender(), array( 'private_key' => 'secret' ) ),
			array( 'https://example.com/inbox' )
		);
		$this->assertWPError( $result );
		$this->assertSame( 'activitypub_external_delivery_private_key', $result->get_error_code() );
	}

	/** Persist one idempotent, transport-only row without key material. */
	public function test_enqueue_is_idempotent_and_non_secret() {
		$payload = $this->payload();
		$sender  = $this->sender();
		$first   = \Activitypub\deliver_activity( $payload, $sender, array( 'https://example.com/inbox' ) );
		$this->assertIsInt( $first );
		$this->spool_ids[] = $first;
		$this->assertSame( $first, \Activitypub\deliver_activity( $payload, $sender, array( 'https://example.com/inbox' ) ) );
		$this->assertStringContainsString( $payload['id'], \get_post( $first )->post_content );
		$this->assertStringNotContainsString( 'PRIVATE KEY', \wp_json_encode( \get_post_meta( $first ) ) );
		$this->assertSame( $sender['private_key_ref'], \get_post_meta( $first, '_activitypub_external_private_key_ref', true ) );
	}

	/** Complete Activity fixture. */
	private function payload() {
		return array(
			'id'     => 'https://local.example/activities/' . \wp_generate_uuid4(),
			'type'   => 'Follow',
			'actor'  => 'https://local.example/actors/alice',
			'object' => 'https://example.com/users/bob',
		);
	}

	/** Non-secret external sender descriptor. */
	private function sender() {
		return array(
			'actor_uri'       => 'https://local.example/actors/alice',
			'key_id'          => 'https://local.example/actors/alice#main-key',
			'private_key_ref' => 'fixture:alice',
		);
	}
}
