<?php
namespace Activitypub;

use WP_DEBUG;
use WP_DEBUG_LOG;

use function Activitypub\object_to_uri;

/**
 * ActivityPub Debug Class
 *
 * @author Matthias Pfefferle
 */
class Debug {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		if ( WP_DEBUG_LOG ) {
			\add_action( 'activitypub_safe_remote_post_response', array( self::class, 'log_remote_post_responses' ), 10, 4 );
			\add_action( 'activitypub_inbox', array( self::class, 'log_inbox' ), 10, 4 );
		}
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public static function log_remote_post_responses( $response, $url, $body, $user_id ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
		\error_log( "[OUTBOX] Request to: {$url} with Response: " . \print_r( $response, true ) );
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	public static function log_inbox( $data, $user_id, $type, $activity ) {
		$type = strtolower( $type );

		if ( 'delete' !== $type ) {
			$url = object_to_uri( $data['actor'] );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			\error_log( "[INBOX] Request From: {$url} with Activity: " . \print_r( $data, true ) );
		}
	}

	public static function write_log( $log ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
		\error_log( \print_r( $log, true ) );
	}
}
