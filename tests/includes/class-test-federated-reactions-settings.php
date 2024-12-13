<?php
/**
 * Test Federated Reactions Settings.
 *
 * @package ActivityPub
 */

namespace Activitypub\Tests;

use Activitypub\Federated_Reactions_Settings;

/**
 * Test Federated Reactions Settings.
 *
 * @coversDefaultClass \Activitypub\Federated_Reactions_Settings
 */
class Test_Federated_Reactions_Settings extends \WP_UnitTestCase {

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		parent::tear_down();
		delete_option( 'activitypub_reactions_enabled' );
	}

	/**
	 * Test initialization of hooks.
	 *
	 * @covers ::init
	 */
	public function test_init() {
		$this->assertEquals( 11, has_action( 'init', array( Federated_Reactions_Settings::class, 'register_post_meta' ) ) );
		$this->assertEquals( 10, has_action( 'admin_init', array( Federated_Reactions_Settings::class, 'register_settings' ) ) );
	}

	/**
	 * Test registration of post meta.
	 *
	 * @covers ::register_post_meta
	 */
	public function test_register_post_meta() {
		Federated_Reactions_Settings::register_post_meta();

		$post_type  = 'post';
		$registered = get_registered_meta_keys( 'post', $post_type );

		$this->assertArrayHasKey( 'activitypub_reactions_enabled', $registered );
		$this->assertTrue( $registered['activitypub_reactions_enabled']['show_in_rest'] );
		$this->assertTrue( $registered['activitypub_reactions_enabled']['single'] );
		$this->assertEquals( 'string', $registered['activitypub_reactions_enabled']['type'] );

		// Test sanitize callback.
		$sanitize_callback = $registered['activitypub_reactions_enabled']['sanitize_callback'];
		$this->assertEquals( '1', $sanitize_callback( '1' ) );
		$this->assertEquals( '0', $sanitize_callback( '0' ) );
		$this->assertEquals( '1', $sanitize_callback( 'invalid' ) );
	}

	/**
	 * Test registration of settings.
	 *
	 * @covers ::register_settings
	 */
	public function test_register_settings() {
		Federated_Reactions_Settings::register_settings();

		$registered = get_registered_settings();
		$this->assertArrayHasKey( 'activitypub_reactions_enabled', $registered );
		$this->assertEquals( 'boolean', $registered['activitypub_reactions_enabled']['type'] );
		$this->assertTrue( $registered['activitypub_reactions_enabled']['show_in_rest'] );
		$this->assertTrue( $registered['activitypub_reactions_enabled']['default'] );
	}

	/**
	 * Test rendering of the settings field.
	 *
	 * @covers ::render_reactions_enabled_field
	 */
	public function test_render_reactions_enabled_field() {
		// Test with default value.
		update_option( 'activitypub_reactions_enabled', '1' );
		ob_start();
		Federated_Reactions_Settings::render_reactions_enabled_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<input type="checkbox"', $output );
		$this->assertStringContainsString( 'name="activitypub_reactions_enabled"', $output );
		$this->assertStringContainsString( 'value="1"', $output );
		$this->assertStringContainsString( 'checked=\'checked\'', $output );

		// Test with disabled value.
		update_option( 'activitypub_reactions_enabled', '0' );
		ob_start();
		Federated_Reactions_Settings::render_reactions_enabled_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<input type="checkbox"', $output );
		$this->assertStringContainsString( 'name="activitypub_reactions_enabled"', $output );
		$this->assertStringContainsString( 'value="1"', $output );
		$this->assertStringNotContainsString( 'checked=\'checked\'', $output );
	}

	/**
	 * Test checking if reactions are enabled.
	 *
	 * @covers ::is_reactions_enabled
	 */
	public function test_is_reactions_enabled() {
		$post = self::factory()->post->create_and_get();

		// Test with default global setting.
		$this->assertTrue( Federated_Reactions_Settings::is_reactions_enabled( $post->ID ) );

		// Test with global setting disabled.
		update_option( 'activitypub_reactions_enabled', '0' );
		$this->assertFalse( Federated_Reactions_Settings::is_reactions_enabled( $post->ID ) );

		// Test with global setting enabled but post setting disabled.
		update_option( 'activitypub_reactions_enabled', '1' );
		update_post_meta( $post->ID, 'activitypub_reactions_enabled', '0' );
		$this->assertFalse( Federated_Reactions_Settings::is_reactions_enabled( $post->ID ) );
		
		update_option( 'activitypub_reactions_enabled', '0' );
		update_post_meta( $post->ID, 'activitypub_reactions_enabled', '1' );
		$this->assertTrue( Federated_Reactions_Settings::is_reactions_enabled( $post->ID ) );
	}
}
