<?php
/**
 * Mock Jetpack class for testing.
 *
 * @package Activitypub
 */

if ( ! class_exists( 'Jetpack' ) ) {
	/**
	 * Mock Jetpack class for testing purposes.
	 */
	class Jetpack {
		/**
		 * Mock method to simulate Jetpack connection status.
		 *
		 * @return bool Always returns true for testing.
		 */
		public static function is_connection_ready() {
			return true;
		}
	}
}
