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
	 * Test that the earliest-outbox lookup ignores a corrupt `post_date_gmt`
	 * by reading the auto-stamped `post_modified_gmt` instead.
	 *
	 * Regression for a production warning where `post_date_gmt` on the
	 * earliest outbox row was empty/null, dereferenced into a PHP warning,
	 * and silently produced 1970 as the earliest year.
	 *
	 * @covers ::backfill_historical_stats
	 */
	public function test_backfill_uses_post_modified_gmt_when_post_date_gmt_corrupt() {
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

		// Corrupt `post_date_gmt` after insert; `post_modified_gmt` stays valid.
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

		$errors = array();
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		\set_error_handler(
			static function ( $errno, $errstr ) use ( &$errors ) {
				$errors[] = $errstr;
			}
		);

		$result = Statistics::backfill_historical_stats();

		\restore_error_handler();

		$this->assertEmpty( $errors, 'No warnings should fire when the outbox row has a zero `post_date_gmt`.' );
		$this->assertIsArray( $result, 'Scheduler should keep going, not crash.' );
	}
}
