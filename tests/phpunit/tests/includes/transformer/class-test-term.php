<?php
/**
 * Test file for Term Transformer.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Transformer;

use Activitypub\Transformer\Factory;
use Activitypub\Transformer\Term;

/**
 * Test class for Term Transformer.
 *
 * @coversDefaultClass \Activitypub\Transformer\Term
 */
class Test_Term extends \WP_UnitTestCase {
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
				'name'     => 'Test Term',
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

		$this->assertInstanceOf( Term::class, $transformer );
	}

	/**
	 * Test to_object method.
	 *
	 * @covers ::to_object
	 */
	public function test_to_object() {
		$term        = get_term( self::$term_id );
		$transformer = new Term( $term );
		$object      = $transformer->to_object();

		// Should return a Base_Object.
		$this->assertInstanceOf( \Activitypub\Activity\Base_Object::class, $object );

		// Check ActivityStreams context.
		$this->assertEquals( 'https://www.w3.org/ns/activitystreams', $object->{'@context'} );

		// Check type is OrderedCollection.
		$this->assertEquals( 'OrderedCollection', $object->get_type() );

		// Check ID uses stable term_id-based URL.
		$expected_id = \add_query_arg( 'term_id', $term->term_id, \home_url( '/' ) );
		$this->assertEquals( $expected_id, $object->get_id() );

		// Check URL is the term link.
		$expected_url = get_term_link( $term );
		$this->assertEquals( $expected_url, $object->get_url() );
	}

	/**
	 * Test to_id method.
	 *
	 * @covers ::to_id
	 */
	public function test_to_id() {
		$term        = get_term( self::$term_id );
		$transformer = new Term( $term );
		$id          = $transformer->to_id();

		// Should return stable term_id-based URL.
		$expected_id = \add_query_arg( 'term_id', $term->term_id, \home_url( '/' ) );
		$this->assertEquals( $expected_id, $id );
	}

	/**
	 * Test get_id returns stable ID.
	 *
	 * @covers ::get_id
	 */
	public function test_get_id() {
		$term        = get_term( self::$term_id );
		$transformer = new Term( $term );

		$expected_id = \add_query_arg( 'term_id', $term->term_id, \home_url( '/' ) );
		$this->assertEquals( $expected_id, $transformer->get_id() );
	}

	/**
	 * Test get_url returns term link.
	 *
	 * @covers ::get_url
	 */
	public function test_get_url() {
		$term        = get_term( self::$term_id );
		$transformer = new Term( $term );

		$expected_url = get_term_link( $term );
		$this->assertEquals( $expected_url, $transformer->get_url() );
	}

	/**
	 * Test with category taxonomy.
	 *
	 * @covers ::to_object
	 * @covers ::get_id
	 * @covers ::get_url
	 */
	public function test_category_term() {
		$category = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'category',
				'name'     => 'Test Category',
				'slug'     => 'test-category',
			)
		);

		$transformer = new Term( $category );
		$object      = $transformer->to_object();

		$this->assertEquals( 'OrderedCollection', $object->get_type() );

		// ID should use stable term_id-based URL.
		$expected_id = \add_query_arg( 'term_id', $category->term_id, \home_url( '/' ) );
		$this->assertEquals( $expected_id, $object->get_id() );

		// URL should be the term link.
		$this->assertEquals( get_term_link( $category ), $object->get_url() );
	}
}
