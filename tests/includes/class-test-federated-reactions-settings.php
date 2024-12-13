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
		$GLOBALS['wp_meta_boxes'] = array();
		$_POST                    = array();
	}

	/**
	 * Test initialization of hooks.
	 *
	 * @covers ::init
	 */
	public function test_init() {
		$this->assertEquals( 11, has_action( 'init', array( Federated_Reactions_Settings::class, 'register_postmeta' ) ) );
		$this->assertEquals( 10, has_action( 'admin_init', array( Federated_Reactions_Settings::class, 'register_settings' ) ) );
		$this->assertEquals( 10, has_action( 'add_meta_boxes', array( Federated_Reactions_Settings::class, 'add_meta_box' ) ) );
		$this->assertEquals( 10, has_action( 'save_post', array( Federated_Reactions_Settings::class, 'meta_box_save' ) ) );
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
	 * Test adding meta box.
	 *
	 * @covers ::add_meta_box
	 */
	public function test_add_meta_box() {
		global $wp_meta_boxes;

		Federated_Reactions_Settings::add_meta_box();

		$this->assertArrayHasKey( 'post', $wp_meta_boxes );
		$this->assertArrayHasKey( 'side', $wp_meta_boxes['post'] );
		$this->assertArrayHasKey( 'default', $wp_meta_boxes['post']['side'] );
		$this->assertArrayHasKey( 'activitypub_reactions', $wp_meta_boxes['post']['side']['default'] );
	}

	/**
	 * Test rendering of meta box.
	 *
	 * @covers ::render_meta_box
	 */
	public function test_render_meta_box() {
		$post = self::factory()->post->create_and_get();

		// Test with default value (no meta set).
		update_option( 'activitypub_reactions_enabled', '1' );
		ob_start();
		Federated_Reactions_Settings::render_meta_box( $post );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<input type="checkbox"', $output );
		$this->assertStringContainsString( 'name="activitypub_reactions_enabled"', $output );
		$this->assertStringContainsString( 'value="1"', $output );
		$this->assertStringContainsString( 'checked=\'checked\'', $output );

		// Test with meta value set.
		update_post_meta( $post->ID, 'activitypub_reactions_enabled', '0' );
		ob_start();
		Federated_Reactions_Settings::render_meta_box( $post );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<input type="checkbox"', $output );
		$this->assertStringContainsString( 'name="activitypub_reactions_enabled"', $output );
		$this->assertStringContainsString( 'value="1"', $output );
		$this->assertStringNotContainsString( 'checked=\'checked\'', $output );
	}

	/**
	 * Test saving meta box data.
	 *
	 * @covers ::meta_box_save
	 */
	public function test_meta_box_save() {
		$post    = self::factory()->post->create_and_get();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Test with missing nonce.
		$_POST = array();
		Federated_Reactions_Settings::meta_box_save( $post->ID );
		$this->assertEmpty( get_post_meta( $post->ID, 'activitypub_reactions_enabled', true ) );

		// Test with invalid nonce.
		$_POST = array(
			'activitypub_reactions_meta_box_nonce' => 'invalid',
		);
		Federated_Reactions_Settings::meta_box_save( $post->ID );
		$this->assertEmpty( get_post_meta( $post->ID, 'activitypub_reactions_enabled', true ) );

		// Test with valid data - checkbox checked.
		$_POST = array(
			'activitypub_reactions_meta_box_nonce' => wp_create_nonce( 'activitypub_reactions_meta_box' ),
			'activitypub_reactions_enabled'        => '1',
		);
		Federated_Reactions_Settings::meta_box_save( $post->ID );
		$this->assertEquals( '1', get_post_meta( $post->ID, 'activitypub_reactions_enabled', true ) );

		// Test with valid data - checkbox unchecked.
		$_POST = array(
			'activitypub_reactions_meta_box_nonce' => wp_create_nonce( 'activitypub_reactions_meta_box' ),
		);
		Federated_Reactions_Settings::meta_box_save( $post->ID );
		$this->assertEquals( '0', get_post_meta( $post->ID, 'activitypub_reactions_enabled', true ) );
	}

	/**
	 * Test checking if reactions are enabled.
	 *
	 * @covers ::is_reactions_enabled
	 */
	public function test_is_reactions_enabled() {
		$post = self::factory()->post->create_and_get();

		// Test with default global setting.
		update_option( 'activitypub_reactions_enabled', '1' );
		$this->assertTrue( Federated_Reactions_Settings::is_reactions_enabled( $post->ID ) );

		// Test with disabled global setting.
		update_option( 'activitypub_reactions_enabled', '0' );
		$this->assertFalse( Federated_Reactions_Settings::is_reactions_enabled( $post->ID ) );

		// Test with post meta overriding global setting.
		update_option( 'activitypub_reactions_enabled', '1' );
		update_post_meta( $post->ID, 'activitypub_reactions_enabled', '0' );
		$this->assertFalse( Federated_Reactions_Settings::is_reactions_enabled( $post->ID ) );

		update_option( 'activitypub_reactions_enabled', '0' );
		update_post_meta( $post->ID, 'activitypub_reactions_enabled', '1' );
		$this->assertTrue( Federated_Reactions_Settings::is_reactions_enabled( $post->ID ) );
	}
}
