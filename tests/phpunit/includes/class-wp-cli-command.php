<?php
/**
 * Minimal WP_CLI_Command stub for tests.
 *
 * @package Activitypub
 */

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	/**
	 * Stub base class so the ActivityPub CLI command classes can be loaded under
	 * PHPUnit, where WP-CLI is not bootstrapped. The plugin only registers the
	 * real commands when the `WP_CLI` constant is defined, so this class stub
	 * never causes them to run outside of explicit test instantiation.
	 */
	class WP_CLI_Command {}
}
