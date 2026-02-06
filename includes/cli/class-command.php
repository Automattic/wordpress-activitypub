<?php
/**
 * Base CLI Command.
 *
 * @package Activitypub
 */

namespace Activitypub\Cli;

/**
 * Manage ActivityPub plugin functionality and federation.
 *
 * @package Activitypub
 */
class Command extends \WP_CLI_Command {

	/**
	 * Display the ActivityPub plugin version.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp activitypub version
	 *     ActivityPub 7.9.0
	 *
	 * @subcommand version
	 *
	 * @param array $args       The positional arguments (unused).
	 * @param array $assoc_args The associative arguments (unused).
	 */
	public function version( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		\WP_CLI::line( 'ActivityPub ' . ACTIVITYPUB_PLUGIN_VERSION );
	}
}
