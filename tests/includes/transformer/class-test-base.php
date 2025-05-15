<?php
/**
 * Test file for Base Transformer.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Activity\Base_Object;
use Activitypub\Transformer\Base;

/**
 * Test class for Base Transformer.
 *
 * @coversDefaultClass \Activitypub\Transformer\Base
 */
class Test_Base extends \WP_UnitTestCase {
	/**
	 * Test that false values are properly set in object properties.
	 *
	 * @covers ::transform_object_properties
	 */
	public function test_transform_object_properties_with_false_value() {
		// Create a mock transformer that extends Base and has a method returning false.
		$mock_transformer = new class('test') extends Base {
			/**
			 * Get a property that returns false.
			 *
			 * @return bool Always returns false.
			 */
			public function get_sensitive() {
				return false;
			}

			/**
			 * Public wrapper to test the protected transform_object_properties method.
			 *
			 * @param Base_Object $activity_object The ActivityPub Object.
			 *
			 * @return Base_Object|\WP_Error The transformed ActivityPub Object or WP_Error on failure.
			 */
			public function test_transform_properties( $activity_object ) {
				return $this->transform_object_properties( $activity_object );
			}
		};

		// Create a test object that has 'sensitive' in its var keys.
		$test_object = new class() extends Base_Object {
			/**
			 * Whether the content is sensitive.
			 *
			 * @var bool|null
			 */
			protected $sensitive;

			/**
			 * Override get_object_var_keys to include 'sensitive'.
			 *
			 * @return array The keys of the object vars.
			 */
			public function get_object_var_keys() {
				return array( 'sensitive' );
			}
		};

		// Transform the object.
		$transformed_object = $mock_transformer->test_transform_properties( $test_object );

		// Assert that the sensitive property could be set to false.
		$this->assertFalse( $transformed_object->get_sensitive(), 'The sensitive property should be set to false.' );
	}
}
