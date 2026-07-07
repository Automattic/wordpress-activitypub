<?php
/**
 * FASP Client class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Fasp;

use Activitypub\Signature\Http_Message_Signature;

/**
 * FASP Client class.
 *
 * Makes signed requests to a registered Fediverse Auxiliary Service Provider
 * (FASP) and verifies its signed responses, per the FASP protocol basics:
 * requests are signed with RFC 9421 HTTP Message Signatures (Ed25519) over
 * `@method`, `@target-uri` and `content-digest`, responses over `@status`
 * and `content-digest`.
 *
 * The site signs with the per-registration Ed25519 key under the `serverId`
 * the provider allocated for this site; provider responses verify against
 * the provider's public key exchanged at registration.
 *
 * @see https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/blob/main/general/v0.1/protocol_basics.md
 *
 * @since unreleased
 */
class Client {

	/**
	 * Fetch and verify the provider info of a FASP.
	 *
	 * This makes a blocking signed request, so callers must run it in a
	 * request context that can afford to wait (e.g. the approve and refresh
	 * admin-post handlers), never during a page render. The result is
	 * persisted into the registration record by the caller so render paths
	 * can read it without fetching.
	 *
	 * @see https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/blob/main/general/v0.1/provider_info.md
	 *
	 * @param array $registration The registration record.
	 * @return array|\WP_Error The decoded provider info, or WP_Error on failure.
	 */
	public static function fetch_provider_info( $registration ) {
		$response = self::request( $registration, 'GET', '/provider_info' );
		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== \wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error(
				'fasp_provider_info_failed',
				\__( 'The auxiliary service did not return its provider information.', 'activitypub' )
			);
		}

		$provider_info = \json_decode( \wp_remote_retrieve_body( $response ), true );
		if ( ! \is_array( $provider_info ) || empty( $provider_info['capabilities'] ) || ! \is_array( $provider_info['capabilities'] ) ) {
			return new \WP_Error(
				'fasp_provider_info_invalid',
				\__( 'The auxiliary service returned invalid provider information.', 'activitypub' )
			);
		}

		return $provider_info;
	}

	/**
	 * Notify a FASP that a capability has been enabled.
	 *
	 * @param array  $registration The registration record.
	 * @param string $identifier   The capability identifier.
	 * @param string $version      The capability version.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function activate_capability( $registration, $identifier, $version ) {
		return self::toggle_capability( $registration, $identifier, $version, 'POST' );
	}

	/**
	 * Notify a FASP that a capability has been disabled.
	 *
	 * @param array  $registration The registration record.
	 * @param string $identifier   The capability identifier.
	 * @param string $version      The capability version.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function deactivate_capability( $registration, $identifier, $version ) {
		return self::toggle_capability( $registration, $identifier, $version, 'DELETE' );
	}

	/**
	 * Call the capability activation endpoint of a FASP.
	 *
	 * @param array  $registration The registration record.
	 * @param string $identifier   The capability identifier.
	 * @param string $version      The capability version.
	 * @param string $method       The HTTP method: 'POST' to activate, 'DELETE' to deactivate.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	private static function toggle_capability( $registration, $identifier, $version, $method ) {
		$path = \sprintf( '/capabilities/%s/%s/activation', \rawurlencode( $identifier ), \rawurlencode( $version ) );

		$response = self::request( $registration, $method, $path );
		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		if ( 204 !== \wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error(
				'fasp_capability_toggle_failed',
				\__( 'The auxiliary service did not acknowledge the capability change.', 'activitypub' )
			);
		}

		return true;
	}

	/**
	 * Make a signed request to a FASP and verify the signed response.
	 *
	 * @param array       $registration The registration record.
	 * @param string      $method       The HTTP method.
	 * @param string      $path         The path, relative to the provider's base URL.
	 * @param string|null $body         Optional. The request body. Default null.
	 * @return array|\WP_Error The HTTP response array on success, WP_Error on failure.
	 */
	private static function request( $registration, $method, $path, $body = null ) {
		$url = \untrailingslashit( $registration['base_url'] ) . $path;

		$private_key = \base64_decode( $registration['server_private_key'] ?? '' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( ! $private_key || \strlen( $private_key ) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ) {
			return new \WP_Error(
				'fasp_missing_private_key',
				\__( 'No valid signing key is stored for this auxiliary service.', 'activitypub' )
			);
		}

		$args = array(
			'method'  => $method,
			'timeout' => 10,
			'headers' => array(
				'Accept' => 'application/json',
			),
		);

		if ( null !== $body ) {
			$args['body']                    = $body;
			$args['headers']['Content-Type'] = 'application/json';
		}

		$signature = new Http_Message_Signature();
		$args      = $signature->sign_request_ed25519( $args, $url, $private_key, $registration['server_id'] );

		$response = \wp_safe_remote_request( $url, $args );
		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		$verification = self::verify_response( $registration, $response );
		if ( \is_wp_error( $verification ) ) {
			return $verification;
		}

		return $response;
	}

	/**
	 * Verify the signature of a FASP response.
	 *
	 * The FASP spec requires all responses to be signed with the provider's
	 * Ed25519 key over `@status` and `content-digest`.
	 *
	 * @param array $registration The registration record.
	 * @param array $response     The HTTP response array.
	 * @return true|\WP_Error True if the response signature is valid, WP_Error otherwise.
	 */
	private static function verify_response( $registration, $response ) {
		$public_key = \base64_decode( $registration['fasp_public_key'] ?? '' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( ! $public_key ) {
			return new \WP_Error(
				'fasp_missing_public_key',
				\__( 'No public key is stored for this auxiliary service.', 'activitypub' )
			);
		}

		$headers = \wp_remote_retrieve_headers( $response );
		if ( \is_object( $headers ) ) {
			$headers = $headers->getAll();
		}

		$signature = new Http_Message_Signature();
		$verified  = $signature->verify_response(
			\wp_remote_retrieve_response_code( $response ),
			$headers,
			\wp_remote_retrieve_body( $response ),
			$public_key
		);

		if ( \is_wp_error( $verified ) ) {
			return $verified;
		}

		return true;
	}
}
