<?php
namespace Activitypub;

use phpseclib3\Crypt\RSA;

/**
 * ActivityPub Signature Class
 *
 * @author Matthias Pfefferle
 */
class Signature {

	public const SIGNATURE_PATTERN = '/^
        keyId="(?P<keyId>
            (https?:\/\/[\w\-\.]+[\w]+)
            (:[\d]+)?
            ([\w\-\.#\/@]+)
        )",
        (algorithm="(?P<algorithm>[\w\s-]+)",)?
        (headers="(?P<headers>[\(\)\w\s-]+)",)?
        signature="(?P<signature>[\w+\/]+={0,2})"
    /x';

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

	public static function verify_signature( $request ) {

		// https://github.com/landrok/activitypub/blob/master/src/ActivityPhp/Server/Http/HttpSignature.php
		$header_data = $request->get_headers(); 
		$body = $request->get_body();	
		if ( !$header_data['signature'][0] ) {
            return false;
        }
        
		// Split it into its parts ( keyId, headers and signature )
        $signature_parts = self::splitSignature( $header_data['signature'][0] );
        if ( !count($signature_parts ) ) {
            return false;
        }
        extract( $signature_parts );

        // Fetch the public key linked from keyId
        $actor = \strip_fragment_from_url( $keyId );
        $publicKeyPem =  \Activitypub\get_publickey_by_actor( $actor,  $keyId );
		
		if (! is_wp_error( $publicKeyPem ) ) {
			$pkey = \openssl_pkey_get_details( \openssl_pkey_get_public( $publicKeyPem ) );
			$digest_gen = 'SHA-256=' . \base64_encode( \hash( 'sha256', $body, true ) );
			if ( $digest_gen !== $header_data['digest'][0] ) {
				return false;
			}

			// Create a comparison string from the plaintext headers we got 
			// in the same order as was given in the signature header, 
			$data_plain = self::getPlainText(
				explode(' ', trim( $headers ) ), 
				$request
			);

			// Verify that string using the public key and the original 
			// signature.
			$rsa = RSA::createKey()
					->loadPublicKey( $pkey['key'])
					->withHash('sha256'); 

			$verified = $rsa->verify( $data_plain, \base64_decode( $signature ) );

			if ( '1' === $verified ) {
				return true;
			} else {
				return false;
			}		
		}
		return true;
	}

	/**
     * Split HTTP signature into its parts (keyId, headers and signature)
     */
    public static function splitSignature( $signature ) {
	
		$allowedKeys = [
			'keyId',
			'algorithm', // optional
			'headers',   // optional
			'signature',
		];

        if (!preg_match(self::SIGNATURE_PATTERN, $signature, $matches)) {
            return [];
        }

        // Headers are optional
        if (!isset($matches['headers']) || $matches['headers'] == '') {
            $matches['headers'] = 'date';
        }

        return array_filter($matches, function($key) use ($allowedKeys) {
                return !is_int($key) && in_array($key, $allowedKeys);
        },  ARRAY_FILTER_USE_KEY );        
    }

	/**
     * Get plain text that has been originally signed
     * 
     * @param  array $headers HTTP header keys
     * @param  \Symfony\Component\HttpFoundation\Request $request 
     */
    public static function getPlainText( $headers, $request ) {

		$url_params = $request->get_url_params();
		if ( isset( $url_params ) && isset( $url_params['user_id'] ) ) {
			$url_params = '';
		}

        $strings = [];
        $request_target = sprintf(
            '%s %s%s',
            strtolower($request->get_method()),
            $request->get_route(),
            $url_params
        );
		 
		 foreach ($headers as $value) {
			 if ( $value == '(request-target)' ) {
				 $strings[] = "$value: " . $request_target;
			} else {
				$strings[] = "$value: " . $request->get_header($value);
			}
		}

        return implode("\n", $strings);   
	}

	public static function generate_digest( $body ) {
		$digest = \base64_encode( \hash( 'sha256', $body, true ) ); // phpcs:ignore
		return "$digest";
	}
}
