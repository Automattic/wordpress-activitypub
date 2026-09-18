<?php
/**
 * Stub for remote requests in tests.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

/**
 * Answers `pre_http_request` from a table of URLs and counts the requests.
 */
trait Remote_Request_Stub {
	/**
	 * The number of requests the stub answered.
	 *
	 * @var int
	 */
	protected $requests = 0;

	/**
	 * What the stub answers, keyed by URL: an array served as JSON, an int status code,
	 * or a prepared response array.
	 *
	 * @var array<string, array|int>
	 */
	protected $responses = array();

	/**
	 * Start answering remote requests from the table.
	 */
	protected function stub_remote_requests() {
		$this->requests  = 0;
		$this->responses = array();
		\add_filter( 'pre_http_request', array( $this, 'stub_remote_request' ), 10, 3 );
	}

	/**
	 * Stop answering remote requests.
	 */
	protected function unstub_remote_requests() {
		\remove_filter( 'pre_http_request', array( $this, 'stub_remote_request' ) );
	}

	/**
	 * Answer one request from the table.
	 *
	 * @param false|array $pre  The pre-empted response.
	 * @param array       $args The request arguments.
	 * @param string      $url  The URL.
	 *
	 * @return array The response.
	 */
	public function stub_remote_request( $pre, $args, $url ) {
		++$this->requests;
		$answer = $this->responses[ $url ] ?? 404;

		if ( \is_array( $answer ) && isset( $answer['response'] ) ) {
			return $answer;
		}

		if ( \is_int( $answer ) ) {
			return array(
				'response' => array( 'code' => $answer ),
				'body'     => '',
				'headers'  => array(),
			);
		}

		return array(
			'response' => array( 'code' => 200 ),
			'body'     => \wp_json_encode( $answer ),
			'headers'  => array( 'content-type' => 'application/activity+json' ),
		);
	}

	/**
	 * A response that was redirected to another URL.
	 *
	 * @param string    $served_from The URL the response was served from.
	 * @param array|int $answer      The object, or a status code.
	 *
	 * @return array The response.
	 */
	protected function redirected( $served_from, $answer ) {
		$requests_response      = new \WpOrg\Requests\Response();
		$requests_response->url = $served_from;

		$response = \is_int( $answer )
			? array(
				'response' => array( 'code' => $answer ),
				'body'     => '',
			)
			: array(
				'response' => array( 'code' => 200 ),
				'body'     => \wp_json_encode( $answer ),
			);

		$response['headers']       = array();
		$response['http_response'] = new \WP_HTTP_Requests_Response( $requests_response );

		return $response;
	}
}
