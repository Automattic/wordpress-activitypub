<?php
/**
 * Test file for Advanced_Settings_Fields.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Options;
use Activitypub\WP_Admin\Advanced_Settings_Fields;

/**
 * Test class for Advanced_Settings_Fields.
 *
 * @coversDefaultClass \Activitypub\WP_Admin\Advanced_Settings_Fields
 */
class Test_Advanced_Settings_Fields extends \WP_UnitTestCase {
	/**
	 * Tear down after tests.
	 */
	public function tear_down() {
		\delete_option( 'activitypub_distribution_mode' );
		\delete_option( 'activitypub_custom_batch_size' );
		\delete_option( 'activitypub_custom_batch_pause' );

		parent::tear_down();
	}

	/**
	 * Test the distribution mode field renders a radio for every mode plus 'custom'.
	 *
	 * Guards against adding a new preset to Options::get_distribution_modes()
	 * without exposing it in the admin UI.
	 *
	 * @covers ::render_distribution_mode_field
	 */
	public function test_render_distribution_mode_field_covers_all_modes() {
		\ob_start();
		Advanced_Settings_Fields::render_distribution_mode_field();
		$html = \ob_get_clean();

		$expected_keys = \array_merge( \array_keys( Options::get_distribution_modes() ), array( 'custom' ) );

		foreach ( $expected_keys as $key ) {
			$this->assertStringContainsString(
				'value="' . $key . '"',
				$html,
				\sprintf( 'Distribution mode field should render a radio for "%s"', $key )
			);
		}
	}

	/**
	 * Test the distribution mode field marks the stored mode as checked.
	 *
	 * @covers ::render_distribution_mode_field
	 */
	public function test_render_distribution_mode_field_checks_active_mode() {
		\update_option( 'activitypub_distribution_mode', 'eco' );

		\ob_start();
		Advanced_Settings_Fields::render_distribution_mode_field();
		$html = \ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/value="eco"\s+checked=/',
			$html,
			'Stored mode should be rendered as the checked radio'
		);
	}
}
