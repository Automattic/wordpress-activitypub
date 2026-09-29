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
 * @since unreleased
 */
trait Rate_Limit {
	/**
	 * What each request was already told, so it is counted once, keyed by request.
	 *
	 * @var array<string, array{request: \WP_REST_Request, answer: true|\WP_Error}>
	 */
	private static $counted = array();

	/**
	 * Count a request against its caller's allowance.
	 *
	 * WordPress may ask a permission callback more than once for the same request: core's
	 * `rest_send_allow_header()` calls it again after dispatch to work out the `Allow` header. The
	 * answer is therefore remembered per request object, so one request spends one unit however
	 * often it is asked.
	 *
	 * @since unreleased
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
		 * The request is kept beside the answer: `spl_object_id()` hands out the id of a freed object
		 * again, so without a reference a later request could read this answer as its own. The entry
		 * lives until the response is stamped, so a refused caller who keeps asking does not pile up
		 * entries either; each one goes with its own response.
		 */
		self::$counted[ $memo ] = array(
			'request' => $request,
			'answer'  => $this->count_request( $bucket, $limit, $request, $memo ),
		);

		return self::$counted[ $memo ]['answer'];
	}

	/**
	 * Count one request and report the allowance on the response it gets.
	 *
	 * @param string           $bucket  What is being limited.
	 * @param int              $limit   How many requests a caller may make per minute.
	 * @param \WP_REST_Request $request The request being counted.
	 * @param string           $memo    Where this request's answer is remembered.
	 *
	 * @return true|\WP_Error True when the request fits in the allowance, WP_Error otherwise.
	 */
	private function count_request( $bucket, $limit, $request, $memo ) {
		/**
		 * Filters how many requests a caller may make per minute.
		 *
		 * @since unreleased
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

		// Without a caller there is nothing to count, so the request is refused rather than let through.
		if ( '' === $caller || $count >= $limit ) {
			$this->send_rate_limit_headers( $request, $memo, $limit, 0, $reset );

			return new \WP_Error(
				'activitypub_rate_limited',
				\__( 'Too many requests. Please try again later.', 'activitypub' ),
				array( 'status' => 429 )
			);
		}

		\set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		$this->send_rate_limit_headers( $request, $memo, $limit, $limit - $count - 1, $reset );

		return true;
	}

	/**
	 * Report the allowance on the response this request gets.
	 *
	 * The header names follow the IETF RateLimit header fields, which is what other Fediverse
	 * servers read. `Retry-After` is added to a refusal as well, per RFC 9110 section 10.2.3, and the
	 * answer is kept out of shared caches, because the numbers describe one caller.
	 *
	 * The callback answers only the response to the request it counted, and takes itself off the hook
	 * once it has: a process can dispatch more than one REST request, `rest_do_request()` being the
	 * common case, and a callback left behind would stamp one request's allowance on another's answer.
	 *
	 * The remembered answer goes with it. It is needed until here, because core asks the permission
	 * callback again on this same hook to work out the `Allow` header, and not afterwards, so keeping it
	 * any longer would only hold the request object for the rest of the process.
	 *
	 * @param \WP_REST_Request $request   The request that was counted.
	 * @param string           $memo      Where this request's answer is remembered.
	 * @param int              $limit     The allowance.
	 * @param int              $remaining What is left of it after this request.
	 * @param int              $reset     When it resets, as a Unix timestamp.
	 */
	private function send_rate_limit_headers( $request, $memo, $limit, $remaining, $reset ) {
		$callback = static function ( $response, $server, $dispatched ) use ( &$callback, $request, $memo, $limit, $remaining, $reset ) {
			if ( $dispatched !== $request || ! $response instanceof \WP_HTTP_Response ) {
				return $response;
			}

			\remove_filter( 'rest_post_dispatch', $callback );
			unset( self::$counted[ $memo ] );

			$seconds = \max( 0, $reset - \time() );

			$response->header( 'RateLimit-Limit', (string) $limit );
			$response->header( 'RateLimit-Remaining', (string) $remaining );
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
		};

		\add_filter( 'rest_post_dispatch', $callback, 10, 3 );
	}
}
