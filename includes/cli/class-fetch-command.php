<?php
/**
 * Fetch CLI Command.
 *
 * @package Activitypub
 */

namespace Activitypub\Cli;

use Activitypub\Http;
use Activitypub\Signature;
use Activitypub\Signature\Http_Message_Signature;

/**
 * Fetch a remote ActivityPub URL with signed HTTP requests.
 *
 * Useful for debugging HTTP Signatures and federation issues.
 * Signs requests as the application actor by default.
 *
 * @package Activitypub
 */
class Fetch_Command extends \WP_CLI_Command {

	/**
	 * Fetch a remote ActivityPub URL with a signed HTTP request.
	 *
	 * Signs the request as the application actor and displays the response.
	 * Supports switching between signature modes for debugging.
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : The URL to fetch.
	 *
	 * [--signature=<mode>]
	 * : Signature mode: default (plugin-configured), draft-cavage, rfc9421, double-knock, or none.
	 * ---
	 * default: default
	 * options:
	 *   - default
	 *   - draft-cavage
	 *   - rfc9421
	 *   - double-knock
	 *   - none
	 * ---
	 *
	 * [--raw]
	 * : Output the raw response body without formatting.
	 *
	 * [--include-headers]
	 * : Show response headers alongside the body.
	 *
	 * ## EXAMPLES
	 *
	 *     # Fetch an actor profile with default signature
	 *     $ wp activitypub fetch https://mastodon.social/@Gargron
	 *
	 *     # Fetch with RFC 9421 signature
	 *     $ wp activitypub fetch https://mastodon.social/@Gargron --signature=rfc9421
	 *
	 *     # Fetch with Draft Cavage signature
	 *     $ wp activitypub fetch https://mastodon.social/@Gargron --signature=draft-cavage
	 *
	 *     # Fetch with double-knock (RFC 9421 first, Draft Cavage fallback on 4xx)
	 *     $ wp activitypub fetch https://mastodon.social/@Gargron --signature=double-knock
	 *
	 *     # Fetch without signature
	 *     $ wp activitypub fetch https://mastodon.social/@Gargron --signature=none
	 *
	 *     # Show response headers
	 *     $ wp activitypub fetch https://mastodon.social/@Gargron --include-headers
	 *
	 *     # Output raw response body
	 *     $ wp activitypub fetch https://mastodon.social/@Gargron --raw
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$url             = $args[0];
		$signature_mode  = \WP_CLI\Utils\get_flag_value( $assoc_args, 'signature', 'default' );
		$raw             = \WP_CLI\Utils\get_flag_value( $assoc_args, 'raw', false );
		$include_headers = \WP_CLI\Utils\get_flag_value( $assoc_args, 'include-headers', false );

		\WP_CLI::log( \sprintf( 'Fetching: %s', $url ) );
		\WP_CLI::log( \sprintf( 'Signature mode: %s', $signature_mode ) );

		$get_args = array();
		$cleanup  = $this->apply_signature_mode( $signature_mode, $get_args );
		$response = Http::get( $url, $get_args, false );

		$cleanup();

		if ( \is_wp_error( $response ) ) {
			\WP_CLI::error( \sprintf( 'Request failed: %s (Error code: %s).', $response->get_error_message(), $response->get_error_code() ) );
		}

		$code = \wp_remote_retrieve_response_code( $response );

		\WP_CLI::log( \sprintf( 'Response code: %d', $code ) );
		\WP_CLI::log( '' );

		// Show response headers if requested.
		if ( $include_headers ) {
			$headers = \wp_remote_retrieve_headers( $response );

			\WP_CLI::log( '--- Response Headers ---' );

			foreach ( $headers as $name => $value ) {
				\WP_CLI::log( \sprintf( '%s: %s', $name, $value ) );
			}

			\WP_CLI::log( '' );
		}

		$body = \wp_remote_retrieve_body( $response );

		// Output the body.
		if ( $raw ) {
			\WP_CLI::log( $body );
		} else {
			$data = \json_decode( $body, true );

			if ( \JSON_ERROR_NONE === \json_last_error() ) {
				\WP_CLI::log( \wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			} else {
				\WP_CLI::log( $body );
			}
		}
	}

	/**
	 * Apply signature mode overrides via filters.
	 *
	 * For rfc9421, replaces the default sign_request and disables double-knock
	 * to avoid an infinite retry loop when the server returns 4xx.
	 *
	 * @param string $mode The signature mode.
	 * @param array  $args The request arguments, passed by reference.
	 *
	 * @return callable Cleanup callback to restore original filters.
	 */
	private function apply_signature_mode( $mode, &$args ) {
		$filters = array();
		$restore = array();

		switch ( $mode ) {
			case 'default':
				break;

			case 'none':
				$args['key_id']      = null;
				$args['private_key'] = null;
				break;

			case 'rfc9421':
			case 'double-knock':
				// Replace default signing to force RFC 9421. For rfc9421 mode,
				// also disable double-knock to prevent an infinite retry loop.
				// For double-knock mode, keep it active but skip re-signing on retry.
				$removed_sign_request = \remove_filter( 'http_request_args', array( Signature::class, 'sign_request' ), 0 );

				$is_double_knock      = 'double-knock' === $mode;
				$removed_double_knock = false;

				if ( ! $is_double_knock ) {
					$removed_double_knock = \remove_filter( 'http_response', array( Signature::class, 'maybe_double_knock' ), 10 );
				}

				$forced_signer = function ( $request_args, $url ) use ( $is_double_knock ) {
					if ( ! isset( $request_args['key_id'], $request_args['private_key'] ) ) {
						return $request_args;
					}
					// In double-knock mode, skip if already signed (retry from maybe_double_knock).
					if ( $is_double_knock && ! empty( $request_args['headers']['Signature'] ) ) {
						return $request_args;
					}
					return ( new Http_Message_Signature() )->sign( $request_args, $url );
				};
				\add_filter( 'http_request_args', $forced_signer, 0, 2 );

				$filters[] = array( 'http_request_args', $forced_signer, 0 );

				if ( $removed_sign_request ) {
					$restore[] = array( 'http_request_args', array( Signature::class, 'sign_request' ), 0, 2 );
				}

				if ( $removed_double_knock ) {
					$restore[] = array( 'http_response', array( Signature::class, 'maybe_double_knock' ), 10, 3 );
				}
				break;

			case 'draft-cavage':
				$force_cavage = function () {
					return '0';
				};

				\add_filter( 'pre_option_activitypub_rfc9421_signature', $force_cavage );

				$filters[] = array( 'pre_option_activitypub_rfc9421_signature', $force_cavage );
				break;

			default:
				\WP_CLI::error(
					\sprintf(
						'Invalid signature mode "%s". Allowed modes: default, draft-cavage, rfc9421, double-knock, none.',
						$mode
					)
				);
		}

		return function () use ( $filters, $restore ) {
			foreach ( $filters as $filter ) {
				\remove_filter( $filter[0], $filter[1], $filter[2] ?? 10 );
			}
			foreach ( $restore as $filter ) {
				\add_filter( $filter[0], $filter[1], $filter[2], $filter[3] );
			}
		};
	}
}
