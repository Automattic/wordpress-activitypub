<?php
/**
 * Test file for Feature_Authorization extended object.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Activity\Extended_Object;

use Activitypub\Activity\Extended_Object\Feature_Authorization;

/**
 * Test class for Feature_Authorization.
 *
 * @coversDefaultClass \Activitypub\Activity\Extended_Object\Feature_Authorization
 *
 * @group activitypub
 */
class Test_Feature_Authorization extends \WP_UnitTestCase {
	/**
	 * Test that the type is set to FeatureAuthorization.
	 */
	public function test_type_is_feature_authorization() {
		$object = new Feature_Authorization();
		$this->assertSame( 'FeatureAuthorization', $object->get_type() );
	}

	/**
	 * Test that interactingObject and interactionTarget round-trip.
	 */
	public function test_interaction_properties_round_trip() {
		$object = new Feature_Authorization();
		$object->set_id( 'https://example.com/users/alice/feature-stamps/12' );
		$object->set_interacting_object( 'https://other.example.com/users/bob/featured/23' );
		$object->set_interaction_target( 'https://example.com/users/alice' );

		$array = $object->to_array();

		$this->assertSame( 'FeatureAuthorization', $array['type'] );
		$this->assertSame( 'https://other.example.com/users/bob/featured/23', $array['interactingObject'] );
		$this->assertSame( 'https://example.com/users/alice', $array['interactionTarget'] );
	}

	/**
	 * Test that the JSON-LD context includes the FEP-7aa9 namespace and
	 * the gts:-namespaced stamp link properties.
	 */
	public function test_json_ld_context_includes_fep_7aa9() {
		$object = new Feature_Authorization();
		$array  = $object->to_array();

		$found = false;
		foreach ( (array) $array['@context'] as $entry ) {
			if ( is_array( $entry ) && isset( $entry['FeatureAuthorization'] ) ) {
				$this->assertSame( 'https://w3id.org/fep/7aa9#FeatureAuthorization', $entry['FeatureAuthorization'] );
				$this->assertSame( 'https://gotosocial.org/ns#', $entry['gts'] );
				$this->assertSame( 'gts:interactingObject', $entry['interactingObject']['@id'] );
				$this->assertSame( '@id', $entry['interactingObject']['@type'] );
				$this->assertSame( 'gts:interactionTarget', $entry['interactionTarget']['@id'] );
				$this->assertSame( '@id', $entry['interactionTarget']['@type'] );
				$found = true;
			}
		}
		$this->assertTrue( $found, 'JSON-LD context must include FeatureAuthorization mapping.' );
	}
}
