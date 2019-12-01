<?php
namespace Activitypub;

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
			\add_action( 'activitypub_safe_remote_post_response', array( '\Activitypub\Debug', 'log_remote_post_responses' ), 10, 4 );
		}
	}

	public static function log_remote_post_responses( $response, $url, $body, $user_id ) {
		\error_log( "Request to: {$url} with response: " . \print_r( $response, true ) );
	}

	public static function write_log( $log ) {
		if ( \is_array( $log ) || \is_object( $log ) ) {
			\error_log( \print_r( $log, true ) );
		} else {
			\error_log( $log );
		}
	}
}
