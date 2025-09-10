<?php
/**
 * WP-CLI file.
 *
 * @package Activitypub
 */

namespace Activitypub\Development;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
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
	 * <actor_url>
	 *     The URL of the actor to add as a follower.
	 *
	 * [--user=<user>]
	 *     The user to add the follower to. Defaults to the blog actor.
	 *     ---
	 *     default: 0
	 *     ---
	 *
	 * ## EXAMPLES
	 *
	 *    $ wp activitypub add_follower https://example.com/@user
	 *    $ wp activitypub add_follower https://example.com/@user --user=1
	 *    $ wp --user=pfefferle activitypub add_follower https://example.com/@user
	 *
	 * @synopsis <actor_url> [--user=<user>]
	 *
	 * @param array $args       The arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function add_follower( $args, $assoc_args ) {
		$actor_url = $args[0];
		$user_id   = get_flag_value( $assoc_args, 'user', Actors::BLOG_USER_ID );
		\WP_CLI::log( sprintf( 'Adding follower %s to user %d...', $actor_url, $user_id ) );

		$result = Followers::add_follower( $user_id, $actor_url );

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
}
