<?php
/**
 * WP-CLI file.
 *
 * @package Activitypub
 */

namespace Activitypub\Development;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Inbox;
use Activitypub\Collection\Posts;
use Activitypub\Comment;

use function WP_CLI\Utils\get_flag_value;
use function WP_CLI\Utils\make_progress_bar;

/**
 * WP-CLI commands.
 *
 * @package Activitypub
 */
class Cli extends \WP_CLI_Command {

	/**
	 * Add a follower to a user's followers list for testing purposes.
	 *
	 * ## OPTIONS
	 *
	 * <actor>
	 *     The URL or Webfinger of the actor to add as a follower.
	 *
	 * [--user=<id|login|email>]
	 *     The WordPress user to add the follower to. Omit to add to blog actor.
	 *     ---
	 *     default: 0
	 *     ---
	 *
	 * ## EXAMPLES
	 *
	 *    $ wp activitypub add_follower https://example.com/@user
	 *    $ wp activitypub add_follower user@example.com --user=1
	 *    $ wp --user=pfefferle activitypub add_follower https://example.com/@user
	 *
	 * @synopsis <actor> [--user=<id|login|email>]
	 *
	 * @param array $args The arguments.
	 */
	public function add_follower( $args ) {
		$actor_url = $args[0];
		$user_id   = get_current_user_id();
		\WP_CLI::log( sprintf( 'Adding follower %s to user %d...', $actor_url, $user_id ) );

		$result = Followers::add( $user_id, $actor_url );

		if ( \is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		} else {
			\WP_CLI::success( sprintf( 'Follower added successfully (ID: %d).', $result ) );
		}
	}

	/**
	 * Generates some number of new dummy ActivityPub reactions.
	 *
	 * Creates a specified number of new ActivityPub reactions with dummy data.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The action to perform.
	 * ---
	 * options:
	 *   - comments
	 * ---
	 *
	 * [--count=<number>]
	 * : How many ActivityPub reactions to generate?
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--post_id=<post-id>]
	 * : Assign ActivityPub reactions to a specific post.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: progress
	 * options:
	 *   - progress
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate comments for the given post.
	 *     $ wp activitypub generate comments --format=ids --count=3 --post_id=123
	 *     138 139 140
	 *
	 * @param array $args       The arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function generate( $args, $assoc_args ) {
		switch ( $args[0] ) {
			case 'comments':
				$this->generate_comments( $args, $assoc_args );
				break;
			default:
				\WP_CLI::error( 'Unknown action.' );
		}
	}

	/**
	 * Generate demo comments and reactions.
	 *
	 * @param array $args The arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	private function generate_comments( $args, $assoc_args ) {
		$defaults = array(
			'count'   => 100,
			'post_id' => 0,
		);

		$assoc_args = array_merge( $defaults, $assoc_args );

		$format = get_flag_value( $assoc_args, 'format', 'progress' );

		$notify = false;
		if ( 'progress' === $format ) {
			$notify = make_progress_bar( 'Generating comments', $assoc_args['count'] );
		}

		$comment_count = wp_count_comments();
		$total         = (int) $comment_count->total_comments;
		$limit         = $total + $assoc_args['count'];

		for ( $index = $total; $index < $limit; $index++ ) {
			$comment_types   = Comment::get_comment_type_slugs();
			$comment_types[] = 'comment';

			$comment_type = $comment_types[ array_rand( $comment_types ) ];

			$comment_id = wp_insert_comment(
				array(
					'comment_content'    => $comment_type . ' ' . $index,
					'comment_post_ID'    => $assoc_args['post_id'],
					'comment_type'       => $comment_type,
					'comment_author'     => 'Something Doe',
					'comment_author_url' => 'https://example.org/author/' . $index,
					'comment_meta'       => array(
						'protocol'   => 'activitypub',
						'avatar_url' => 'https://i.pravatar.cc/80?u=' . $index,
						'source_id'  => 'https://example.org/canonical/' . $index,
					),
				)
			);
			if ( 'progress' === $format ) {
				$notify->tick();
			} elseif ( 'ids' === $format ) {
				echo esc_attr( $comment_id );
				if ( $index < $limit - 1 ) {
					echo ' ';
				}
			}
		}

		if ( 'progress' === $format ) {
			$notify->finish();
		}
	}

	/**
	 * Reprocess an inbox item.
	 *
	 * ## OPTIONS
	 *
	 * <post_id>
	 * : The post ID of the ap_inbox item to reprocess.
	 *
	 * ## EXAMPLES
	 *
	 *     # Reprocess inbox item with ID 123
	 *     $ wp activitypub reprocess_inbox 123
	 *     Success: Inbox item 123 has been reprocessed.
	 *
	 * @param array $args The arguments.
	 */
	public function reprocess_inbox( $args ) {
		$post_id = absint( $args[0] );

		if ( ! $post_id ) {
			\WP_CLI::error( 'Invalid post ID provided.' );
		}

		$post = Inbox::get( $post_id );

		if ( ! $post ) {
			\WP_CLI::error( sprintf( 'Post with ID %d not found.', $post_id ) );
		}

		\WP_CLI::log( sprintf( 'Reprocessing inbox item %d...', $post_id ) );

		// Get the activity ID (GUID) and activity data.
		$activity_id   = $post->guid;
		$activity_data = json_decode( $post->post_content, true );

		if ( ! $activity_data ) {
			\WP_CLI::error( 'Failed to decode activity data.' );
		}

		// Delete existing comments created from this activity.
		$deleted_comments = 0;
		$deleted_posts    = 0;

		// Delete comments with source_id matching the activity ID.
		$comment = Comment::object_id_to_comment( $activity_id );
		if ( $comment ) {
			\wp_delete_comment( $comment->comment_ID, true );
			++$deleted_comments;
			\WP_CLI::log( sprintf( 'Deleted comment %d (source_id: activity ID)', $comment->comment_ID ) );
		}

		// Delete comments and posts with source_id/GUID matching the activity object ID.
		if ( isset( $activity_data['object'] ) && is_array( $activity_data['object'] ) && isset( $activity_data['object']['id'] ) ) {
			$object_id = $activity_data['object']['id'];

			$object_comment = Comment::object_id_to_comment( $object_id );
			if ( $object_comment ) {
				\wp_delete_comment( $object_comment->comment_ID, true );
				++$deleted_comments;
				\WP_CLI::log( sprintf( 'Deleted comment %d (source_id: object ID)', $object_comment->comment_ID ) );
			}

			// Delete ap_post with GUID matching the object ID.
			$ap_post = Posts::get_by_guid( $object_id );
			if ( ! \is_wp_error( $ap_post ) ) {
				\wp_delete_post( $ap_post->ID, true );
				++$deleted_posts;
				\WP_CLI::log( sprintf( 'Deleted ap_post %d (GUID: object ID)', $ap_post->ID ) );
			}
		}

		if ( $deleted_comments > 0 || $deleted_posts > 0 ) {
			\WP_CLI::log( sprintf( 'Deleted %d comment(s) and %d post(s).', $deleted_comments, $deleted_posts ) );
		} else {
			\WP_CLI::log( 'No existing comments or posts found to delete.' );
		}

		// Bypass signature verification for internal reprocessing.
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		// Create internal REST request to the shared inbox endpoint.
		$request = new \WP_REST_Request( 'POST', '/' . \ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $activity_data ) );

		// Dispatch the request through the REST API.
		$response = \rest_do_request( $request );

		\remove_filter( 'activitypub_defer_signature_verification', '__return_true' );

		if ( \is_wp_error( $response ) ) {
			\WP_CLI::error( sprintf( 'Failed to reprocess: %s', $response->get_error_message() ) );
		}

		$status = $response->get_status();
		if ( $status >= 200 && $status < 300 ) {
			\WP_CLI::success( sprintf( 'Inbox item %d has been reprocessed as %s activity.', $post_id, $activity_data['type'] ) );
		} else {
			$data          = $response->get_data();
			$error_message = isset( $data['message'] ) ? $data['message'] : 'Unknown error';
			\WP_CLI::error( sprintf( 'Failed to reprocess (HTTP %d): %s', $status, $error_message ) );
		}
	}
}
