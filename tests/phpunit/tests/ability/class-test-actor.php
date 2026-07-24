<?php
/**
 * Test Actor abilities.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Ability;

use Activitypub\Ability\Actor;

/**
 * Test Actor abilities.
 *
 * @coversDefaultClass \Activitypub\Ability\Actor
 */
class Test_Actor extends \WP_UnitTestCase {

	/**
	 * The permission callback requires the activitypub capability.
	 *
	 * @covers ::permission_callback
	 */
	public function test_permission_callback_requires_capability() {
		\wp_set_current_user( 0 );
		$this->assertFalse( Actor::permission_callback() );

		$user = self::factory()->user->create_and_get();
		$user->add_cap( 'activitypub' );
		\wp_set_current_user( $user->ID );
		$this->assertTrue( Actor::permission_callback() );
	}

	/**
	 * The shared item schema exposes the common actor properties.
	 *
	 * @covers ::item_schema
	 */
	public function test_item_schema_properties() {
		$schema = Actor::item_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertSame(
			array( 'id', 'type', 'name', 'preferredUsername', 'followers', 'following', 'icon' ),
			\array_keys( $schema['properties'] )
		);
	}

	/**
	 * Maps the shared actor fields and omits detail-only fields.
	 *
	 * @covers ::to_array
	 */
	public function test_to_array_maps_common_fields() {
		$model = new \Activitypub\Activity\Actor();
		$model->set_id( 'https://example.com/users/alice' );
		$model->set_type( 'Person' );
		$model->set_name( 'Alice' );
		$model->set_preferred_username( 'alice' );
		$model->set_followers( 'https://example.com/users/alice/followers' );
		$model->set_following( 'https://example.com/users/alice/following' );
		$model->set_icon( array( 'type' => 'Image' ) );

		$array = Actor::to_array( $model );

		$this->assertSame( 'https://example.com/users/alice', $array['id'] );
		$this->assertSame( 'Person', $array['type'] );
		$this->assertSame( 'Alice', $array['name'] );
		$this->assertSame( 'alice', $array['preferredUsername'] );
		$this->assertSame( 'https://example.com/users/alice/followers', $array['followers'] );
		$this->assertSame( 'https://example.com/users/alice/following', $array['following'] );
		$this->assertSame( array( 'type' => 'Image' ), $array['icon'] );

		// Detail-only fields belong to get-actor, not the shared shape.
		$this->assertArrayNotHasKey( 'summary', $array );
		$this->assertArrayNotHasKey( 'inbox', $array );
		$this->assertArrayNotHasKey( 'outbox', $array );
	}
}
