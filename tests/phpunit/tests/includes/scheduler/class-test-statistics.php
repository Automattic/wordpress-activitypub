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
		\add_filter( 'pre_wp_mail', array( $this, 'short_circuit_wp_mail' ) );
	}

	/**
	 * Remove the test mail filter so it doesn't leak across tests.
	 */
	public function tear_down() {
		\remove_filter( 'pre_wp_mail', array( $this, 'short_circuit_wp_mail' ) );
		parent::tear_down();
	}

	/**
	 * Count sent emails and short-circuit wp_mail during tests.
	 *
	 * @return bool Always true to short-circuit wp_mail.
	 */
	public function short_circuit_wp_mail() {
		++$this->sent_count;
		return true;
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

		$email_sent_option = \sprintf( 'activitypub_stats_emailed_%d_%d_%d', self::$user_id, $year, $month );
		$this->assertNotFalse( \get_option( $email_sent_option, false ), 'Email-sent option should be persisted.' );
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

		$email_sent_option = \sprintf( 'activitypub_stats_emailed_%d_%d_annual', self::$user_id, $year );
		$this->assertNotFalse( \get_option( $email_sent_option, false ), 'Email-sent option should be persisted.' );
	}

	/**
	 * Test that a forced monthly send still records the email-sent option so a later
	 * non-forced cron run cannot send another copy for the same period.
	 *
	 * @covers ::send_monthly_email
	 */
	public function test_send_monthly_email_force_records_email_sent() {
		$year   = 2026;
		$month  = 3;
		$option = Statistics_Collector::get_monthly_option_name( self::$user_id, $year, $month );

		\update_option(
			$option,
			array(
				'posts_count'     => 1,
				'followers_count' => 2,
				'followers_total' => 7,
			)
		);

		// Forced send writes the email-sent option even when none existed yet.
		Statistics_Scheduler::send_monthly_email( self::$user_id, $year, $month, true );

		$email_sent_option = \sprintf( 'activitypub_stats_emailed_%d_%d_%d', self::$user_id, $year, $month );
		$this->assertNotFalse( \get_option( $email_sent_option, false ), 'Forced send should still persist the email-sent option.' );

		// A subsequent non-forced send must be suppressed by the existing record.
		Statistics_Scheduler::send_monthly_email( self::$user_id, $year, $month );

		$this->assertSame( 1, $this->sent_count, 'Email-sent record from a forced send must block later non-forced sends.' );

		// Forced still sends regardless of an existing record.
		Statistics_Scheduler::send_monthly_email( self::$user_id, $year, $month, true );

		$this->assertSame( 2, $this->sent_count, 'Forced send should bypass the email-sent check and still send.' );
	}

	/**
	 * Test that a forced annual send still records the email-sent option so a later
	 * non-forced cron run cannot send another copy for the same year.
	 *
	 * @covers ::send_annual_email
	 */
	public function test_send_annual_email_force_records_email_sent() {
		\update_user_option( self::$user_id, 'activitypub_mailer_annual_report', '1' );

		$year    = 2025;
		$summary = array(
			'posts_count'     => 8,
			'followers_count' => 4,
		);

		// Forced send writes the email-sent option even when none existed yet.
		Statistics_Scheduler::send_annual_email( self::$user_id, $year, $summary, true );

		$email_sent_option = \sprintf( 'activitypub_stats_emailed_%d_%d_annual', self::$user_id, $year );
		$this->assertNotFalse( \get_option( $email_sent_option, false ), 'Forced send should still persist the email-sent option.' );

		// A subsequent non-forced send must be suppressed by the existing record.
		Statistics_Scheduler::send_annual_email( self::$user_id, $year, $summary );

		$this->assertSame( 1, $this->sent_count, 'Email-sent record from a forced send must block later non-forced sends.' );

		// Forced still sends regardless of an existing record.
		Statistics_Scheduler::send_annual_email( self::$user_id, $year, $summary, true );

		$this->assertSame( 2, $this->sent_count, 'Forced send should bypass the email-sent check and still send.' );
	}
}
