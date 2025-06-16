<?php
/**
 * Debug Class.
 *
 * @package Activitypub
 */

namespace Activitypub;

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
		if ( \WP_DEBUG && \WP_DEBUG_LOG ) {
			\add_action( 'activitypub_safe_remote_post_response', array( self::class, 'log_remote_post_responses' ), 10, 2 );

			\add_action( 'activitypub_inbox', array( self::class, 'log_inbox' ), 10, 3 );
			\add_action( 'activitypub_rest_inbox_disallowed', array( self::class, 'log_inbox' ), 10, 3 );

			\add_action( 'activitypub_add_to_outbox_failed', array( self::class, 'log_outbox_error' ), 10, 4 );

			\add_action( 'activitypub_sent_to_inbox', array( self::class, 'log_sent_to_inbox' ), 10, 2 );
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
			$actor = $data['actor'] ?? '';
			$url   = object_to_uri( $actor );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			\error_log( "[INBOX] Request from: {$url} with Activity: " . \print_r( $data, true ) );
		}
	}

	/**
	 * Log failed outbox requests.
	 *
	 * @param false|\WP_Error $error   The error object or false.
	 * @param array           $data    The Activity array.
	 * @param string          $type    The type of the request.
	 * @param int             $user_id The ID of the local blog user.
	 */
	public static function log_outbox_error( $error, $data, $type, $user_id ) {
		$error_message = \is_wp_error( $error ) ? $error->get_error_message() : 'Unknown';

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		\error_log( "[OUTBOX] Failed to add {$type}-Activity from: {$user_id} (Error: {$error_message}) with Activity: " . \print_r( $data, true ) );
	}

	/**
	 * Logs Follower notifications.
	 *
	 * @param array  $result The result of the remote post request.
	 * @param string $inbox  The inbox URL.
	 */
	public static function log_sent_to_inbox( $result, $inbox ) {
		if ( \is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			\error_log( "[DISPATCHER] Failed Request to: {$inbox} with Result: " . \print_r( $result, true ) );
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
