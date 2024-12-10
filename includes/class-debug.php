<?php
/**
 * Debug Class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use WP_DEBUG;
use WP_DEBUG_LOG;

/**
 * ActivityPub Debug Class.
 *
 * @author Matthias Pfefferle
 */
class Debug {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		if ( WP_DEBUG_LOG ) {
			\add_action( 'activitypub_safe_remote_post_response', array( self::class, 'log_remote_post_responses' ), 10, 2 );
			\add_action( 'activitypub_inbox', array( self::class, 'log_inbox' ), 10, 3 );
		}
	}

	/**
	 * Log the responses of remote post requests.
	 *
	 * @param array  $response The response from the remote server.
	 * @param string $url      The URL of the remote server.
	 */
	public static function log_remote_post_responses( $response, $url ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		\error_log( "[OUTBOX] Request to: {$url} with Response: " . \print_r( $response, true ) );
	}

	/**
	 * Log the inbox requests.
	 *
	 * @param array  $data    The Activity array.
	 * @param int    $user_id The ID of the local blog user.
	 * @param string $type    The type of the request.
	 */
	public static function log_inbox( $data, $user_id, $type ) {
		$type = strtolower( $type );

		if ( 'delete' !== $type ) {
			$url = object_to_uri( $data['actor'] );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			\error_log( "[INBOX] Request From: {$url} with Activity: " . \print_r( $data, true ) );
		}
	}

	/**
	 * Write a log entry.
	 *
	 * @param mixed $log The log entry.
	 */
	public static function write_log( $log ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		\error_log( \print_r( $log, true ) );
	}
}
