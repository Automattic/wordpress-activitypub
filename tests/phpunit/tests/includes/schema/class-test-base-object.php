<?php
/**
 * Test file for the Base_Object schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Base_Object;

/**
 * Test class for the Base_Object schema.
 *
 * @coversDefaultClass \Activitypub\Schema\Base_Object
 */
class Test_Base_Object extends \WP_UnitTestCase {

	/**
	 * Every schema uses only keywords core validates.
	 *
	 * Core ignores unknown keywords without an error, so a `$ref` or an `allOf` would pass everything.
	 *
	 * @covers ::get_schema
	 */
	public function test_schemas_use_only_core_keywords() {
		// `arg_options` is not checked: core merges it into the route arg (`rest_get_endpoint_args_for_schema()`).
		$allowed = \array_merge( \rest_get_allowed_schema_keywords(), array( 'required', 'arg_options' ) );

		// Every schema class, so a new one is checked too.
		foreach ( \glob( ACTIVITYPUB_PLUGIN_DIR . 'includes/schema/class-*.php' ) as $file ) {
			$name = 'Activitypub\\Schema\\' . \str_replace( ' ', '_', \ucwords( \str_replace( '-', ' ', \basename( $file, '.php' ) ) ) );
			$name = \str_replace( 'Class_', '', $name );

			if ( ! \is_a( $name, Base_Object::class, true ) || ! ( new \ReflectionClass( $name ) )->isInstantiable() ) {
				continue;
			}

			$schema = new $name();

			foreach ( $this->get_keywords( $schema->get_schema() ) as $keyword ) {
				$this->assertContains( $keyword, $allowed, \sprintf( 'The %s schema uses "%s".', $name, $keyword ) );
			}
		}
	}

	/**
	 * Collect the keywords of a schema, without the property names.
	 *
	 * @param array $schema The schema.
	 *
	 * @return string[] The keywords.
	 */
	private function get_keywords( $schema ) {
		$keywords = \array_keys( $schema );
		unset( $schema['arg_options'] );

		foreach ( $schema['properties'] ?? array() as $property ) {
			$keywords = \array_merge( $keywords, $this->get_keywords( $property ) );
		}

		foreach ( $schema['anyOf'] ?? array() as $option ) {
			$keywords = \array_merge( $keywords, $this->get_keywords( $option ) );
		}

		if ( isset( $schema['items'] ) && \is_array( $schema['items'] ) ) {
			$keywords = \array_merge( $keywords, $this->get_keywords( $schema['items'] ) );
		}

		return $keywords;
	}

	/**
	 * Test validate.
	 *
	 * @dataProvider validate_provider
	 * @covers ::validate
	 * @covers ::get_schema
	 *
	 * @param array $data     The remote data.
	 * @param bool  $expected Whether it is valid.
	 */
	public function test_validate( $data, $expected ) {
		$this->assertSame( $expected, ! \is_wp_error( ( new Base_Object( $data ) )->validate() ) );
	}

	/**
	 * Data provider for test_validate.
	 *
	 * @return array The remote data and whether it is valid.
	 */
	public function validate_provider() {
		$note = array(
			'id'           => 'https://example.com/notes/1',
			'type'         => 'Note',
			'attributedTo' => 'https://example.com/actor',
		);

		return array(
			'note'               => array( $note, true ),
			'without id'         => array( \array_diff_key( $note, array( 'id' => true ) ), true ),
			'without type'       => array( \array_diff_key( $note, array( 'type' => true ) ), false ),
			'id not a uri'       => array( \array_merge( $note, array( 'id' => 'note-1' ) ), false ),
			'author as object'   => array( \array_merge( $note, array( 'attributedTo' => array( 'id' => 'https://example.com/actor' ) ) ), true ),
			'authors as list'    => array( \array_merge( $note, array( 'attributedTo' => array( 'https://example.com/actor', 'https://example.com/channel' ) ) ), true ),
			'author not a uri'   => array( \array_merge( $note, array( 'attributedTo' => 'alice' ) ), false ),
			'audience not a uri' => array( \array_merge( $note, array( 'cc' => array( 'everyone' ) ) ), false ),
		);
	}

	/**
	 * Valid data is normalized: language maps resolve to the site's language, an audience becomes a list.
	 *
	 * @covers ::sanitize
	 */
	public function test_sanitize() {
		$locale = static function () {
			return 'de_DE';
		};
		\add_filter( 'locale', $locale );

		$note = ( new Base_Object(
			array(
				'id'         => 'https://example.com/notes/1',
				'type'       => 'Note',
				'to'         => 'https://www.w3.org/ns/activitystreams#Public',
				'content'    => 'Hello',
				'contentMap' => array(
					'en' => 'Hello',
					'de' => 'Hallo',
				),
			)
		) )->sanitize();

		\remove_filter( 'locale', $locale );

		$this->assertSame( 'Hallo', $note['content'] );
		$this->assertSame( array( 'https://www.w3.org/ns/activitystreams#Public' ), $note['to'] );
	}

	/**
	 * A property's own required list stays with it: only the marker of `required()` moves to the schema.
	 *
	 * @covers ::get_schema
	 */
	public function test_nested_required_list_stays() {
		$class = \get_class(
			new class() extends Base_Object {
				/**
				 * The properties: an optional details object whose label is required.
				 *
				 * @return array The properties, keyed by name.
				 */
				protected function get_properties() {
					$properties = parent::get_properties();

					$properties['details'] = array(
						'type'       => 'object',
						'properties' => array( 'label' => array( 'type' => 'string' ) ),
						'required'   => array( 'label' ),
					);

					return $properties;
				}
			}
		);
		$note  = array(
			'id'   => 'https://example.com/note/1',
			'type' => 'Note',
		);

		$this->assertTrue( ( new $class( $note ) )->validate(), 'details is optional' );
		$this->assertWPError( ( new $class( \array_merge( $note, array( 'details' => array() ) ) ) )->validate(), 'label is required in details' );
		$this->assertTrue( ( new $class( \array_merge( $note, array( 'details' => array( 'label' => 'x' ) ) ) ) )->validate() );
	}
}
