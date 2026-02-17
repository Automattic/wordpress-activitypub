<?php
/**
 * Actor CLI Command.
 *
 * @package Activitypub
 */

namespace Activitypub\Cli;

use Activitypub\Collection\Actors;
use Activitypub\Scheduler\Actor;

/**
 * Manage ActivityPub actors.
 *
 * @package Activitypub
 */
class Actor_Command extends \WP_CLI_Command {

	/**
	 * Delete an Actor from the Fediverse.
	 *
	 * Sends a Delete activity to all followers to remove the actor from
	 * federated instances.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the Actor (user ID).
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete actor with user ID 1
	 *     $ wp activitypub actor delete 1
	 *
	 * @subcommand delete
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments (unused).
	 */
	public function delete( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( Actors::APPLICATION_USER_ID === (int) $args[0] ) {
			\WP_CLI::error( 'You cannot delete the application actor.' );
		}

		\add_filter( 'activitypub_user_can_activitypub', '__return_true' );
		Actor::schedule_user_delete( $args[0] );
		\remove_filter( 'activitypub_user_can_activitypub', '__return_true' );
		\WP_CLI::success( '"Delete" activity is queued.' );
	}

	/**
	 * Update an Actor on the Fediverse.
	 *
	 * Sends an Update activity to all followers to refresh the actor profile
	 * on federated instances.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the Actor (user ID).
	 *
	 * ## EXAMPLES
	 *
	 *     # Update actor with user ID 1
	 *     $ wp activitypub actor update 1
	 *
	 * @subcommand update
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments (unused).
	 */
	public function update( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		Actor::schedule_profile_update( $args[0] );
		\WP_CLI::success( '"Update" activity is queued.' );
	}
}
