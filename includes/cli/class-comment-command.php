<?php
/**
 * Comment CLI Command.
 *
 * @package Activitypub
 */

namespace Activitypub\Cli;

use function Activitypub\add_to_outbox;
use function Activitypub\was_comment_received;

/**
 * Manage ActivityPub comments.
 *
 * @package Activitypub
 */
class Comment_Command extends \WP_CLI_Command {

	/**
	 * Delete a Comment from the Fediverse.
	 *
	 * Sends a Delete activity to all followers to remove the comment from
	 * federated instances.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the Comment.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete comment with ID 123
	 *     $ wp activitypub comment delete 123
	 *
	 * @subcommand delete
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function delete( $args, $assoc_args ) {
		$comment = \get_comment( $args[0] );

		if ( ! $comment ) {
			\WP_CLI::error( 'Comment not found.' );
		}

		if ( was_comment_received( $comment ) ) {
			\WP_CLI::error( 'This comment was received via ActivityPub and cannot be deleted or updated.' );
		}

		\WP_CLI::confirm( 'Do you really want to delete the Comment with the ID: ' . $args[0], $assoc_args );
		add_to_outbox( $comment, 'Delete', $comment->user_id );
		\WP_CLI::success( '"Delete" activity is queued.' );
	}

	/**
	 * Update a Comment on the Fediverse.
	 *
	 * Sends an Update activity to all followers to refresh the comment content
	 * on federated instances.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the Comment.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update comment with ID 123
	 *     $ wp activitypub comment update 123
	 *
	 * @subcommand update
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments (unused).
	 */
	public function update( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$comment = \get_comment( $args[0] );

		if ( ! $comment ) {
			\WP_CLI::error( 'Comment not found.' );
		}

		if ( was_comment_received( $comment ) ) {
			\WP_CLI::error( 'This comment was received via ActivityPub and cannot be deleted or updated.' );
		}

		add_to_outbox( $comment, 'Update', $comment->user_id );
		\WP_CLI::success( '"Update" activity is queued.' );
	}
}
