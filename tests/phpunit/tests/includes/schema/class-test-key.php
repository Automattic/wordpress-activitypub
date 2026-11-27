<?php
/**
 * Test file for the Key schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Key;

/**
 * Test class for the Key schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Key
 */
class Test_Key extends \WP_UnitTestCase {

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
		$this->assertSame( $expected, ! \is_wp_error( ( new Key() )->validate( $data ) ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$key = array(
			'id'           => 'https://example.com/actor#main-key',
			'owner'        => 'https://example.com/actor',
			'publicKeyPem' => '-----BEGIN PUBLIC KEY-----',
		);

		return array(
			'key'             => array( $key, true ),
			'with type'       => array( \array_merge( $key, array( 'type' => 'CryptographicKey' ) ), true ),
			'without id'      => array( \array_diff_key( $key, array( 'id' => true ) ), false ),
			'without pem'     => array( \array_diff_key( $key, array( 'publicKeyPem' => true ) ), false ),
			'empty pem'       => array( \array_merge( $key, array( 'publicKeyPem' => '' ) ), false ),
			'owner not a uri' => array( \array_merge( $key, array( 'owner' => 'actor' ) ), false ),
		);
	}
}
