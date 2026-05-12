<?php
/**
 * Test file for Statistics.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Outbox;
use Activitypub\Statistics;

/**
 * Test class for Statistics.
 *
 * @coversDefaultClass \Activitypub\Statistics
 */
class Test_Statistics extends \WP_UnitTestCase {

	/**
	 * Test that backfill_historical_stats handles a get_post() null result gracefully.
	 *
	 * Regression test for a production warning where `get_post()` returned null
	 * (cron race / cache miss) and the next line dereferenced `->post_date_gmt`
	 * on null. The guard inside `get_earliest_data_year()` keeps the cron call
	 * silent and lets the scheduler advance to the next user.
	 *
	 * @covers ::backfill_historical_stats
	 */
	public function test_backfill_historical_stats_handles_missing_post() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', $user_id )->add_cap( 'activitypub' );

		// Publish a post so the user shows up in `get_active_user_ids()`.
		self::factory()->post->create( array( 'post_author' => $user_id ) );

		// Force the outbox query to return a non-existent ID so `get_post()` returns null.
		$filter = function ( $posts, $query ) {
			if ( Outbox::POST_TYPE === ( $query->query['post_type'] ?? '' ) ) {
				return array( 999999 );
			}
			return $posts;
		};
		\add_filter( 'posts_pre_query', $filter, 10, 2 );

		$errors = array();
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		\set_error_handler(
			static function ( $errno, $errstr ) use ( &$errors ) {
				$errors[] = $errstr;
			}
		);

		$result = Statistics::backfill_historical_stats();

		\restore_error_handler();
		\remove_filter( 'posts_pre_query', $filter, 10 );

		$this->assertEmpty(
			\array_filter(
				$errors,
				static function ( $msg ) {
					return false !== \strpos( $msg, 'post_date_gmt' );
				}
			),
			'No "post_date_gmt on null" warning should fire when get_post() returns null.'
		);
		$this->assertIsArray( $result, 'Scheduler should advance to the next user, not crash.' );
	}
}
