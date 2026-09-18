<?php
/**
 * Tests for ActivityPub CLI command error propagation.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Cli;

use Activitypub\Cli\Comment_Command;
use Activitypub\Cli\Post_Command;

use function Activitypub\set_wp_object_state;

/**
 * Test that the CLI `update` commands surface a WP_Error from the outbox as a
 * command error instead of reporting a bogus success.
 *
 * The command classes only load when the `WP_CLI` constant is defined — never
 * under PHPUnit — so the facade and base class are provided by the stubs in
 * `tests/phpunit/includes`, loaded here before the commands autoload.
 */
class Test_Command_Error_Propagation extends \WP_UnitTestCase {

	/**
	 * Load the WP-CLI stubs before the command classes autoload.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once AP_TESTS_DIR . '/includes/class-wp-cli-command.php';
		require_once AP_TESTS_DIR . '/includes/class-wp-cli.php';
	}

	/**
	 * An Update for a soft-deleted post is rejected by add_to_outbox(); the post
	 * command must halt with that error rather than report success.
	 */
	public function test_post_update_surfaces_outbox_error() {
		$post_id = self::factory()->post->create( array( 'post_author' => 1 ) );
		set_wp_object_state( \get_post( $post_id ), ACTIVITYPUB_OBJECT_STATE_DELETED );

		\WP_CLI::$last_success = null;

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Cannot send an Update for an object that has been deleted from the Fediverse. Re-publish it instead.' );

		( new Post_Command() )->update( array( $post_id ), array() );
	}

	/**
	 * The same for the comment command: an Update for a soft-deleted comment must
	 * halt with the error rather than report success.
	 */
	public function test_comment_update_surfaces_outbox_error() {
		$post_id    = self::factory()->post->create( array( 'post_author' => 1 ) );
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'user_id'         => 1,
			)
		);
		set_wp_object_state( \get_comment( $comment_id ), ACTIVITYPUB_OBJECT_STATE_DELETED );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Cannot send an Update for an object that has been deleted from the Fediverse. Re-publish it instead.' );

		( new Comment_Command() )->update( array( $comment_id ), array() );
	}

	/**
	 * Positive control: a normal Update does not halt and reports success, so the
	 * error branch above is genuinely conditional on the WP_Error.
	 */
	public function test_post_update_reports_success_without_error() {
		$post_id = self::factory()->post->create( array( 'post_author' => 1 ) );

		\WP_CLI::$last_success = null;

		( new Post_Command() )->update( array( $post_id ), array() );

		$this->assertStringContainsString( 'queued', (string) \WP_CLI::$last_success, 'A successful Update must report success.' );
	}
}
