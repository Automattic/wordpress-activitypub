<?php
/**
 * Statistics class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Collection\Interactions;

/**
 * Statistics class.
 *
 * Provides engagement statistics for ActivityPub users.
 */
class Statistics {

	/**
	 * Cache group for statistics.
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'activitypub_stats';

	/**
	 * Cache expiration time in seconds (1 hour).
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = HOUR_IN_SECONDS;

	/**
	 * Get engagement statistics for a user.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return array {
	 *     Statistics array.
	 *
	 *     @type int $followers      Number of followers.
	 *     @type int $following      Number of accounts being followed.
	 *     @type int $total_likes    Total likes received across all posts.
	 *     @type int $total_reposts  Total reposts/announces received.
	 *     @type int $total_replies  Total replies/comments received.
	 *     @type int $total_posts    Total posts published.
	 * }
	 */
	public static function get_user_stats( $user_id ) {
		$cache_key = 'user_stats_' . $user_id;
		$stats     = \wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $stats ) {
			return $stats;
		}

		$stats = array(
			'followers'     => self::get_followers_count( $user_id ),
			'following'     => self::get_following_count( $user_id ),
			'total_likes'   => 0,
			'total_reposts' => 0,
			'total_replies' => 0,
			'total_posts'   => 0,
		);

		// Get all published posts by this user.
		$posts = self::get_user_posts( $user_id );

		$stats['total_posts'] = count( $posts );

		// Sum engagement across all posts.
		foreach ( $posts as $post_id ) {
			$stats['total_likes']   += Interactions::count_by_type( $post_id, 'like' );
			$stats['total_reposts'] += Interactions::count_by_type( $post_id, 'repost' );
			$stats['total_replies'] += self::count_replies( $post_id );
		}

		\wp_cache_set( $cache_key, $stats, self::CACHE_GROUP, self::CACHE_EXPIRATION );

		return $stats;
	}

	/**
	 * Get followers count for a user.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return int The number of followers.
	 */
	public static function get_followers_count( $user_id ) {
		return Followers::count( $user_id );
	}

	/**
	 * Get following count for a user.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return int The number of accounts being followed.
	 */
	public static function get_following_count( $user_id ) {
		return Following::count( $user_id );
	}

	/**
	 * Get all published posts by a user that are ActivityPub enabled.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return array Array of post IDs.
	 */
	public static function get_user_posts( $user_id ) {
		$post_types = \get_post_types_by_support( 'activitypub' );

		if ( empty( $post_types ) ) {
			return array();
		}

		$args = array(
			'author'         => $user_id,
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		return \get_posts( $args );
	}

	/**
	 * Count replies (comments) for a post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return int The number of replies.
	 */
	public static function count_replies( $post_id ) {
		return (int) \get_comments(
			array(
				'post_id' => $post_id,
				'status'  => 'approve',
				'type'    => 'comment',
				'count'   => true,
			)
		);
	}

	/**
	 * Get top posts by engagement for a user.
	 *
	 * @param int $user_id The user ID.
	 * @param int $limit   Maximum number of posts to return.
	 *
	 * @return array {
	 *     Array of top posts with engagement data.
	 *
	 *     @type int    $post_id    The post ID.
	 *     @type string $title      The post title.
	 *     @type string $url        The post URL.
	 *     @type int    $likes      Number of likes.
	 *     @type int    $reposts    Number of reposts.
	 *     @type int    $replies    Number of replies.
	 *     @type int    $total      Total engagement.
	 * }
	 */
	public static function get_top_posts( $user_id, $limit = 3 ) {
		$cache_key = 'top_posts_' . $user_id . '_' . $limit;
		$top_posts = \wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $top_posts ) {
			return $top_posts;
		}

		$posts      = self::get_user_posts( $user_id );
		$posts_data = array();

		foreach ( $posts as $post_id ) {
			$likes   = Interactions::count_by_type( $post_id, 'like' );
			$reposts = Interactions::count_by_type( $post_id, 'repost' );
			$replies = self::count_replies( $post_id );
			$total   = $likes + $reposts + $replies;

			// Skip posts with no engagement.
			if ( 0 === $total ) {
				continue;
			}

			$posts_data[] = array(
				'post_id' => $post_id,
				'title'   => \get_the_title( $post_id ),
				'url'     => \get_permalink( $post_id ),
				'likes'   => $likes,
				'reposts' => $reposts,
				'replies' => $replies,
				'total'   => $total,
			);
		}

		// Sort by total engagement descending.
		usort(
			$posts_data,
			function ( $a, $b ) {
				return $b['total'] - $a['total'];
			}
		);

		$top_posts = array_slice( $posts_data, 0, $limit );

		\wp_cache_set( $cache_key, $top_posts, self::CACHE_GROUP, self::CACHE_EXPIRATION );

		return $top_posts;
	}

	/**
	 * Get total engagement count (likes + reposts + replies).
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return int Total engagement count.
	 */
	public static function get_total_engagement( $user_id ) {
		$stats = self::get_user_stats( $user_id );

		return $stats['total_likes'] + $stats['total_reposts'] + $stats['total_replies'];
	}

	/**
	 * Clear cached statistics for a user.
	 *
	 * @param int $user_id The user ID.
	 */
	public static function clear_cache( $user_id ) {
		\wp_cache_delete( 'user_stats_' . $user_id, self::CACHE_GROUP );
		\wp_cache_delete( 'top_posts_' . $user_id . '_3', self::CACHE_GROUP );
		\wp_cache_delete( 'top_posts_' . $user_id . '_5', self::CACHE_GROUP );
	}
}
