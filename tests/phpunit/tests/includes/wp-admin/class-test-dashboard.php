<?php
/**
 * Test file for Dashboard.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\WP_Admin\Dashboard;

/**
 * Test class for Dashboard.
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Dashboard
 */
class Test_Dashboard extends \WP_UnitTestCase {
	/**
	 * Every widget gets its own element IDs, so a label never points into another widget.
	 *
	 * @covers ::add_dashboard_widgets
	 * @covers ::render_author_profile_widget
	 * @covers ::render_blog_profile_widget
	 */
	public function test_widgets_do_not_share_ids() {
		global $wp_meta_boxes;

		require_once ABSPATH . 'wp-admin/includes/dashboard.php';

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		\wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		\set_current_screen( 'dashboard' );

		// The news widget loads a remote feed.
		$no_request = static function () {
			return new \WP_Error( 'http_request_not_executed', 'No remote requests in tests.' );
		};
		\add_filter( 'pre_http_request', $no_request );

		Dashboard::add_dashboard_widgets();

		$html = '';
		foreach ( $wp_meta_boxes['dashboard'] as $priorities ) {
			foreach ( $priorities as $boxes ) {
				foreach ( $boxes as $box ) {
					\ob_start();
					\call_user_func( $box['callback'], null, $box );
					$html .= \ob_get_clean();
				}
			}
		}

		\remove_filter( 'pre_http_request', $no_request );
		\delete_option( 'activitypub_actor_mode' );

		$this->assertArrayHasKey( 'activitypub_profile', $wp_meta_boxes['dashboard']['normal']['core'] );
		$this->assertArrayHasKey( 'activitypub_blog_profile', $wp_meta_boxes['dashboard']['normal']['core'] );

		\preg_match_all( '/\sid="([^"]+)"/', $html, $ids );
		$this->assertSame( \array_unique( $ids[1] ), $ids[1], 'An element ID is used twice.' );

		// Every label points to an input that exists.
		\preg_match_all( '/<label for="([^"]+)"/', $html, $labels );
		$this->assertSame( array(), \array_diff( $labels[1], $ids[1] ) );
	}
}
