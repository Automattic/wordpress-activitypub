<?php
/**
 * Follow CLI Command.
 *
 * @package Activitypub
 */

namespace Activitypub\Cli;

use function Activitypub\follow;

/**
 * Follow a remote ActivityPub user.
 *
 * @package Activitypub
 */
class Follow_Command extends \WP_CLI_Command {

	/**
	 * Follow a remote user.
	 *
	 * Sends a Follow activity to subscribe to a remote ActivityPub user.
	 * Use --user flag to specify which local user should follow.
	 *
	 * ## OPTIONS
	 *
	 * <remote_user>
	 * : The remote user to follow (URL or @user@domain format).
	 *
	 * ## EXAMPLES
	 *
	 *     # Follow a remote user
	 *     $ wp activitypub follow https://example.com/@user
	 *
	 *     # Follow as a specific local user
	 *     $ wp --user=pfefferle activitypub follow https://example.com/@user
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments (unused).
	 */
	public function __invoke( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$user_id       = \get_current_user_id();
		$follow_result = follow( $args[0], $user_id );

		if ( \is_wp_error( $follow_result ) ) {
			\WP_CLI::error( $follow_result->get_error_message() );
		} else {
			\WP_CLI::success( 'Follow Scheduled.' );
		}
	}
}
