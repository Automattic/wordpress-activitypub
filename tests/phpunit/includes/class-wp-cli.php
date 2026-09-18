<?php
/**
 * Minimal WP_CLI facade stub for tests.
 *
 * @package Activitypub
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Stub of the WP-CLI facade exposing only what the ActivityPub commands use.
	 *
	 * `error()` throws so a halting call can be asserted; `success()` records its
	 * message so the non-error path can be checked. The production facade exits
	 * the process on `error()`, which a test cannot observe.
	 */
	class WP_CLI {
		/**
		 * The last message passed to success().
		 *
		 * @var string|null
		 */
		public static $last_success = null;

		/**
		 * Halt with an error, mirroring the production facade.
		 *
		 * @param string $message Error message.
		 *
		 * @throws \RuntimeException Always, so a halting command can be asserted.
		 */
		public static function error( $message ) {
			throw new \RuntimeException( esc_html( is_string( $message ) ? $message : '' ) );
		}

		/**
		 * Record a success message.
		 *
		 * @param string $message Success message.
		 */
		public static function success( $message ) {
			self::$last_success = $message;
		}
	}
}
