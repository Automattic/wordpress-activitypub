<?php
/**
 * Test file for Attachment transformer.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Transformer\Attachment;
use WP_UnitTestCase;

/**
 * Test class for Attachment Transformer.
 *
 * @coversDefaultClass \Activitypub\Transformer\Attachment
 */
class Test_Attachment extends WP_UnitTestCase {
	/**
	 * Test attachment ID.
	 *
	 * @var int
	 */
	protected static $attachment_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		// Create test attachment.
		self::$attachment_id = $factory->attachment->create_object(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image',
				'post_content'   => 'Test Image Description',
			)
		);
	}

	/**
	 * Clean up after tests.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$attachment_id, true );
	}

	/**
	 * Test get_type method.
	 *
	 * @covers ::get_type
	 */
	public function test_get_type() {
		$attachment  = get_post( self::$attachment_id );
		$transformer = new Attachment( $attachment );
		$type        = $this->get_protected_method( $transformer, 'get_type' );

		$this->assertEquals( 'Note', $type );
	}

	/**
	 * Test get_attachment method with different mime types.
	 *
	 * @covers ::get_attachment
	 * @dataProvider provide_mime_types
	 *
	 * @param string $mime_type The mime type of the attachment.
	 * @param string $expected_type The expected type of the attachment.
	 */
	public function test_get_attachment( $mime_type, $expected_type ) {
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => $mime_type,
			)
		);

		$attachment  = get_post( $attachment_id );
		$transformer = new Attachment( $attachment );
		$result      = $this->get_protected_method( $transformer, 'get_attachment' );

		$this->assertIsArray( $result );
		$this->assertEquals( $expected_type, $result['type'] );
		$this->assertEquals( $mime_type, $result['mediaType'] );
		$this->assertArrayHasKey( 'url', $result );

		wp_delete_post( $attachment_id, true );
	}

	/**
	 * Test get_attachment method with alt text.
	 *
	 * @covers ::get_attachment
	 */
	public function test_get_attachment_with_alt() {
		$alt_text = 'Test Alt Text';
		update_post_meta( self::$attachment_id, '_wp_attachment_image_alt', $alt_text );

		$attachment  = get_post( self::$attachment_id );
		$transformer = new Attachment( $attachment );
		$result      = $this->get_protected_method( $transformer, 'get_attachment' );

		$this->assertArrayHasKey( 'name', $result );
		$this->assertEquals( $alt_text, $result['name'] );
	}

	/**
	 * Test to_object method.
	 *
	 * @covers ::to_object
	 */
	public function test_to_object() {
		$attachment  = get_post( self::$attachment_id );
		$transformer = new Attachment( $attachment );
		$object      = $transformer->to_object();

		$this->assertEquals( 'Note', $object->get_type() );
		$this->assertEquals( home_url( '?p=' . self::$attachment_id ), $object->get_id() );
		$this->assertNull( $object->get_name() );
	}

	/**
	 * Data provider for mime types.
	 *
	 * @return array Test data.
	 */
	public function provide_mime_types() {
		return array(
			'image' => array(
				'image/jpeg',
				'Image',
			),
			'audio' => array(
				'audio/mpeg',
				'Audio',
			),
			'video' => array(
				'video/mp4',
				'Video',
			),
			'pdf'   => array(
				'application/pdf',
				'',
			),
			'text'  => array(
				'text/plain',
				'',
			),
		);
	}

	/**
	 * Helper method to access protected methods.
	 *
	 * @param object $obj     Object instance.
	 * @param string $method_name Method name.
	 * @param array  $parameters Optional parameters.
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
