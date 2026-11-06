<?php
/**
 * Rate limit trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use function Activitypub\get_client_ip;
use function Activitypub\maybe_set_no_store;

/**
 * How often one caller may ask.
 *
 * Controllers use this trait for permission callbacks: an endpoint states its allowance where its
 * route is registered, and the trait counts the caller and reports the allowance in the response
 * headers, whether the request fit in it or not. The headers are added here rather than by the
 * endpoint because a permission callback can return no more than a `WP_Error`, which carries none.
 *
 * A signed-in caller is counted per account and everyone else per IP address. A caller that cannot
 * be identified at all is refused: without a key there is nothing to count, so letting it through
 * would be the same as having no limit.
 *
 * @since 9.4.0
 */
trait Rate_Limit {
	/**
	 * What each request was already told, so it is counted once, keyed by request.
	 *
	 * The request is kept beside the answer because `spl_object_id()` hands out the id of a freed
	 * object again; without a reference a later request could read this answer as its own. The
	 * numbers are kept so the response can carry them once it exists.
	 *
	 * @var array<string, array{request: \WP_REST_Request, answer: true|\WP_Error, limit: int, remaining: int, reset: int}>
	 */
	private static $counted = array();

	/**
	 * Count a request against its caller's allowance.
	 *
	 * WordPress may ask a permission callback more than once for the same request: core's
	 * `rest_send_allow_header()` calls it again after dispatch to work out the `Allow` header. The
	 * answer is therefore remembered per request object, so one request spends one unit however
	 * often it is asked, and forgotten once its response has been stamped.
	 *
	 * @since 9.4.0
	 *
	 * @param string           $bucket  What is being limited, for example `interactions`.
	 * @param int              $limit   How many requests a caller may make per minute.
	 * @param \WP_REST_Request $request The request being counted.
	 *
	 * @return true|\WP_Error True when the request fits in the allowance, WP_Error otherwise.
	 */
	protected function rate_limit( $bucket, $limit, $request ) {
		$memo = $bucket . ':' . \spl_object_id( $request );

		if ( isset( self::$counted[ $memo ] ) ) {
			return self::$counted[ $memo ]['answer'];
		}

		/*
		 * One callback per class puts the numbers on the responses. It is added on first use, so a
		 * process that never counts anything never runs it, and only once, so it never accumulates.
		 */
		if ( ! \has_filter( 'rest_post_dispatch', array( self::class, 'send_rate_limit_headers' ) ) ) {
			\add_filter( 'rest_post_dispatch', array( self::class, 'send_rate_limit_headers' ), 10, 3 );
		}

		self::$counted[ $memo ]            = $this->count_request( $bucket, $limit );
		self::$counted[ $memo ]['request'] = $request;

		return self::$counted[ $memo ]['answer'];
	}

	/**
	 * Count one request against its caller's allowance.
	 *
	 * @param string $bucket What is being limited.
	 * @param int    $limit  How many requests a caller may make per minute.
	 *
	 * @return array{answer: true|\WP_Error, limit: int, remaining: int, reset: int} The answer and the numbers behind it.
	 */
	private function count_request( $bucket, $limit ) {
		/**
		 * Filters how many requests a caller may make per minute.
		 *
		 * @since 9.4.0
		 *
		 * @param int    $limit  The allowance the endpoint asks for.
		 * @param string $bucket What is being limited, for example `interactions`.
		 */
		$limit = (int) \apply_filters( 'activitypub_rate_limit', $limit, $bucket );

		$user_id = \get_current_user_id();
		$caller  = $user_id ? 'user-' . $user_id : get_client_ip();

		/*
		 * The window is part of the entry's name, so it ends by itself instead of being pushed
		 * forward by every request, and only the first request of a window writes a new entry.
		 */
		$window = (int) \floor( \time() / MINUTE_IN_SECONDS );
		$reset  = ( $window + 1 ) * MINUTE_IN_SECONDS;

		// The caller is hashed, so no IP address ends up in an option name, a backup or a debug dump.
		$key   = \sprintf( 'activitypub_rate_%s_%s_%d', $bucket, \md5( $caller ), $window );
		$count = (int) \get_transient( $key );

		$entry = array(
			'answer'    => true,
			'limit'     => $limit,
			'remaining' => 0,
			'reset'     => $reset,
		);

		// Without a caller there is nothing to count, so the request is refused rather than let through.
		if ( '' === $caller || $count >= $limit ) {
			$entry['answer'] = new \WP_Error(
				'activitypub_rate_limited',
				\__( 'Too many requests. Please try again later.', 'activitypub' ),
				array( 'status' => 429 )
			);

			return $entry;
		}

		\set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		$entry['remaining'] = $limit - $count - 1;

		return $entry;
	}

	/**
	 * Put the allowance on the response to a counted request, and forget the request.
	 *
	 * Runs on `rest_post_dispatch` for every response the process sends and acts only on the ones
	 * whose request was counted. The header names follow the IETF RateLimit header fields, which is
	 * what other Fediverse servers read. `Retry-After` is added to a refusal as well, per RFC 9110
	 * section 10.2.3, and the answer is kept out of shared caches, because the numbers describe one
	 * caller.
	 *
	 * The request is forgotten here and not earlier, because core asks the permission callback again
	 * on this same hook to work out the `Allow` header, and not afterwards. A request dispatched
	 * with `rest_do_request()` never reaches this hook and stays remembered; the plugin does not
	 * dispatch a limited route that way.
	 *
	 * Public because it is a hook callback, not part of the controller's API.
	 *
	 * @param \WP_HTTP_Response|mixed $response   The response about to be sent.
	 * @param \WP_REST_Server         $server     The server.
	 * @param \WP_REST_Request        $dispatched The request it answers.
	 *
	 * @return \WP_HTTP_Response|mixed The response, with the allowance on it when it was counted.
	 */
	public static function send_rate_limit_headers( $response, $server, $dispatched ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$entry = null;

		foreach ( self::$counted as $memo => $counted ) {
			if ( $counted['request'] === $dispatched ) {
				$entry = $counted;
				unset( self::$counted[ $memo ] );
			}
		}

		if ( null === $entry || ! $response instanceof \WP_HTTP_Response ) {
			return $response;
		}

		$seconds = \max( 0, $entry['reset'] - \time() );

		$response->header( 'RateLimit-Limit', (string) $entry['limit'] );
		$response->header( 'RateLimit-Remaining', (string) $entry['remaining'] );
		$response->header( 'RateLimit-Reset', (string) $seconds );

		/*
		 * These numbers belong to one caller, so the answer must not be stored by a shared cache
		 * and handed to the next one. An endpoint that already said how it may be cached keeps its
		 * own directive: `Server::add_cache_headers()` runs first and the token endpoint sends the
		 * one RFC 6749 section 5.1 asks for.
		 */
		$headers = $response->get_headers();

		if ( empty( $headers['Cache-Control'] ) ) {
			maybe_set_no_store( $response );
		}

		if ( 429 === $response->get_status() ) {
			$response->header( 'Retry-After', (string) \max( 1, $seconds ) );
		}

		return $response;
	}
}
