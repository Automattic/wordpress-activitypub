<?php
/**
 * Rate limit trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use function Activitypub\spend_rate_limit;

/**
 * How often one caller may ask.
 *
 * Controllers use this trait for permission callbacks: an endpoint states its allowance where its
 * route is registered, and the trait counts the caller through `spend_rate_limit()` and reports the
 * allowance in the response headers, whether the request fit in it or not. The headers are added here
 * rather than by the endpoint because a permission callback can return no more than a `WP_Error`,
 * which carries none.
 *
 * The counting itself lives in `spend_rate_limit()`, because the OAuth consent page reaches client
 * discovery without passing a route and has to be counted the same way.
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
		 * The request is kept beside the answer: `spl_object_id()` hands out the id of a freed
		 * object again, so without a reference a later request could read this answer as its own.
		 */
		self::$counted[ $memo ] = array(
			'request' => $request,
			'answer'  => $this->count_request( $bucket, $limit, $request ),
		);

		return self::$counted[ $memo ]['answer'];
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
		$allowance = spend_rate_limit( $bucket, $limit );

		if ( \is_wp_error( $allowance ) ) {
			$data = $allowance->get_error_data();

			$this->send_rate_limit_headers( $request, $data['limit'], 0, $data['reset'] );

			return $allowance;
		}

		$this->send_rate_limit_headers( $request, $allowance['limit'], $allowance['remaining'], $allowance['reset'] );

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
