<?php
/**
 * Mock Jetpack class for testing.
 *
 * @package Activitypub
 */

namespace Automattic\Jetpack\Connection;

/**
 * Mock Jetpack class for testing.
 */
if ( ! class_exists( 'Automattic\Jetpack\Connection\Manager' ) ) {
	/**
	 * Mock Jetpack class for testing purposes.
	 */
	class Manager {
		/**
		 * Mock method to simulate Jetpack connection status.
		 *
		 * @return bool Always returns true for testing.
		 */
		public function is_user_connected() {
			return true;
		}
	}
}
