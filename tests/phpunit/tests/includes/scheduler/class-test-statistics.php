<?php
/**
 * Test Statistics scheduler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Scheduler;

use Activitypub\Collection\Actors;
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
	 * HTML message of the last sent email.
	 *
	 * @var string
	 */
	protected $sent_message = '';

	/**
	 * Plain text alternative of the last sent email.
	 *
	 * @var string
	 */
	protected $sent_alt_body = '';

	/**
	 * Reset email counter and short-circuit wp_mail before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->sent_count    = 0;
		$this->sent_message  = '';
		$this->sent_alt_body = '';
		\add_filter( 'pre_wp_mail', array( $this, 'short_circuit_wp_mail' ), 10, 2 );
	}

	/**
	 * Remove the test mail filter so it doesn't leak across tests.
	 */
	public function tear_down() {
		\remove_filter( 'pre_wp_mail', array( $this, 'short_circuit_wp_mail' ), 10 );
		\remove_action( 'phpmailer_init', array( $this, 'capture_alt_body' ), 11 );
		parent::tear_down();
	}

	/**
	 * Count sent emails and short-circuit wp_mail during tests.
	 *
	 * @param null|bool $short_circuit Short-circuit return value.
	 * @param array     $atts          The wp_mail() arguments.
	 *
	 * @return bool Always true to short-circuit wp_mail.
	 */
	public function short_circuit_wp_mail( $short_circuit, $atts = array() ) {
		++$this->sent_count;
		$this->sent_message = $atts['message'] ?? '';

		return true;
	}

	/**
	 * Capture the plain text alternative body set by the mailer.
	 *
	 * Runs after `Mailer::send()`'s own `phpmailer_init` callback, which assigns AltBody.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $mailer The mailer instance.
	 */
	public function capture_alt_body( $mailer ) {
		$this->sent_alt_body = $mailer->{'AltBody'};
	}

	/**
	 * Seed a month of stats with meaningful activity.
	 *
	 * @param int $user_id The user ID.
	 * @param int $year    The year.
	 * @param int $month   The month.
	 */
	protected function seed_monthly_stats( $user_id, $year, $month ) {
		\update_option(
			Statistics_Collector::get_monthly_option_name( $user_id, $year, $month ),
			array(
				'posts_count'     => 3,
				'followers_count' => 5,
				'followers_total' => 10,
			)
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

	/**
	 * Test that the monthly report links to the Fediverse Stats dashboard widget.
	 *
	 * @covers ::send_monthly_email
	 */
	public function test_send_monthly_email_links_to_stats_widget() {
		$year  = 2026;
		$month = 5;
		$this->seed_monthly_stats( self::$user_id, $year, $month );

		Statistics_Scheduler::send_monthly_email( self::$user_id, $year, $month );

		$this->assertSame( 1, $this->sent_count, 'Monthly email should be sent.' );
		$this->assertStringContainsString(
			\esc_url( \admin_url( 'index.php#activitypub_stats' ) ),
			$this->sent_message,
			'Monthly email should link to the stats dashboard widget.'
		);
		$this->assertStringContainsString( 'View your stats', $this->sent_message, 'Monthly email should show the stats link label.' );
	}

	/**
	 * Test that the annual report links to the Fediverse Stats dashboard widget.
	 *
	 * @covers ::send_annual_email
	 */
	public function test_send_annual_email_links_to_stats_widget() {
		\update_user_option( self::$user_id, 'activitypub_mailer_annual_report', '1' );

		Statistics_Scheduler::send_annual_email( self::$user_id, 2024, array( 'posts_count' => 8 ) );

		$this->assertSame( 1, $this->sent_count, 'Annual email should be sent.' );
		$this->assertStringContainsString(
			\esc_url( \admin_url( 'index.php#activitypub_stats' ) ),
			$this->sent_message,
			'Annual email should link to the stats dashboard widget.'
		);
	}

	/**
	 * Test that the blog actor's report links to the stats widget, since its report
	 * goes to admin_email.
	 *
	 * @covers ::send_monthly_email
	 */
	public function test_send_monthly_email_links_to_stats_widget_for_blog_actor() {
		$year  = 2026;
		$month = 6;

		// The widget's blog branch requires the blog actor to be enabled.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		$this->seed_monthly_stats( Actors::BLOG_USER_ID, $year, $month );

		Statistics_Scheduler::send_monthly_email( Actors::BLOG_USER_ID, $year, $month );

		$this->assertSame( 1, $this->sent_count, 'Blog actor monthly email should be sent.' );
		$this->assertStringContainsString(
			\esc_url( \admin_url( 'index.php#activitypub_stats' ) ),
			$this->sent_message,
			'Blog actor email should link to the stats dashboard widget.'
		);
	}

	/**
	 * Test that no stats link is added when the recipient cannot see the dashboard widget.
	 *
	 * @covers ::send_monthly_email
	 */
	public function test_send_monthly_email_omits_stats_link_without_access() {
		$year  = 2026;
		$month = 7;
		$this->seed_monthly_stats( self::$user_id, $year, $month );

		\add_filter( 'activitypub_user_can_activitypub', '__return_false' );
		Statistics_Scheduler::send_monthly_email( self::$user_id, $year, $month );
		\remove_filter( 'activitypub_user_can_activitypub', '__return_false' );

		$this->assertSame( 1, $this->sent_count, 'Monthly email should still be sent.' );
		$this->assertStringNotContainsString(
			'activitypub_stats',
			$this->sent_message,
			'Email should not link to a dashboard widget the recipient cannot see.'
		);
		$this->assertStringNotContainsString( 'View your stats', $this->sent_message, 'Email should not show the stats link label.' );
	}

	/**
	 * Test that no stats link is added when user mode is disabled, even if a filter forces
	 * the ActivityPub capability on. The widget's own gate re-checks the actor type after
	 * that filter, so the link would otherwise point at a dashboard without the widget.
	 *
	 * @covers ::send_monthly_email
	 */
	public function test_send_monthly_email_omits_stats_link_when_user_mode_disabled() {
		$year  = 2026;
		$month = 9;
		$this->seed_monthly_stats( self::$user_id, $year, $month );

		// Blog-only mode disables the user actor type.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );
		\add_filter( 'activitypub_user_can_activitypub', '__return_true' );

		Statistics_Scheduler::send_monthly_email( self::$user_id, $year, $month );

		\remove_filter( 'activitypub_user_can_activitypub', '__return_true' );

		$this->assertSame( 1, $this->sent_count, 'Monthly email should still be sent.' );
		$this->assertStringNotContainsString(
			'activitypub_stats',
			$this->sent_message,
			'A forced capability should not add a link to a widget the actor type disables.'
		);
	}

	/**
	 * Test that an administrator still gets the link when only the blog actor is enabled,
	 * since the widget's blog branch shows it to anyone who can manage options.
	 *
	 * @covers ::send_monthly_email
	 */
	public function test_send_monthly_email_links_to_stats_widget_for_admin_in_blog_mode() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\update_user_option( $admin_id, 'activitypub_mailer_monthly_report', '1' );

		$year  = 2026;
		$month = 10;
		$this->seed_monthly_stats( $admin_id, $year, $month );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_BLOG_MODE );

		Statistics_Scheduler::send_monthly_email( $admin_id, $year, $month );

		$this->assertSame( 1, $this->sent_count, 'Monthly email should be sent.' );
		$this->assertStringContainsString(
			\esc_url( \admin_url( 'index.php#activitypub_stats' ) ),
			$this->sent_message,
			'An administrator sees the widget through its blog branch, so the link belongs in the email.'
		);
	}

	/**
	 * Test that the plain text alternative also carries the stats URL.
	 *
	 * @covers ::send_monthly_email
	 */
	public function test_send_monthly_email_adds_stats_url_to_alt_body() {
		$year  = 2026;
		$month = 8;
		$this->seed_monthly_stats( self::$user_id, $year, $month );

		// Let wp_mail run so Mailer's phpmailer_init callback assigns AltBody. The default
		// From address (wordpress@localhost) has no TLD, which PHPMailer rejects.
		\remove_filter( 'pre_wp_mail', array( $this, 'short_circuit_wp_mail' ), 10 );
		\add_filter( 'wp_mail_from', array( $this, 'valid_mail_from' ) );
		\add_action( 'phpmailer_init', array( $this, 'capture_alt_body' ), 11 );

		Statistics_Scheduler::send_monthly_email( self::$user_id, $year, $month );

		\remove_filter( 'wp_mail_from', array( $this, 'valid_mail_from' ) );

		$this->assertStringContainsString(
			\admin_url( 'index.php#activitypub_stats' ),
			$this->sent_alt_body,
			'Plain text body should include the stats dashboard URL.'
		);
	}

	/**
	 * Test that the annual report's plain text alternative also carries the stats URL.
	 *
	 * @covers ::send_annual_email
	 */
	public function test_send_annual_email_adds_stats_url_to_alt_body() {
		\update_user_option( self::$user_id, 'activitypub_mailer_annual_report', '1' );

		// Let wp_mail run so Mailer's phpmailer_init callback assigns AltBody.
		\remove_filter( 'pre_wp_mail', array( $this, 'short_circuit_wp_mail' ), 10 );
		\add_filter( 'wp_mail_from', array( $this, 'valid_mail_from' ) );
		\add_action( 'phpmailer_init', array( $this, 'capture_alt_body' ), 11 );

		Statistics_Scheduler::send_annual_email( self::$user_id, 2023, array( 'posts_count' => 8 ) );

		\remove_filter( 'wp_mail_from', array( $this, 'valid_mail_from' ) );

		$this->assertStringContainsString(
			\admin_url( 'index.php#activitypub_stats' ),
			$this->sent_alt_body,
			'Annual plain text body should include the stats dashboard URL.'
		);
	}

	/**
	 * Provide a From address PHPMailer accepts.
	 *
	 * @return string The From address.
	 */
	public function valid_mail_from() {
		return 'wordpress@example.org';
	}
}
