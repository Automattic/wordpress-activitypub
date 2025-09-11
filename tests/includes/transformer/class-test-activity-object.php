<?php
/**
 * Test file for Activity Object transformer.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Activity\Base_Object;
use Activitypub\Transformer\Activity_Object;
use WP_UnitTestCase;

/**
 * Test class for Activity Object Transformer.
 *
 * @coversDefaultClass \Activitypub\Transformer\Activity_Object
 */
class Test_Activity_Object extends WP_UnitTestCase {
	/**
	 * Test object instance.
	 *
	 * @var Base_Object
	 */
	protected $test_object;

	/**
	 * Get the locale.
	 *
	 * @return string The locale.
	 */
	private function get_locale() {
		return \strtolower( \strtok( \get_locale(), '_-' ) );
	}

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();

		$this->test_object = new Base_Object();
		$this->test_object->set_content( 'Test content with @mention and another @mention2' );
		$this->test_object->set_summary( 'Test summary with @mention3' );
		$this->test_object->set_name( 'Test name' );
		$this->test_object->set_type( 'Note' );
	}

	/**
	 * Test to_object method.
	 *
	 * @covers ::to_object
	 */
	public function test_to_object() {
		$transformer = new Activity_Object( $this->test_object );
		$object      = $transformer->to_object();

		$this->assertInstanceOf( Base_Object::class, $object );
		$this->assertEquals( 'Test content with @mention and another @mention2', $object->get_content() );
		$this->assertEquals( 'Test name', $object->get_name() );
		$this->assertEquals( 'Note', $object->get_type() );
	}

	/**
	 * Test get_mentions method.
	 *
	 * @covers ::get_mentions
	 */
	public function test_get_mentions() {
		add_filter(
			'activitypub_extract_mentions',
			function () {
				return array(
					'@mention'  => 'https://example.com/@mention',
					'@mention2' => 'https://example.com/@mention2',
					'@mention3' => 'https://example.com/@mention3',
				);
			},
			10,
			2
		);

		$transformer = new Activity_Object( $this->test_object );
		$mentions    = $this->get_protected_method( $transformer, 'get_mentions' );

		$this->assertIsArray( $mentions );
		$this->assertCount( 3, $mentions );
		$this->assertEquals( 'https://example.com/@mention', $mentions['@mention'] );

		remove_all_filters( 'activitypub_extract_mentions' );
	}

	/**
	 * Test get_cc method.
	 */
	public function test_get_cc() {
		add_filter(
			'activitypub_extract_mentions',
			function () {
				return array(
					'@mention'  => 'https://example.com/@mention',
					'@mention2' => 'https://example.com/@mention2',
				);
			}
		);

		$transformer = new Activity_Object( $this->test_object );
		$object      = $transformer->to_object();
		$cc          = $object->get_cc();

		$this->assertIsArray( $cc );
		$this->assertCount( 2, $cc );
		$this->assertContains( 'https://example.com/@mention', $cc );
		$this->assertContains( 'https://example.com/@mention2', $cc );

		remove_all_filters( 'activitypub_extract_mentions' );
	}

	/**
	 * Test get_content_map method.
	 *
	 * @covers ::get_content_map
	 */
	public function test_get_content_map() {
		$transformer = new Activity_Object( $this->test_object );
		$content_map = $this->get_protected_method( $transformer, 'get_content_map' );

		$this->assertIsArray( $content_map );
		$this->assertArrayHasKey( $this->get_locale(), $content_map );
		$this->assertEquals( 'Test content with @mention and another @mention2', $content_map[ $this->get_locale() ] );

		// Test with empty content.
		$this->test_object->set_content( '' );
		$content_map = $this->get_protected_method( $transformer, 'get_content_map' );
		$this->assertNull( $content_map );
	}

	/**
	 * Test get_name_map method.
	 *
	 * @covers ::get_name_map
	 */
	public function test_get_name_map() {
		$transformer = new Activity_Object( $this->test_object );
		$name_map    = $this->get_protected_method( $transformer, 'get_name_map' );

		$this->assertIsArray( $name_map );
		$this->assertArrayHasKey( $this->get_locale(), $name_map );
		$this->assertEquals( 'Test name', $name_map[ $this->get_locale() ] );

		// Test with empty name.
		$this->test_object->set_name( '' );
		$name_map = $this->get_protected_method( $transformer, 'get_name_map' );
		$this->assertNull( $name_map );
	}

	/**
	 * Test get_tag method.
	 *
	 * @covers ::get_tag
	 */
	public function test_get_tag() {
		add_filter(
			'activitypub_extract_mentions',
			function () {
				return array(
					'@mention' => 'https://example.com/@mention',
				);
			}
		);

		$this->test_object->set_tag(
			array(
				array(
					'type' => 'Hashtag',
					'name' => '#test',
				),
			)
		);

		$transformer = new Activity_Object( $this->test_object );
		$tags        = $this->get_protected_method( $transformer, 'get_tag' );

		$this->assertIsArray( $tags );
		$this->assertCount( 2, $tags );

		// Test hashtag.
		$this->assertEquals( 'Hashtag', $tags[0]['type'] );
		$this->assertEquals( '#test', $tags[0]['name'] );

		// Test mention.
		$this->assertEquals( 'Mention', $tags[1]['type'] );
		$this->assertEquals( '@mention', $tags[1]['name'] );
		$this->assertEquals( 'https://example.com/@mention', $tags[1]['href'] );

		remove_all_filters( 'activitypub_extract_mentions' );
	}

	/**
	 * Helper method to access protected methods.
	 *
	 * @param object $obj         Object instance.
	 * @param string $method_name Method name.
	 * @param array  $parameters  Optional parameters.
	 *
	 * @return mixed Method result.
	 */
	protected function get_protected_method( $obj, $method_name, $parameters = array() ) {
		$reflection = new \ReflectionClass( get_class( $obj ) );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $obj, $parameters );
	}
}
