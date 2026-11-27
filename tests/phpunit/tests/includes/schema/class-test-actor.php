<?php
/**
 * Test file for the Actor schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Actor;

/**
 * Test class for the Actor schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Actor
 */
class Test_Actor extends \WP_UnitTestCase {

	/**
	 * Test validate.
	 *
	 * @dataProvider validate_provider
	 * @covers ::validate
	 * @covers ::get_schema
	 *
	 * @param array $data     The remote data.
	 * @param bool  $expected Whether it is valid.
	 */
	public function test_validate( $data, $expected ) {
		$this->assertSame( $expected, ! \is_wp_error( ( new Actor( $data ) )->validate() ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$actor = array(
			'id'        => 'https://example.com/actor',
			'type'      => 'Person',
			'inbox'     => 'https://example.com/actor/inbox',
			'publicKey' => array(
				'id'           => 'https://example.com/actor#main-key',
				'owner'        => 'https://example.com/actor',
				'publicKeyPem' => '-----BEGIN PUBLIC KEY-----',
			),
		);

		return array(
			'actor'              => array( $actor, true ),
			'without id'         => array( \array_diff_key( $actor, array( 'id' => true ) ), false ),
			'without inbox'      => array( \array_diff_key( $actor, array( 'inbox' => true ) ), false ),
			'inbox not a uri'    => array( \array_merge( $actor, array( 'inbox' => 'inbox' ) ), false ),
			'shared inbox'       => array( \array_merge( $actor, array( 'endpoints' => array( 'sharedInbox' => 'https://example.com/inbox' ) ) ), true ),
			'key without pem'    => array( \array_merge( $actor, array( 'publicKey' => array( 'id' => 'https://example.com/actor#main-key' ) ) ), false ),
			'without public key' => array( \array_diff_key( $actor, array( 'publicKey' => true ) ), true ),
			'public key as uri'  => array( \array_merge( $actor, array( 'publicKey' => 'https://example.com/actor/key' ) ), true ),
			'public key no uri'  => array( \array_merge( $actor, array( 'publicKey' => 'key' ) ), false ),
			'summary null'       => array( \array_merge( $actor, array( 'summary' => null ) ), true ),
			'name not a string'  => array( \array_merge( $actor, array( 'name' => array( 'Alice' ) ) ), false ),
		);
	}
}
