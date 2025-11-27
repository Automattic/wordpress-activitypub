<?php
/**
 * Test file for Tag Transformer.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Transformer\Factory;
use Activitypub\Transformer\Tag;

/**
 * Test class for Tag Transformer.
 *
 * @coversDefaultClass \Activitypub\Transformer\Tag
 */
class Test_Tag extends \WP_UnitTestCase {
	/**
	 * Test term ID.
	 *
	 * @var int
	 */
	protected static $term_id;

	/**
	 * Create fake data before tests run.
	 *
	 * @param \WP_UnitTest_Factory $factory Helper that creates fake data.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		// Create a test tag.
		$term          = $factory->term->create_and_get(
			array(
				'taxonomy' => 'post_tag',
				'name'     => 'Test Tag',
				'slug'     => 'test-tag',
			)
		);
		self::$term_id = $term->term_id;
	}

	/**
	 * Test get_transformer with WP_Term.
	 *
	 * @covers \Activitypub\Transformer\Factory::get_transformer
	 */
	public function test_get_transformer_term() {
		$term        = get_term( self::$term_id );
		$transformer = Factory::get_transformer( $term );

		$this->assertInstanceOf( Tag::class, $transformer );
	}

	/**
	 * Test to_object method.
	 *
	 * @covers ::to_object
	 */
	public function test_to_object() {
		$term        = get_term( self::$term_id );
		$transformer = new Tag( $term );
		$object      = $transformer->to_object();

		// Should return a Base_Object.
		$this->assertInstanceOf( \Activitypub\Activity\Base_Object::class, $object );

		// Check ActivityStreams context.
		$this->assertEquals( 'https://www.w3.org/ns/activitystreams', $object->{'@context'} );

		// Check type is OrderedCollection.
		$this->assertEquals( 'OrderedCollection', $object->get_type() );

		// Check ID is the term link.
		$expected_url = get_term_link( $term );
		$this->assertEquals( $expected_url, $object->get_id() );
	}

	/**
	 * Test to_id method.
	 *
	 * @covers ::to_id
	 */
	public function test_to_id() {
		$term        = get_term( self::$term_id );
		$transformer = new Tag( $term );
		$id          = $transformer->to_id();

		// Should return the term link.
		$expected_url = get_term_link( $term );
		$this->assertEquals( $expected_url, $id );
	}

	/**
	 * Test with category taxonomy.
	 */
	public function test_category_term() {
		$category = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'category',
				'name'     => 'Test Category',
				'slug'     => 'test-category',
			)
		);

		$transformer = new Tag( $category );
		$object      = $transformer->to_object();

		$this->assertEquals( 'OrderedCollection', $object->get_type() );
		$this->assertEquals( get_term_link( $category ), $object->get_id() );
	}
}
