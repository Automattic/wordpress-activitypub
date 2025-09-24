<?php
/**
 * WP-CLI file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;
use Activitypub\Scheduler\Actor;

/**
 * WP-CLI commands.
 *
 * @package Activitypub
 */
class Cli extends \WP_CLI_Command {

	/**
	 * Remove the entire blog from the Fediverse.
	 *
	 * This command permanently removes your blog from ActivityPub networks by sending
	 * Delete activities to all followers. This action is IRREVERSIBLE.
	 *
	 * ## OPTIONS
	 *
	 * [--status]
	 * : Check the status of the self-destruct process instead of running it.
	 * Use this to monitor progress after initiating the deletion process.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt and proceed with deletion immediately.
	 * Use with extreme caution as this bypasses all safety checks.
	 *
	 * ## EXAMPLES
	 *
	 *     # Start the self-destruct process (with confirmation prompt)
	 *     $ wp activitypub self_destruct
	 *
	 *     # Check the status of an ongoing self-destruct process
	 *     $ wp activitypub self_destruct --status
	 *
	 *     # Force deletion without confirmation (dangerous!)
	 *     $ wp activitypub self_destruct --yes
	 *
	 * ## WHAT THIS DOES
	 *
	 * - Finds all users with ActivityPub capabilities
	 * - Creates Delete activities for each user
	 * - Sends these activities to all followers
	 * - Removes your blog from ActivityPub discovery
	 * - Sets a flag to track completion status
	 *
	 * ## IMPORTANT NOTES
	 *
	 * - This action cannot be undone
	 * - Keep the ActivityPub plugin active during the process
	 * - The process may take several minutes to complete
	 * - You will be notified when the process finishes
	 *
	 * @param array|null $args       The positional arguments (unused).
	 * @param array|null $assoc_args The associative arguments (--status, --yes).
	 *
	 * @return void
	 */
	public function self_destruct( $args, $assoc_args = array() ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Check if --status flag is provided.
		if ( isset( $assoc_args['status'] ) ) {
			$this->show_self_destruct_status();
			return;
		}

		// Check if self-destruct has already been run.
		if ( \get_option( 'activitypub_self_destruct' ) ) {
			\WP_CLI::error( 'Self-destruct has already been initiated. The process may still be running or has completed.' . PHP_EOL . \WP_CLI::colorize( 'To check the status, run: %Bwp activitypub self_destruct --status%n' ) );
			return;
		}

		$this->execute_self_destruct( $assoc_args );
	}

	/**
	 * Execute the self-destruct process.
	 *
	 * This method handles the actual deletion process:
	 * 1. Displays warning and confirmation prompt
	 * 2. Retrieves all ActivityPub-capable users
	 * 3. Creates and schedules Delete activities for each user
	 * 4. Sets the self-destruct flag for status tracking
	 * 5. Provides progress feedback and completion instructions
	 *
	 * @param array $assoc_args The associative arguments from WP-CLI.
	 *
	 * @return void
	 */
	private function execute_self_destruct( $assoc_args ) {
		$this->display_self_destruct_warning();
		\WP_CLI::confirm( 'Are you absolutely sure you want to continue?', $assoc_args );

		$user_ids = $this->get_activitypub_users();
		if ( empty( $user_ids ) ) {
			\WP_CLI::warning( 'No ActivityPub users found. Nothing to delete.' );
			return;
		}

		$processed = $this->process_user_deletions( $user_ids );
		$this->display_completion_message( $processed );
	}

	/**
	 * Display the self-destruct warning message.
	 *
	 * @return void
	 */
	private function display_self_destruct_warning() {
		\WP_CLI::line( \WP_CLI::colorize( '%R⚠️  DESTRUCTIVE OPERATION ⚠️%n' ) );
		\WP_CLI::line( '' );

		$question = 'You are about to delete your blog from the Fediverse. This action is IRREVERSIBLE and will:';
		\WP_CLI::line( \WP_CLI::colorize( "%y{$question}%n" ) );
		\WP_CLI::line( \WP_CLI::colorize( '%y• Send Delete activities to all followers%n' ) );
		\WP_CLI::line( \WP_CLI::colorize( '%y• Remove your blog from ActivityPub networks%n' ) );
		\WP_CLI::line( '' );
	}

	/**
	 * Get all users with ActivityPub capabilities.
	 *
	 * @return array Array of user IDs with ActivityPub capabilities.
	 */
	private function get_activitypub_users() {
		return \get_users(
			array(
				'fields'         => 'ID',
				'capability__in' => array( 'activitypub' ),
			)
		);
	}

	/**
	 * Process user deletions and create Delete activities.
	 *
	 * @param array $user_ids Array of user IDs to process.
	 *
	 * @return int Number of users successfully processed.
	 */
	private function process_user_deletions( $user_ids ) {
		$user_count = \count( $user_ids );
		\WP_CLI::line( \WP_CLI::colorize( '%GStarting Fediverse deletion process...%n' ) );
		\WP_CLI::line( \WP_CLI::colorize( "%BFound {$user_count} ActivityPub user(s) to process:%n" ) );
		\WP_CLI::line( '' );

		// Set the self-destruct flag.
		\update_option( 'activitypub_self_destruct', true );

		$processed = 0;
		foreach ( $user_ids as $user_id ) {
			if ( $this->create_delete_activity_for_user( $user_id, $processed, $user_count ) ) {
				++$processed;
			}
		}

		\WP_CLI::line( '' );

		if ( 0 === $processed ) {
			\WP_CLI::error( 'Failed to schedule any deletions. Please check your configuration.' );
		}

		return $processed;
	}

	/**
	 * Create a Delete activity for a specific user.
	 *
	 * @param int $user_id    The user ID to process.
	 * @param int $processed  Number of users already processed.
	 * @param int $user_count Total number of users to process.
	 *
	 * @return bool True if the activity was created successfully, false otherwise.
	 */
	private function create_delete_activity_for_user( $user_id, $processed, $user_count ) {
		$actor = Actors::get_by_id( $user_id );

		if ( ! $actor ) {
			\WP_CLI::line( \WP_CLI::colorize( "%R✗ Failed to load user ID: {$user_id}%n" ) );
			return false;
		}

		$activity = new Activity();
		$activity->set_actor( $actor->get_id() );
		$activity->set_object( $actor->get_id() );
		$activity->set_type( 'Delete' );

		$result = add_to_outbox( $activity, null, $user_id );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::line( \WP_CLI::colorize( "%R✗ Failed to schedule deletion for: %B{$actor->get_name()}%n - {$result->get_error_message()}" ) );
			return false;
		}

		$current = $processed + 1;
		\WP_CLI::line( \WP_CLI::colorize( "%G✓%n [{$current}/{$user_count}] Scheduled deletion for: %B{$actor->get_name()}%n" ) );
		return true;
	}

	/**
	 * Display the completion message after processing.
	 *
	 * @param int $processed Number of users successfully processed.
	 *
	 * @return void
	 */
	private function display_completion_message( $processed ) {
		if ( 0 === $processed ) {
			return; // Error already displayed in process_user_deletions.
		}

		\WP_CLI::success( "Successfully scheduled {$processed} user(s) for Fediverse deletion." );
		\WP_CLI::line( '' );
		\WP_CLI::line( \WP_CLI::colorize( '%Y📋 Next Steps:%n' ) );
		\WP_CLI::line( \WP_CLI::colorize( '%Y• Keep the ActivityPub plugin active%n' ) );
		\WP_CLI::line( \WP_CLI::colorize( '%Y• Delete activities will be sent automatically%n' ) );
		\WP_CLI::line( \WP_CLI::colorize( '%Y• Process may take several minutes to complete%n' ) );
		\WP_CLI::line( \WP_CLI::colorize( '%Y• The plugin will notify you when the process is done.%n' ) );
		\WP_CLI::line( '' );
	}

	/**
	 * Show the status of the self-destruct process.
	 *
	 * Checks the current state of the self-destruct process by:
	 * - Verifying if the process has been initiated
	 * - Counting remaining pending Delete activities
	 * - Displaying appropriate status messages and progress
	 * - Providing guidance on next steps
	 *
	 * Status can be:
	 * - NOT STARTED: Process hasn't been initiated
	 * - IN PROGRESS: Delete activities are still being processed
	 * - COMPLETED: All Delete activities have been sent
	 *
	 * @return void
	 */
	private function show_self_destruct_status() {
		// Only proceed if self-destruct is active.
		if ( ! \get_option( 'activitypub_self_destruct', false ) ) {
			\WP_CLI::line( \WP_CLI::colorize( '%C❌ Status: NOT STARTED%n' ) );
			\WP_CLI::line( \WP_CLI::colorize( '%CThe self-destruct process has not been initiated.%n' ) );
			\WP_CLI::line( '' );
			\WP_CLI::line( \WP_CLI::colorize( '%CTo start the process, run:%n %Bwp activitypub self_destruct%n' ) );
			\WP_CLI::line( '' );
			return;
		}

		\WP_CLI::line( \WP_CLI::colorize( '%B🔍 Self-Destruct Status Check%n' ) );
		\WP_CLI::line( '' );

		// Check if there are any more pending Delete activities for self-destruct.
		$pending_deletes = \get_posts(
			array(
				'post_type'      => Outbox::POST_TYPE,
				'post_status'    => 'pending',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'   => '_activitypub_activity_type',
						'value' => 'Delete',
					),
				),
			)
		);

		// Get count of pending Delete activities.
		$pending_count = count( $pending_deletes );

		// If no more pending Delete activities, self-destruct is complete.
		if ( 0 === $pending_count ) {
			\WP_CLI::line( \WP_CLI::colorize( '%G✅ Status: COMPLETED%n' ) );
			\WP_CLI::line( \WP_CLI::colorize( '%GYour blog has been successfully removed from the Fediverse.%n' ) );
			\WP_CLI::line( '' );
			\WP_CLI::line( \WP_CLI::colorize( '%Y📋 What happened:%n' ) );
			\WP_CLI::line( \WP_CLI::colorize( '%Y• Delete activities were sent to all followers%n' ) );
			\WP_CLI::line( \WP_CLI::colorize( '%Y• Your blog is no longer discoverable on ActivityPub networks%n' ) );
			\WP_CLI::line( \WP_CLI::colorize( '%Y• The self-destruct process has finished%n' ) );
		} else {
			\WP_CLI::line( \WP_CLI::colorize( '%Y⏳ Status: IN PROGRESS%n' ) );
			\WP_CLI::line( \WP_CLI::colorize( '%YThe self-destruct process is currently running.%n' ) );
			\WP_CLI::line( '' );

			\WP_CLI::line( \WP_CLI::colorize( "%YProgress: {$pending_count} Delete Activities still pending%n" ) );

			\WP_CLI::line( '' );
			\WP_CLI::line( \WP_CLI::colorize( '%YNote: The process may take several minutes to complete.%n' ) );
		}

		\WP_CLI::line( '' );
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
	 * Delete or Update an Actor.
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
	 * : The id of the Actor.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp activitypub actor delete 1
	 *
	 * @synopsis <action> <id>
	 *
	 * @param array $args The arguments.
	 */
	public function actor( $args ) {
		if ( Actors::APPLICATION_USER_ID === (int) $args[1] ) {
			\WP_CLI::error( 'You cannot delete the application actor.' );
		}

		switch ( $args[0] ) {
			case 'delete':
				\add_filter( 'activitypub_user_can_activitypub', '__return_true' );
				Actor::schedule_user_delete( $args[1] );
				\remove_filter( 'activitypub_user_can_activitypub', '__return_true' );
				\WP_CLI::success( '"Delete" activity is queued.' );
				break;
			case 'update':
				Actor::schedule_profile_update( $args[1] );
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
