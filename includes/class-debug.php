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
		if ( WP_DEBUG_LOG ) {
			\add_action( 'activitypub_safe_remote_post_response', array( self::class, 'log_remote_post_responses' ), 10, 4 );
		}
		if ( is_user_logged_in() ) {
			\add_filter( 'comment_text', array( self::class, 'show_comment_source' ), 10, 2 );
		}
	}

	public static function show_comment_source( $comment_text, $comment ) {
		$was_sent = \get_comment_meta( $comment->comment_ID, 'activitypub_status', true );

		if ( $was_sent ) {
			// translators: %s is the federation state of the comment
			$comment_text .= '<p><small>' . sprintf( \__( 'Activitypub → (%s)', 'activitypub' ), $was_sent ) . '</small></p>';
		}

		$was_received = \get_comment_meta( $comment->comment_ID, 'protocol', true );

		if ( 'activitypub' === $was_received ) {
			$comment_text .= '<p><small>' . \__( '← ActivityPub', 'activitypub' ) . '</small></p>';
		}

		return $comment_text;
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
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
