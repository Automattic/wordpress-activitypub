<?php
namespace Activitypub;

use WP_DEBUG;
use WP_DEBUG_LOG;

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
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			\add_action( 'activitypub_safe_remote_post_response', array( self::class, 'log_remote_post_responses' ), 10, 4 );
		}
	}

	public static function log_remote_post_responses( $response, $url, $body, $user_id ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
		\error_log( "Request to: {$url} with response: " . \print_r( $response, true ) );
	}

	public static function write_log( $log ) {
		if ( \is_array( $log ) || \is_object( $log ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.PHP.DevelopmentFunctions.error_log_print_r
			\error_log( \print_r( $log, true ) );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			\error_log( $log );
		}
	}
}
