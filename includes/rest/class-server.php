<?php
namespace Activitypub\Rest;

/**
 * Custom (hopefully temporary) ActivityPub Rest Server
 *
 * @author Matthias Pfefferle
 */
class Server extends \WP_REST_Server {
	/**
	 * Overwrite dispatch function to quick fix missing subtype featur
	 *
	 * @see https://core.trac.wordpress.org/ticket/49404
	 *
	 * @param WP_REST_Request $request Request to attempt dispatching.
	 * @return WP_REST_Response Response returned by the callback.
	 */
	public function dispatch( $request ) {
		$content_type = $request->get_content_type();

		if ( ! $content_type ) {
			return parent::dispatch( $request );
		}

		// check for content-sub-types like 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"'
		if ( \preg_match( '/application\/([a-zA-Z+_-]+\+)json/', $content_type['value'] ) ) {
			$request->set_header( 'Content-Type', 'application/json' );
		}

		// make request filterable
		$request = \apply_filters( 'activitypub_pre_dispatch_request', $request );

		return parent::dispatch( $request );
	}
}
