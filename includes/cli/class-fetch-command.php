<?php
/**
 * Fetch CLI Command.
 *
 * @package Activitypub
 */

namespace Activitypub\Cli;

use Activitypub\Http;
use Activitypub\Signature;

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
	 * : Signature mode: draft-cavage, rfc9421, or none.
	 * ---
	 * default: default
	 * options:
	 *   - default
	 *   - draft-cavage
	 *   - rfc9421
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
			\WP_CLI::error( \sprintf( 'Request failed: %s (HTTP %s).', $response->get_error_message(), $response->get_error_code() ) );
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

			if ( null !== $data ) {
				\WP_CLI::log( \wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			} else {
				\WP_CLI::log( $body );
			}
		}
	}

	/**
	 * Apply signature mode overrides via option filters.
	 *
	 * @param string $mode The signature mode.
	 * @param array  $args The request arguments, passed by reference.
	 *
	 * @return callable Cleanup callback to remove added filters.
	 */
	private function apply_signature_mode( $mode, &$args ) {
		$filters = array();
		$restore = array();

		switch ( $mode ) {
			case 'none':
				$args['key_id']      = null;
				$args['private_key'] = null;
				break;

			case 'rfc9421':
				$force_rfc9421      = function () {
					return '1';
				};
				$bypass_unsupported = function () {
					return array();
				};

				\add_filter( 'pre_option_activitypub_rfc9421_signature', $force_rfc9421 );
				\add_filter( 'pre_option_activitypub_rfc9421_unsupported', $bypass_unsupported );
				\remove_filter( 'http_response', array( Signature::class, 'maybe_double_knock' ), 10 );

				$filters[] = array( 'pre_option_activitypub_rfc9421_signature', $force_rfc9421 );
				$filters[] = array( 'pre_option_activitypub_rfc9421_unsupported', $bypass_unsupported );
				$restore[] = array( 'http_response', array( Signature::class, 'maybe_double_knock' ), 10, 3 );
				break;

			case 'draft-cavage':
				$force_cavage = function () {
					return '0';
				};

				\add_filter( 'pre_option_activitypub_rfc9421_signature', $force_cavage );

				$filters[] = array( 'pre_option_activitypub_rfc9421_signature', $force_cavage );
				break;
		}

		return function () use ( $filters, $restore ) {
			foreach ( $filters as $filter ) {
				\remove_filter( $filter[0], $filter[1] );
			}
			foreach ( $restore as $filter ) {
				\add_filter( $filter[0], $filter[1], $filter[2], $filter[3] );
			}
		};
	}
}
