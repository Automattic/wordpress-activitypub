<?php
/**
 * Blurhash encoder.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Encodes pixel data into a Blurhash placeholder string.
 *
 * A first-party port of the public Blurhash encode algorithm
 * (https://github.com/woltapp/blurhash, MIT). Adapted from
 * Automattic/FOSSE (https://github.com/Automattic/fosse), which used the
 * kornrunner/blurhash library; this replaces that runtime dependency so
 * the plugin ships Blurhash support out of the box.
 *
 * @since unreleased
 */
class Blurhash_Encoder {

	/**
	 * Base83 alphabet defined by the Blurhash spec.
	 *
	 * @var string
	 */
	const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz#$%*+,-.:;=?@[]^_{|}~';

	/**
	 * Encode a pixel array into a Blurhash string.
	 *
	 * @param array $pixels       Row-major `[r,g,b][][]` array (0-255 per channel), indexed `[$y][$x]`.
	 * @param int   $components_x Horizontal component count (1-9).
	 * @param int   $components_y Vertical component count (1-9).
	 * @return string Blurhash string, or '' on invalid input.
	 */
	public static function encode( $pixels, $components_x, $components_y ) {
		$components_x = (int) $components_x;
		$components_y = (int) $components_y;

		if ( $components_x < 1 || $components_x > 9 || $components_y < 1 || $components_y > 9 ) {
			return '';
		}

		$height = \count( $pixels );
		if ( $height < 1 || ! isset( $pixels[0] ) || ! \is_array( $pixels[0] ) ) {
			return '';
		}
		$width = \count( $pixels[0] );
		if ( $width < 1 ) {
			return '';
		}

		$factors = array();
		for ( $y = 0; $y < $components_y; $y++ ) {
			for ( $x = 0; $x < $components_x; $x++ ) {
				$normalisation = ( 0 === $x && 0 === $y ) ? 1.0 : 2.0;
				$r             = 0.0;
				$g             = 0.0;
				$b             = 0.0;
				for ( $i = 0; $i < $width; $i++ ) {
					for ( $j = 0; $j < $height; $j++ ) {
						$basis = $normalisation
							* \cos( \pi() * $x * $i / $width )
							* \cos( \pi() * $y * $j / $height );
						$pixel = $pixels[ $j ][ $i ];
						$r    += $basis * self::srgb_to_linear( (int) $pixel[0] );
						$g    += $basis * self::srgb_to_linear( (int) $pixel[1] );
						$b    += $basis * self::srgb_to_linear( (int) $pixel[2] );
					}
				}
				$scale     = 1.0 / ( $width * $height );
				$factors[] = array( $r * $scale, $g * $scale, $b * $scale );
			}
		}

		$dc = $factors[0];
		$ac = \array_slice( $factors, 1 );

		$hash      = self::encode83( ( $components_x - 1 ) + ( $components_y - 1 ) * 9, 1 );
		$max_value = 1.0;

		if ( \count( $ac ) > 0 ) {
			$actual_max = 0.0;
			foreach ( $ac as $factor ) {
				$actual_max = \max( $actual_max, \abs( $factor[0] ), \abs( $factor[1] ), \abs( $factor[2] ) );
			}
			$quantised_max = (int) \max( 0, \min( 82, \floor( $actual_max * 166 - 0.5 ) ) );
			$max_value     = ( $quantised_max + 1 ) / 166;
			$hash         .= self::encode83( $quantised_max, 1 );
		} else {
			$hash .= self::encode83( 0, 1 );
		}

		$hash .= self::encode83( self::encode_dc( $dc ), 4 );
		foreach ( $ac as $factor ) {
			$hash .= self::encode83( self::encode_ac( $factor, $max_value ), 2 );
		}

		return $hash;
	}

	/**
	 * Encode an integer to a fixed-length base83 string.
	 *
	 * @param int $value  Value.
	 * @param int $length Output length.
	 * @return string
	 */
	private static function encode83( $value, $length ) {
		$value  = (int) $value;
		$result = '';
		for ( $i = 1; $i <= $length; $i++ ) {
			$digit   = (int) ( $value / ( 83 ** ( $length - $i ) ) ) % 83;
			$result .= self::ALPHABET[ $digit ];
		}
		return $result;
	}

	/**
	 * Convert an sRGB 0-255 channel to linear 0-1.
	 *
	 * @param int $value Channel value.
	 * @return float
	 */
	private static function srgb_to_linear( $value ) {
		$v = $value / 255.0;
		if ( $v <= 0.04045 ) {
			return $v / 12.92;
		}
		return \pow( ( $v + 0.055 ) / 1.055, 2.4 );
	}

	/**
	 * Convert a linear 0-1 channel to sRGB 0-255.
	 *
	 * @param float $value Linear value.
	 * @return int
	 */
	private static function linear_to_srgb( $value ) {
		$v = \max( 0.0, \min( 1.0, $value ) );
		if ( $v <= 0.0031308 ) {
			return (int) ( $v * 12.92 * 255 + 0.5 );
		}
		return (int) ( ( 1.055 * \pow( $v, 1 / 2.4 ) - 0.055 ) * 255 + 0.5 );
	}

	/**
	 * Encode the DC (average color) factor.
	 *
	 * @param array $factor `[r,g,b]` linear floats.
	 * @return int
	 */
	private static function encode_dc( $factor ) {
		$r = self::linear_to_srgb( $factor[0] );
		$g = self::linear_to_srgb( $factor[1] );
		$b = self::linear_to_srgb( $factor[2] );
		return ( $r << 16 ) + ( $g << 8 ) + $b;
	}

	/**
	 * Encode an AC factor against the maximum value.
	 *
	 * @param array $factor    `[r,g,b]` linear floats.
	 * @param float $max_value Quantisation maximum.
	 * @return int
	 */
	private static function encode_ac( $factor, $max_value ) {
		$quant_r = (int) \max( 0, \min( 18, \floor( self::sign_pow( $factor[0] / $max_value, 0.5 ) * 9 + 9.5 ) ) );
		$quant_g = (int) \max( 0, \min( 18, \floor( self::sign_pow( $factor[1] / $max_value, 0.5 ) * 9 + 9.5 ) ) );
		$quant_b = (int) \max( 0, \min( 18, \floor( self::sign_pow( $factor[2] / $max_value, 0.5 ) * 9 + 9.5 ) ) );
		return $quant_r * 19 * 19 + $quant_g * 19 + $quant_b;
	}

	/**
	 * Sign-preserving power.
	 *
	 * @param float $value Base.
	 * @param float $exp   Exponent.
	 * @return float
	 */
	private static function sign_pow( $value, $exp ) {
		$result = \pow( \abs( $value ), $exp );
		return $value < 0 ? -$result : $result;
	}
}
