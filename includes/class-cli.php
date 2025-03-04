<?php
/**
 * WP-CLI file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;
use Activitypub\Model\Blog;
use Activitypub\Scheduler\Comment;
use Activitypub\Scheduler\Post;
use WP_CLI;
use WP_CLI_Command;

use function Activitypub\is_user_disabled;
use function Activitypub\is_user_type_disabled;

/**
 * WP-CLI commands.
 *
 * @package Activitypub
 */
class Cli extends WP_CLI_Command {

	/**
	 * Remove the entire blog from the Fediverse.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp activitypub self-destruct
	 *
	 * @param array|null $args       The arguments.
	 * @param array|null $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	public function self_destruct( $args, $assoc_args ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		WP_CLI::warning( 'Self-Destructing is not implemented yet.' );
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
			WP_CLI::error( 'Post not found.' );
		}

		switch ( $args[0] ) {
			case 'delete':
				WP_CLI::confirm( 'Do you really want to delete the (Custom) Post with the ID: ' . $args[1] );
				Post::schedule_post_activity( 'trash', 'publish', $post );
				WP_CLI::success( '"Delete" activity is queued.' );
				break;
			case 'update':
				Post::schedule_post_activity( 'publish', 'publish', $post );
				WP_CLI::success( '"Update" activity is queued.' );
				break;
			default:
				WP_CLI::error( 'Unknown action.' );
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
			WP_CLI::error( 'Comment not found.' );
		}

		if ( was_comment_received( $comment ) ) {
			WP_CLI::error( 'This comment was received via ActivityPub and cannot be deleted or updated.' );
		}

		switch ( $args[0] ) {
			case 'delete':
				WP_CLI::confirm( 'Do you really want to delete the Comment with the ID: ' . $args[1] );
				Comment::schedule_comment_activity( 'trash', 'approved', $comment );
				WP_CLI::success( '"Delete" activity is queued.' );
				break;
			case 'update':
				Comment::schedule_comment_activity( 'approved', 'approved', $comment );
				WP_CLI::success( '"Update" activity is queued.' );
				break;
			default:
				WP_CLI::error( 'Unknown action.' );
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
			WP_CLI::error( 'Activity not found.' );
		}

		$undo_id = Outbox::undo( $outbox_item_id );
		if ( ! $undo_id ) {
			WP_CLI::error( 'Failed to undo activity.' );
		}
		WP_CLI::success( 'Undo activity scheduled.' );
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
			WP_CLI::error( 'Activity not found.' );
		}

		Outbox::reschedule( $outbox_item_id );

		WP_CLI::success( 'Rescheduled activity.' );
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
			WP_CLI::error( $outbox_item_id->get_error_message() );
		} else {
			WP_CLI::success( 'Moved Scheduled.' );
		}
	}

	/**
	 * Move all ActivityPub enabled users from one domain to another.
	 *
	 * ## OPTIONS
	 *
	 * <domain>
	 *     The new domain to move to.
	 *
	 * ## EXAMPLES
	 *
	 *    $ wp activitypub move_domain newsite.com
	 *
	 * @synopsis <domain>
	 *
	 * @param array $args The arguments.
	 */
	public function move_domain( $args ) {
		$domain = $args[0];

		// Make sure the domain has a scheme.
		if ( ! preg_match( '#^https?://#', $domain ) ) {
			$domain = 'https://' . $domain;
		}

		$domain = \esc_url_raw( $domain );
		$domain = \trailingslashit( $domain );

		// Get the current site URL.
		$site_url = site_url( '/' );

		$moved_count = 0;
		$errors      = array();

		// Check if blog user is enabled.
		if ( ! is_user_disabled( \Activitypub\Collection\Actors::BLOG_USER_ID ) ) {
			WP_CLI::line( 'Moving blog user...' );
			$from   = ( new Blog() )->get_id();
			$result = Move::account( $from, str_replace( $site_url, $domain, $from ) );

			if ( is_wp_error( $result ) ) {
				$errors[] = 'Blog user: ' . $result->get_error_message();
				WP_CLI::warning( 'Failed to move blog user: ' . $result->get_error_message() );
			} else {
				++$moved_count;
				WP_CLI::line( 'Blog user moved successfully.' );
			}
		}

		// Check if regular users are enabled.
		if ( ! is_user_type_disabled( 'user' ) ) {
			// Get all users with ActivityPub capability.
			$users = get_users( array( 'capability__in' => array( 'activitypub' ) ) );

			WP_CLI::line( sprintf( 'Found %d ActivityPub enabled users.', count( $users ) ) );

			foreach ( $users as $user ) {
				// Skip disabled users.
				if ( is_user_disabled( $user->ID ) ) {
					WP_CLI::line( sprintf( 'Skipping disabled user %s.', $user->user_login ) );
					continue;
				}

				$from = Actors::get_by_id( $user->ID )->get_id();
				$to   = str_replace( $site_url, $domain, $from );

				WP_CLI::line( sprintf( 'Moving user %s from %s to %s...', $user->user_login, $from, $to ) );

				$result = Move::account( $from, $to );

				if ( is_wp_error( $result ) ) {
					$errors[] = 'User ' . $user->user_login . ': ' . $result->get_error_message();
					WP_CLI::warning( sprintf( 'Failed to move user %s: %s', $user->user_login, $result->get_error_message() ) );
				} else {
					++$moved_count;
					WP_CLI::line( sprintf( 'User %s moved successfully.', $user->user_login ) );
				}
			}
		}

		if ( $moved_count > 0 ) {
			WP_CLI::success( sprintf( 'Successfully moved %d users to %s.', $moved_count, $domain ) );
		} else {
			WP_CLI::error( 'No users were moved.' );
		}

		if ( ! empty( $errors ) ) {
			WP_CLI::warning( 'The following errors occurred:' );
			foreach ( $errors as $error ) {
				WP_CLI::line( ' - ' . $error );
			}
		}
	}
}
