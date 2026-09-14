<?php
/**
 * Test file for Activitypub.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activitypub;
use Activitypub\Collection\Outbox;
use Activitypub\Event_Stream;
use Activitypub\Integration\Opengraph;
use Activitypub\OAuth\Server;
use Activitypub\Relay;

/**
 * Test class for Activitypub.
 *
 * @coversDefaultClass \Activitypub\Activitypub
 */
class Test_Activitypub extends \WP_UnitTestCase {
	/**
	 * Test environment.
	 */
	public function test_test_env() {
		$this->assertEquals( 'production', \wp_get_environment_type() );
	}

	/**
	 * Setting-dependent subsystems are registered on `init` whether or not their setting is on.
	 *
	 * The bootstrap ran `plugin_init()` with every feature setting at its default, off. A gate
	 * on the option at `plugins_loaded` would have left these unregistered, and on a multisite
	 * host that switches to the site afterwards there is no second chance to add them.
	 *
	 * @dataProvider setting_dependent_init_provider
	 *
	 * @param string      $subsystem The class whose `init()` has to be registered.
	 * @param string|null $option    A setting that is off by default, to show the registration does not depend on it.
	 */
	public function test_setting_dependent_subsystems_are_registered_regardless_of_the_setting( $subsystem, $option = null ) {
		if ( $option ) {
			$this->assertFalse( \get_option( $option ), 'The setting must be off for this to prove anything.' );
		}

		$this->assertNotFalse( \has_action( 'init', array( $subsystem, 'init' ) ), "$subsystem::init() must be registered on init." );
	}

	/**
	 * Data provider for the setting-dependent subsystems.
	 *
	 * OpenGraph is on by default, so its row only proves that the integration is deferred to
	 * `init` at all instead of being initialized straight from `plugins_loaded`.
	 *
	 * @return array[]
	 */
	public function setting_dependent_init_provider() {
		return array(
			'event stream' => array( Event_Stream::class, 'activitypub_api' ),
			'oauth server' => array( Server::class, 'activitypub_api' ),
			'relay'        => array( Relay::class, 'activitypub_relay_mode' ),
			'opengraph'    => array( Opengraph::class ),
		);
	}

	/**
	 * Test post type support.
	 *
	 * @covers ::init
	 */
	public function test_post_type_support() {
		\add_post_type_support( 'post', 'activitypub' );
		\add_post_type_support( 'page', 'activitypub' );

		$this->assertContains( 'post', \get_post_types_by_support( 'activitypub' ) );
		$this->assertContains( 'page', \get_post_types_by_support( 'activitypub' ) );
	}

	/**
	 * Test activity type meta sanitization.
	 *
	 * @dataProvider activity_meta_sanitization_provider
	 * @covers \Activitypub\Post_Types::register_outbox_post_type
	 *
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @param mixed  $expected   Expected value for invalid meta value.
	 */
	public function test_activity_meta_sanitization( $meta_key, $meta_value, $expected ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => Outbox::POST_TYPE,
				'meta_input' => array( $meta_key => $meta_value ),
			)
		);

		$this->assertEquals( $meta_value, \get_post_meta( $post_id, $meta_key, true ) );

		\wp_update_post(
			array(
				'ID'         => $post_id,
				'meta_input' => array( $meta_key => 'InvalidType' ),
			)
		);
		$this->assertEquals( $expected, \get_post_meta( $post_id, $meta_key, true ) );
	}

	/**
	 * Data provider for test_activity_meta_sanitization.
	 *
	 * @return array
	 */
	public function activity_meta_sanitization_provider() {
		return array(
			array( '_activitypub_activity_type', 'Create', 'Announce' ),
			array( '_activitypub_activity_actor', 'user', 'user' ),
			array( '_activitypub_activity_actor', 'blog', 'user' ),
		);
	}
}
