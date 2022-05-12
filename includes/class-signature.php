// SPDX-FileCopyrightText: 2022 Matthias Pfefferle <matthias@pfefferle.org>
//
// SPDX-License-Identifier: MIT

<?php
namespace Activitypub;

/**
 * ActivityPub Signature Class
 *
 * @author Matthias Pfefferle
 */
class Signature {

	/**
	 * @param int $user_id
	 *
	 * @return mixed
	 */
	public static function get_public_key( $user_id, $force = false ) {
		$key = \get_user_meta( $user_id, 'magic_sig_public_key' );

		if ( $key && ! $force ) {
			return $key[0];
		}

		self::generate_key_pair( $user_id );
		$key = \get_user_meta( $user_id, 'magic_sig_public_key' );

		return $key[0];
	}

	/**
	 * @param int $user_id
	 *
	 * @return mixed
	 */
	public static function get_private_key( $user_id, $force = false ) {
		$key = \get_user_meta( $user_id, 'magic_sig_private_key' );

		if ( $key && ! $force ) {
			return $key[0];
		}

		self::generate_key_pair( $user_id );
		$key = \get_user_meta( $user_id, 'magic_sig_private_key' );

		return $key[0];
	}

	/**
	 * Generates the pair keys
	 *
	 * @param int $user_id
	 */
	public static function generate_key_pair( $user_id ) {
		$config = array(
			'digest_alg' => 'sha512',
			'private_key_bits' => 2048,
			'private_key_type' => \OPENSSL_KEYTYPE_RSA,
		);

		$key = \openssl_pkey_new( $config );
		$priv_key = null;

		\openssl_pkey_export( $key, $priv_key );

		// private key
		\update_user_meta( $user_id, 'magic_sig_private_key', $priv_key );

		$detail = \openssl_pkey_get_details( $key );

		// public key
		\update_user_meta( $user_id, 'magic_sig_public_key', $detail['key'] );
	}

	public static function generate_signature( $user_id, $url, $date, $digest = null ) {
		$key = self::get_private_key( $user_id );

		$url_parts = \wp_parse_url( $url );

		$host = $url_parts['host'];
		$path = '/';

		// add path
		if ( ! empty( $url_parts['path'] ) ) {
			$path = $url_parts['path'];
		}

		// add query
		if ( ! empty( $url_parts['query'] ) ) {
			$path .= '?' . $url_parts['query'];
		}

		if ( ! empty( $digest ) ) {
			$signed_string = "(request-target): post $path\nhost: $host\ndate: $date\ndigest: SHA-256=$digest";
		} else {
			$signed_string = "(request-target): post $path\nhost: $host\ndate: $date";
		}

		$signature = null;
		\openssl_sign( $signed_string, $signature, $key, \OPENSSL_ALGO_SHA256 );
		$signature = \base64_encode( $signature ); // phpcs:ignore

		$key_id = \get_author_posts_url( $user_id ) . '#main-key';

		if ( ! empty( $digest ) ) {
			return \sprintf( 'keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date digest",signature="%s"', $key_id, $signature );
		} else {
			return \sprintf( 'keyId="%s",algorithm="rsa-sha256",headers="(request-target) host date",signature="%s"', $key_id, $signature );
		}
	}

	public static function verify_signature( $headers, $signature ) {

	}

	public static function generate_digest( $body ) {
		$digest = \base64_encode( \hash( 'sha256', $body, true ) ); // phpcs:ignore
		return "$digest";
	}
}
