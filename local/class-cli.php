<?php
/**
 * WP-CLI file.
 *
 * @package Activitypub
 */

namespace Activitypub\Development;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Inbox;
use Activitypub\Comment;
use Activitypub\Statistics;

use function Activitypub\camel_to_snake_case;
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

		// Get the activity data from the inbox post.
		$activity_data = json_decode( $post->post_content, true );

		if ( ! $activity_data ) {
			\WP_CLI::error( 'Failed to decode activity data.' );
		}

		// Get the activity type.
		if ( ! isset( $activity_data['type'] ) ) {
			\WP_CLI::error( 'Activity data does not contain a type field.' );
		}

		$type = camel_to_snake_case( $activity_data['type'] );

		// Get recipients from post meta.
		$user_ids = Inbox::get_recipients( $post_id );

		if ( empty( $user_ids ) ) {
			\WP_CLI::error( 'No recipients found for this inbox item.' );
		}

		// Create Activity object from the activity data.
		$activity = Activity::init_from_array( $activity_data );

		if ( \is_wp_error( $activity ) ) {
			\WP_CLI::error( sprintf( 'Failed to initialize activity: %s', $activity->get_error_message() ) );
		}

		// Trigger both sets of action hooks that handlers may be registered on.
		\do_action( 'activitypub_inbox', $activity_data, $user_ids, $type, $activity, Inbox::CONTEXT_INBOX );
		\do_action( 'activitypub_inbox_' . $type, $activity_data, $user_ids, $activity, Inbox::CONTEXT_INBOX );
		\do_action( 'activitypub_handled_inbox', $activity_data, $user_ids, $type, $activity, $post_id, Inbox::CONTEXT_INBOX );
		\do_action( 'activitypub_handled_inbox_' . $type, $activity_data, $user_ids, $activity, $post_id, Inbox::CONTEXT_INBOX );

		\WP_CLI::success( sprintf( 'Inbox item %d has been reprocessed as %s activity.', $post_id, $activity_data['type'] ) );
	}

	/**
	 * Manage statistics data.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : The action to perform.
	 * ---
	 * options:
	 *   - populate
	 *   - clear
	 *   - collect
	 *   - compile
	 * ---
	 *
	 * [--user_id=<user_id>]
	 * : The user ID to operate on. Defaults to blog user (0).
	 *
	 * [--year=<year>]
	 * : The year to collect/compile stats for. Defaults to current year.
	 *
	 * [--month=<month>]
	 * : The month to collect stats for (1-12). Defaults to current month.
	 *
	 * [--force]
	 * : Force recollection even if stats already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     # Populate demo stats for the blog
	 *     $ wp activitypub stats populate
	 *
	 *     # Populate demo stats for a specific user
	 *     $ wp activitypub stats populate --user_id=1
	 *
	 *     # Clear demo stats for the blog
	 *     $ wp activitypub stats clear
	 *
	 *     # Collect real stats for current month
	 *     $ wp activitypub stats collect
	 *
	 *     # Collect stats for a specific month (force recollect)
	 *     $ wp activitypub stats collect --year=2024 --month=6 --force
	 *
	 *     # Compile annual stats
	 *     $ wp activitypub stats compile --year=2024
	 *
	 * @synopsis <action> [--user_id=<user_id>] [--year=<year>] [--month=<month>] [--force]
	 *
	 * @param array $args       The positional arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function stats( $args, $assoc_args = array() ) {
		$user_id = isset( $assoc_args['user_id'] ) ? (int) $assoc_args['user_id'] : null;
		$year    = isset( $assoc_args['year'] ) ? (int) $assoc_args['year'] : null;
		$month   = isset( $assoc_args['month'] ) ? (int) $assoc_args['month'] : null;
		$force   = isset( $assoc_args['force'] );

		switch ( $args[0] ) {
			case 'populate':
				$target_user = $user_id ?? Actors::BLOG_USER_ID;
				$this->populate_demo_stats( $target_user );
				\WP_CLI::success( "Demo statistics populated for user ID: {$target_user}" );
				break;

			case 'clear':
				$target_user = $user_id ?? Actors::BLOG_USER_ID;
				$this->clear_demo_stats( $target_user );
				\WP_CLI::success( "Demo statistics cleared for user ID: {$target_user}" );
				break;

			case 'collect':
				$results = $this->collect_monthly_stats( $user_id, $year, $month, $force );
				$count   = count( $results );
				$y       = $year ?? gmdate( 'Y' );
				$m       = $month ?? gmdate( 'n' );
				\WP_CLI::success( "Monthly stats collected for {$count} user(s) ({$y}-{$m})." );
				break;

			case 'compile':
				$results = $this->compile_annual_stats( $user_id, $year );
				$count   = count( $results );
				$y       = $year ?? ( gmdate( 'Y' ) - 1 );
				\WP_CLI::success( "Annual stats compiled for {$count} user(s) ({$y})." );
				break;

			case 'send':
				// Delegate to the main ActivityPub stats send command for consistency.
				$command = 'activitypub stats send';

				if ( null !== $user_id ) {
					$command .= ' --user_id=' . $user_id;
				}

				if ( null !== $year ) {
					$command .= ' --year=' . $year;
				}

				if ( null !== $month ) {
					$command .= ' --month=' . $month;
				}

				if ( $force ) {
					$command .= ' --force';
				}

				\WP_CLI::runcommand( $command );
				break;

			default:
				\WP_CLI::error( 'Unknown action. Use "populate", "clear", "collect", "compile", or "send".' );
		}
	}

	/**
	 * Collect monthly statistics.
	 *
	 * @param int|null $user_id The user ID or null for all users.
	 * @param int|null $year    The year.
	 * @param int|null $month   The month.
	 * @param bool     $force   Force recollection even if stats exist.
	 *
	 * @return array Results per user.
	 */
	private function collect_monthly_stats( $user_id, $year, $month, $force ) {
		$year  = $year ?? (int) gmdate( 'Y' );
		$month = $month ?? (int) gmdate( 'n' );

		$user_ids = $user_id ? array( $user_id ) : Statistics::get_active_user_ids();
		$results  = array();

		foreach ( $user_ids as $uid ) {
			if ( $force ) {
				$option_name = Statistics::get_monthly_option_name( $uid, $year, $month );
				\delete_option( $option_name );
			}
			$results[ $uid ] = Statistics::collect_monthly_stats( $uid, $year, $month );
		}

		return $results;
	}

	/**
	 * Compile annual statistics.
	 *
	 * @param int|null $user_id The user ID or null for all users.
	 * @param int|null $year    The year.
	 *
	 * @return array Results per user.
	 */
	private function compile_annual_stats( $user_id, $year ) {
		$year = $year ?? ( (int) gmdate( 'Y' ) - 1 );

		$user_ids = $user_id ? array( $user_id ) : Statistics::get_active_user_ids();
		$results  = array();

		foreach ( $user_ids as $uid ) {
			$results[ $uid ] = Statistics::compile_annual_summary( $uid, $year );
		}

		return $results;
	}

	/**
	 * Populate demo statistics data for testing.
	 *
	 * @param int $user_id The user ID to populate data for.
	 */
	private function populate_demo_stats( $user_id ) {
		$current_year  = (int) \gmdate( 'Y' );
		$current_month = (int) \gmdate( 'n' );

		// Get registered comment types dynamically.
		$comment_types = Comment::get_comment_type_slugs();

		// Base values that will grow over time.
		$followers_base = 50;

		// Populate monthly stats for the current year.
		for ( $month = 1; $month <= $current_month; $month++ ) {
			// Create realistic growth patterns.
			$growth_factor  = $month / 12;
			$seasonal_boost = \in_array( $month, array( 3, 9, 10 ), true ) ? 1.3 : 1.0;

			$posts_count = (int) ( \wp_rand( 6, 14 ) * $seasonal_boost );

			// Followers grow over time.
			$followers_gained = (int) ( \wp_rand( 10, 30 ) * ( 1 + $growth_factor * 0.5 ) );
			$followers_lost   = \wp_rand( 1, 5 );
			$followers_base  += $followers_gained - $followers_lost;

			$stats = array(
				'posts_count'       => $posts_count,
				'followers_gained'  => $followers_gained,
				'followers_lost'    => $followers_lost,
				'followers_total'   => $followers_base,
				'top_posts'         => array(),
				'top_multiplicator' => array(
					'name'  => '@supporter' . $month . '@mastodon.social',
					'url'   => 'https://mastodon.social/@supporter' . $month,
					'count' => \wp_rand( 3, 10 ),
				),
				'collected_at'      => \gmdate( 'Y-m-d H:i:s', \strtotime( "$current_year-$month-28" ) ),
			);

			// Add counts for each registered comment type dynamically.
			foreach ( $comment_types as $type ) {
				$stats[ $type . '_count' ] = (int) ( \wp_rand( 5, 30 ) * ( 1 + $growth_factor ) * $seasonal_boost );
			}

			Statistics::save_monthly_stats( $user_id, $current_year, $month, $stats );
		}
	}

	/**
	 * Clear demo statistics data.
	 *
	 * @param int $user_id The user ID to clear data for.
	 */
	private function clear_demo_stats( $user_id ) {
		$current_year = (int) \gmdate( 'Y' );

		for ( $month = 1; $month <= 12; $month++ ) {
			$option_name = Statistics::get_monthly_option_name( $user_id, $current_year, $month );
			\delete_option( $option_name );
		}

		$annual_option = Statistics::get_annual_option_name( $user_id, $current_year );
		\delete_option( $annual_option );
	}
}
