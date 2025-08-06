<?php
/**
 * WP-CLI file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Outbox;

/**
 * WP-CLI commands.
 *
 * @package Activitypub
 */
class Cli extends \WP_CLI_Command {

	/**
	 * Remove the entire blog from the Fediverse.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp activitypub self_destruct
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function self_destruct( $args, $assoc_args = array() ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$question = 'You are in the process of deleting your blog from the Fediverse. This action could be irreversible, so are you sure you want to continue?';

		\WP_CLI::confirm( \WP_CLI::colorize( "%y{$question}%n" ), $assoc_args );

		\WP_CLI::log( 'Deleting your Blog from the Fediverse. Scheduled delete for:' );

		\add_option( 'activitypub_self_destruct', true );

		$user_ids = \get_users(
			array(
				'fields'         => 'ids',
				'capability__in' => array( 'activitypub' ),
			)
		);

		foreach ( $user_ids as $user_id ) {
			$actor = Actors::get_by_id( $user_id );
			add_to_outbox( $actor, 'Delete', $user_id );

			\WP_CLI::line( ' - ' . $actor->get_name() );
		}

		\WP_CLI::success( 'All users scheduled for deletion. This process can take a while. Please keep the plugin active, we will notify you when it is done.' );
	}

	/**
	 * Delete or Update a Post, Page, Custom Post Type or Attachment.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The action to perform. Either `delete` or `update`.
	 * ---
	 * options:
	 *   - delete
	 *   - update
	 * ---
	 *
	 * <id>
	 * : The id of the Post, Page, Custom Post Type or Attachment.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp activitypub post delete 1
	 *
	 * @synopsis <action> <id>
	 *
	 * @param array $args The arguments.
	 */
	public function post( $args ) {
		$post = get_post( $args[1] );

		if ( ! $post ) {
			\WP_CLI::error( 'Post not found.' );
		}

		switch ( $args[0] ) {
			case 'delete':
				\WP_CLI::confirm( 'Do you really want to delete the (Custom) Post with the ID: ' . $args[1] );
				add_to_outbox( $post, 'Delete', $post->post_author );
				\WP_CLI::success( '"Delete" activity is queued.' );
				break;
			case 'update':
				add_to_outbox( $post, 'Update', $post->post_author );
				\WP_CLI::success( '"Update" activity is queued.' );
				break;
			default:
				\WP_CLI::error( 'Unknown action.' );
		}
	}

	/**
	 * Delete or Update a Comment.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The action to perform. Either `delete` or `update`.
	 * ---
	 * options:
	 *   - delete
	 *   - update
	 * ---
	 *
	 * <id>
	 * : The id of the Comment.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp activitypub comment delete 1
	 *
	 * @synopsis <action> <id>
	 *
	 * @param array $args The arguments.
	 */
	public function comment( $args ) {
		$comment = get_comment( $args[1] );

		if ( ! $comment ) {
			\WP_CLI::error( 'Comment not found.' );
		}

		if ( was_comment_received( $comment ) ) {
			\WP_CLI::error( 'This comment was received via ActivityPub and cannot be deleted or updated.' );
		}

		switch ( $args[0] ) {
			case 'delete':
				\WP_CLI::confirm( 'Do you really want to delete the Comment with the ID: ' . $args[1] );
				add_to_outbox( $comment, 'Delete', $comment->user_id );
				\WP_CLI::success( '"Delete" activity is queued.' );
				break;
			case 'update':
				add_to_outbox( $comment, 'Update', $comment->user_id );
				\WP_CLI::success( '"Update" activity is queued.' );
				break;
			default:
				\WP_CLI::error( 'Unknown action.' );
		}
	}

	/**
	 * Undo an activity that was sent to the Fediverse.
	 *
	 * ## OPTIONS
	 *
	 * <outbox_item_id>
	 *     The ID or URL of the outbox item to undo.
	 *
	 * ## EXAMPLES
	 *
	 *    $ wp activitypub undo 123
	 *    $ wp activitypub undo "https://example.com/?post_type=ap_outbox&p=123"
	 *
	 * @synopsis <outbox_item_id>
	 *
	 * @param array $args The arguments.
	 */
	public function undo( $args ) {
		$outbox_item_id = $args[0];
		if ( ! is_numeric( $outbox_item_id ) ) {
			$outbox_item_id = url_to_postid( $outbox_item_id );
		}

		$outbox_item_id = get_post( $outbox_item_id );
		if ( ! $outbox_item_id ) {
			\WP_CLI::error( 'Activity not found.' );
		}

		$undo_id = Outbox::undo( $outbox_item_id );
		if ( ! $undo_id ) {
			\WP_CLI::error( 'Failed to undo activity.' );
		}
		\WP_CLI::success( 'Undo activity scheduled.' );
	}

	/**
	 * Re-Schedule an activity that was sent to the Fediverse before.
	 *
	 * ## OPTIONS
	 *
	 * <outbox_item_id>
	 *     The ID or URL of the outbox item to reschedule.
	 *
	 * ## EXAMPLES
	 *
	 *    $ wp activitypub reschedule 123
	 *    $ wp activitypub reschedule "https://example.com/?post_type=ap_outbox&p=123"
	 *
	 * @synopsis <outbox_item_id>
	 *
	 * @param array $args The arguments.
	 */
	public function reschedule( $args ) {
		$outbox_item_id = $args[0];
		if ( ! is_numeric( $outbox_item_id ) ) {
			$outbox_item_id = url_to_postid( $outbox_item_id );
		}

		$outbox_item_id = get_post( $outbox_item_id );
		if ( ! $outbox_item_id ) {
			\WP_CLI::error( 'Activity not found.' );
		}

		Outbox::reschedule( $outbox_item_id );

		\WP_CLI::success( 'Rescheduled activity.' );
	}

	/**
	 * Move the blog to a new URL.
	 *
	 * ## OPTIONS
	 *
	 * <from>
	 *     The current URL of the blog.
	 *
	 * <to>
	 *     The new URL of the blog.
	 *
	 * ## EXAMPLES
	 *
	 *    $ wp activitypub move https://example.com/ https://newsite.com/
	 *
	 * @synopsis <from> <to>
	 *
	 * @param array $args The arguments.
	 */
	public function move( $args ) {
		$from = $args[0];
		$to   = $args[1];

		$outbox_item_id = Move::account( $from, $to );

		if ( is_wp_error( $outbox_item_id ) ) {
			\WP_CLI::error( $outbox_item_id->get_error_message() );
		} else {
			\WP_CLI::success( 'Move Scheduled.' );
		}
	}

	/**
	 * Follow a user.
	 *
	 * ## OPTIONS
	 *
	 * <remote-user>
	 *     The remote user to follow.
	 *
	 * ## EXAMPLES
	 *
	 *    $ wp activitypub follow https://example.com/@user
	 *    $ wp --user=pfefferle activitypub follow https://example.com/@user
	 *
	 * @synopsis <remote_user>
	 *
	 * @param array $args The arguments.
	 */
	public function follow( $args ) {
		$user_id = \get_current_user_id();
		$follow  = follow( $args[0], $user_id );

		if ( is_wp_error( $follow ) ) {
			\WP_CLI::error( $follow->get_error_message() );
		} else {
			\WP_CLI::success( 'Follow Scheduled.' );
		}
	}
}
