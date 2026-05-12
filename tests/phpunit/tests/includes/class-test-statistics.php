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
	 * Test that the earliest-outbox lookup falls back to `post_date` when
	 * `post_date_gmt` is the MySQL zero-date.
	 *
	 * Regression for a production warning where dereferencing
	 * `post_date_gmt` produced a PHP warning and a silent 1970 year.
	 *
	 * @covers ::backfill_historical_stats
	 */
	public function test_backfill_falls_back_to_post_date_when_gmt_is_corrupt() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', $user_id )->add_cap( 'activitypub' );
		self::factory()->post->create( array( 'post_author' => $user_id ) );

		$outbox_id = self::factory()->post->create(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_author' => $user_id,
				'post_date'   => '2024-06-15 12:00:00',
				'meta_input'  => array( '_activitypub_activity_type' => 'Create' ),
			)
		);

		// Corrupt `post_date_gmt` after insert; `post_date` stays valid.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->posts,
			array( 'post_date_gmt' => '0000-00-00 00:00:00' ),
			array( 'ID' => $outbox_id ),
			array( '%s' ),
			array( '%d' )
		);
		\clean_post_cache( $outbox_id );

		// Target the regression user explicitly so the assertion isn't sensitive
		// to other AP-capable users that may already exist in the suite.
		$user_index = \array_search( $user_id, Statistics::get_active_user_ids(), true );
		$this->assertNotFalse( $user_index, 'Test user should be in the active-user list.' );

		$errors   = array();
		$relevant = E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		\set_error_handler(
			static function ( $errno, $errstr ) use ( &$errors, $relevant ) {
				if ( $errno & $relevant ) {
					$errors[] = $errstr;
				}
				return false;
			},
			$relevant
		);

		try {
			$result = Statistics::backfill_historical_stats( 12, $user_index );
		} finally {
			\restore_error_handler();
		}

		$this->assertEmpty( $errors, 'No warnings should fire when `post_date_gmt` is zero-dated.' );
		$this->assertIsArray( $result, 'Scheduler should keep going, not crash.' );
		$this->assertSame(
			$user_index,
			$result['user_index'],
			'User should not be skipped — fallback to `post_date` must yield a valid earliest year.'
		);
		$this->assertGreaterThan(
			0,
			$result['year'],
			'A valid earliest year must be derived from `post_date` when `post_date_gmt` is zero-dated.'
		);
	}
}
