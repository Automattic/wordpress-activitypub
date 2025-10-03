<?php
/**
 * Test file for Base Transformer.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Activity\Base_Object;
use Activitypub\Activity\Generic_Object;
use Activitypub\Transformer\Base;
use Activitypub\Transformer\Post;
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

	/**
	 * Test that the audience is set correctly.
	 *
	 * @dataProvider data_provider_set_audience
	 *
	 * @covers ::set_audience
	 *
	 * @param string $content_visibility The content visibility.
	 * @param array  $object_attributes  The object attributes.
	 * @param array  $expected_audience  The expected audience.
	 */
	public function test_set_audience( $content_visibility, $object_attributes, $expected_audience ) {
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Test content that is longer than the note length limit',
			)
		);
		$post    = \get_post( $post_id );

		$function = function ( $response, $url_or_object ) {
			if ( ! str_contains( $url_or_object, 'reply' ) ) {
				return null;
			}

			return array(
				'attributedTo' => $url_or_object,
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $function, 10, 2 );

		$getter_methods = array_map(
			function ( $k ) {
				return 'get_' . $k;
			},
			array_keys( $object_attributes )
		);

		$transformer = $this->getMockBuilder( Post::class )
			->setConstructorArgs( array( $post ) )
			->onlyMethods( $getter_methods )
			->getMock();

		$transformer->set_content_visibility( $content_visibility );

		foreach ( $object_attributes as $key => $value ) {
			$transformer->method( 'get_' . $key )->willReturn( $value );
		}

		$reflection = new \ReflectionObject( $transformer );
		$method     = $reflection->getMethod( 'set_audience' );
		$method->setAccessible( true );

		$transformed_object = $method->invoke( $transformer, new Generic_Object() );

		$this->assertEquals( $expected_audience['to'], $transformed_object->get_to() );
		$this->assertEquals( $expected_audience['cc'], $transformed_object->get_cc() );

		\wp_delete_post( $post_id );
		\remove_filter( 'activitypub_pre_http_get_remote_object', $function );
	}

	/**
	 * Data provider for test_set_audience.
	 *
	 * @return array[]
	 */
	public function data_provider_set_audience() {
		return array(
			array(
				ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
				array(
					'in_reply_to' => 'https://example.com/in-reply-to',
					'mentions'    => array(
						'https://example.com/mentions' => 'https://example.com/mentions',
					),
				),
				array(
					'to' => array(
						'https://www.w3.org/ns/activitystreams#Public',
					),
					'cc' => array(
						'https://example.com/mentions',
						'https://example.com/in-reply-to',
					),
				),
			),
			array(
				ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
				array(
					'in_reply_to' => 'https://example.com/in-reply-to',
					'mentions'    => array(
						'https://example.com/mentions' => 'https://example.com/mentions',
					),
				),
				array(
					'to' => array(
						'https://www.w3.org/ns/activitystreams#Public',
					),
					'cc' => array(
						'https://example.com/mentions',
						'https://example.com/in-reply-to',
					),
				),
			),
			array(
				ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC,
				array(
					'in_reply_to' => 'https://example.com/in-reply-to',
					'mentions'    => array(
						'https://example.com/mentions' => 'https://example.com/mentions',
					),
				),
				array(
					'cc' => array(
						'https://www.w3.org/ns/activitystreams#Public',
					),
					'to' => array(
						'https://example.com/mentions',
						'https://example.com/in-reply-to',
					),
				),
			),
			array(
				ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE,
				array(
					'in_reply_to' => 'https://example.com/in-reply-to',
					'mentions'    => array(
						'https://example.com/mentions' => 'https://example.com/mentions',
					),
				),
				array(
					'to' => array(
						'https://example.com/mentions',
						'https://example.com/in-reply-to',
					),
					'cc' => null,
				),
			),
		);
	}
}
