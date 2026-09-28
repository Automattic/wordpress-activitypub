<?php
/**
 * Rate limit trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use function Activitypub\get_client_ip;

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
	 * The requests that were allowed, so one request is counted once, keyed by request.
	 *
	 * @var array<string, array{request: \WP_REST_Request, answer: true}>
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

		$answer = $this->count_request( $bucket, $limit, $request );

		/*
		 * Only an allowed request is remembered, and the request is kept beside the answer because
		 * `spl_object_id()` hands out the id of a freed object again, so without a reference a later
		 * request could read this answer as its own. A refusal is not remembered: repeating it costs
		 * nothing, it writes no entry and lets nothing through, and a caller that keeps asking after a
		 * 429 would otherwise have every one of its refused requests kept for the life of the process.
		 */
		if ( true === $answer ) {
			self::$counted[ $memo ] = array(
				'request' => $request,
				'answer'  => $answer,
			);
		}

		return $answer;
	}

	/**
	 * Count one request and report the allowance on the response it gets.
	 *
	 * @param string           $bucket  What is being limited.
	 * @param int              $limit   How many requests a caller may make per minute.
	 * @param \WP_REST_Request $request The request being counted.
	 *
	 * @return true|\WP_Error True when the request fits in the allowance, WP_Error otherwise.
	 */
	private function count_request( $bucket, $limit, $request ) {
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
		$key    = \sprintf( 'activitypub_rate_%s_%s_%d', $bucket, \str_replace( ':', '-', $caller ), $window );
		$count  = (int) \get_transient( $key );

		// Without a caller there is nothing to count, so the request is refused rather than let through.
		if ( '' === $caller || $count >= $limit ) {
			$this->send_rate_limit_headers( $request, $limit, 0, $reset );

			return new \WP_Error(
				'activitypub_rate_limited',
				\__( 'Too many requests. Please try again later.', 'activitypub' ),
				array( 'status' => 429 )
			);
		}

		\set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		$this->send_rate_limit_headers( $request, $limit, $limit - $count - 1, $reset );

		return true;
	}

	/**
	 * Report the allowance on the response this request gets.
	 *
	 * The header names follow the IETF RateLimit header fields, which is what other Fediverse
	 * servers read. `Retry-After` is added to a refusal as well, per RFC 9110 section 10.2.3.
	 *
	 * The callback answers only the response to the request it counted, and takes itself off the hook
	 * once it has: a process can dispatch more than one REST request, `rest_do_request()` being the
	 * common case, and a callback left behind would stamp one request's allowance on another's answer.
	 *
	 * @param \WP_REST_Request $request   The request that was counted.
	 * @param int              $limit     The allowance.
	 * @param int              $remaining What is left of it after this request.
	 * @param int              $reset     When it resets, as a Unix timestamp.
	 */
	private function send_rate_limit_headers( $request, $limit, $remaining, $reset ) {
		$callback = static function ( $response, $server, $dispatched ) use ( &$callback, $request, $limit, $remaining, $reset ) {
			if ( $dispatched !== $request || ! $response instanceof \WP_HTTP_Response ) {
				return $response;
			}

			\remove_filter( 'rest_post_dispatch', $callback );

			$seconds = \max( 0, $reset - \time() );

			$response->header( 'RateLimit-Limit', (string) $limit );
			$response->header( 'RateLimit-Remaining', (string) $remaining );
			$response->header( 'RateLimit-Reset', (string) $seconds );

			if ( 429 === $response->get_status() ) {
				$response->header( 'Retry-After', (string) \max( 1, $seconds ) );
			}

			return $response;
		};

		\add_filter( 'rest_post_dispatch', $callback, 10, 3 );
	}
}
