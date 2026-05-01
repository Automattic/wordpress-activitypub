<?php
/**
 * Test Statistics scheduler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Scheduler;

use Activitypub\Scheduler\Statistics as Statistics_Scheduler;
use Activitypub\Statistics as Statistics_Collector;
use WP_UnitTestCase;

/**
 * Test Statistics scheduler.
 *
 * @coversDefaultClass \Activitypub\Scheduler\Statistics
 */
class Test_Statistics extends WP_UnitTestCase {

	/**
	 * Test user.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Set up fixtures.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create( array( 'role' => 'author' ) );
		\update_user_option( self::$user_id, 'activitypub_mailer_monthly_report', '1' );
	}

	/**
	 * Counter for sent emails.
	 *
	 * @var int
	 */
	protected $sent_count = 0;

	/**
	 * Reset email counter and short-circuit wp_mail before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->sent_count = 0;
		\add_filter(
			'pre_wp_mail',
			function () {
				++$this->sent_count;
				return true;
			}
		);
	}

	/**
	 * Test that send_monthly_email only sends once per (user, year, month).
	 *
	 * @covers ::send_monthly_email
	 */
	public function test_send_monthly_email_is_idempotent() {
		$year   = 2026;
		$month  = 4;
		$option = Statistics_Collector::get_monthly_option_name( self::$user_id, $year, $month );

		// Seed a month of stats with meaningful activity.
		\update_option(
			$option,
			array(
				'posts_count'     => 3,
				'followers_count' => 5,
				'followers_total' => 10,
			)
		);

		Statistics_Scheduler::send_monthly_email( self::$user_id, $year, $month );
		Statistics_Scheduler::send_monthly_email( self::$user_id, $year, $month );
		Statistics_Scheduler::send_monthly_email( self::$user_id, $year, $month );

		$this->assertSame( 1, $this->sent_count, 'Monthly email should only be sent once per period.' );

		$marker = \sprintf( 'activitypub_stats_emailed_%d_%d_%d', self::$user_id, $year, $month );
		$this->assertNotFalse( \get_option( $marker, false ), 'Sent marker option should be persisted.' );
	}

	/**
	 * Test that send_annual_email only sends once per (user, year).
	 *
	 * @covers ::send_annual_email
	 */
	public function test_send_annual_email_is_idempotent() {
		\update_user_option( self::$user_id, 'activitypub_mailer_annual_report', '1' );

		$year    = 2026;
		$summary = array(
			'posts_count'     => 12,
			'followers_count' => 30,
		);

		Statistics_Scheduler::send_annual_email( self::$user_id, $year, $summary );
		Statistics_Scheduler::send_annual_email( self::$user_id, $year, $summary );
		Statistics_Scheduler::send_annual_email( self::$user_id, $year, $summary );

		$this->assertSame( 1, $this->sent_count, 'Annual email should only be sent once per year.' );

		$marker = \sprintf( 'activitypub_stats_emailed_%d_%d_annual', self::$user_id, $year );
		$this->assertNotFalse( \get_option( $marker, false ), 'Sent marker option should be persisted.' );
	}
}
