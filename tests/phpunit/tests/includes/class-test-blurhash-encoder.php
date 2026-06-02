<?php
/**
 * Test the Blurhash encoder.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Blurhash_Encoder;

/**
 * Test class for Blurhash_Encoder.
 *
 * @coversDefaultClass \Activitypub\Blurhash_Encoder
 */
class Test_Blurhash_Encoder extends \WP_UnitTestCase {

	/**
	 * Build a solid-color WxH pixel array.
	 *
	 * @param int $r Red.
	 * @param int $g Green.
	 * @param int $b Blue.
	 * @param int $w Width.
	 * @param int $h Height.
	 * @return array
	 */
	private function solid( $r, $g, $b, $w = 8, $h = 8 ) {
		$rows = array();
		for ( $y = 0; $y < $h; $y++ ) {
			$row = array();
			for ( $x = 0; $x < $w; $x++ ) {
				$row[] = array( $r, $g, $b );
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Decode a base83 substring back to an integer.
	 *
	 * @param string $str  Hash.
	 * @param int    $from Start offset.
	 * @param int    $len  Length.
	 * @return int
	 */
	private function decode83( $str, $from, $len ) {
		$alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz#$%*+,-.:;=?@[]^_{|}~';
		$value    = 0;
		for ( $i = $from; $i < $from + $len; $i++ ) {
			$value = $value * 83 + strpos( $alphabet, $str[ $i ] );
		}
		return $value;
	}

	/**
	 * Test that hash length matches component counts.
	 *
	 * @covers ::encode
	 */
	public function test_hash_length_matches_components() {
		$hash = Blurhash_Encoder::encode( $this->solid( 10, 20, 30 ), 4, 3 );
		$this->assertSame( 1 + 1 + 4 + 2 * ( 4 * 3 - 1 ), strlen( $hash ) );
	}

	/**
	 * Test that hash uses only valid base83 alphabet characters.
	 *
	 * @covers ::encode
	 */
	public function test_hash_uses_base83_alphabet_only() {
		$hash = Blurhash_Encoder::encode( $this->solid( 123, 45, 67 ), 4, 4 );
		$this->assertSame( 1, preg_match( '/\A[0-9A-Za-z#$%*+,\-.:;=?@\[\]\^_{|}~]+\z/', $hash ) );
	}

	/**
	 * Test that the size flag correctly encodes the component counts.
	 *
	 * @covers ::encode
	 */
	public function test_size_flag_encodes_components() {
		$hash = Blurhash_Encoder::encode( $this->solid( 0, 0, 0 ), 4, 3 );
		$this->assertSame( ( 4 - 1 ) + ( 3 - 1 ) * 9, $this->decode83( $hash, 0, 1 ) );
	}

	/**
	 * Test that the DC component round-trips to the average color.
	 *
	 * @covers ::encode
	 */
	public function test_dc_round_trips_to_average_color() {
		$hash = Blurhash_Encoder::encode( $this->solid( 200, 100, 50 ), 4, 4 );
		$dc   = $this->decode83( $hash, 2, 4 );
		$this->assertEqualsWithDelta( 200, ( $dc >> 16 ) & 0xFF, 2 );
		$this->assertEqualsWithDelta( 100, ( $dc >> 8 ) & 0xFF, 2 );
		$this->assertEqualsWithDelta( 50, $dc & 0xFF, 2 );
	}

	/**
	 * Test that invalid component counts return an empty string.
	 *
	 * @covers ::encode
	 */
	public function test_invalid_components_return_empty() {
		$this->assertSame( '', Blurhash_Encoder::encode( $this->solid( 1, 2, 3 ), 0, 4 ) );
		$this->assertSame( '', Blurhash_Encoder::encode( $this->solid( 1, 2, 3 ), 4, 10 ) );
	}

	/**
	 * Test that an empty pixel array returns an empty string.
	 *
	 * @covers ::encode
	 */
	public function test_empty_pixels_return_empty() {
		$this->assertSame( '', Blurhash_Encoder::encode( array(), 4, 4 ) );
	}
}
