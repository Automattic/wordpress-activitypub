<?php
/**
 * Test file for the Base_Object schema.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Schema;

use Activitypub\Schema\Activity;
use Activitypub\Schema\Actor;
use Activitypub\Schema\Base_Object;
use Activitypub\Schema\Registry;

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
		$schemas = \array_merge(
			Registry::get_schemas(),
			array(
				'object'   => new Base_Object(),
				'activity' => new Activity(),
				'actor'    => new Actor(),
			)
		);

		foreach ( $schemas as $name => $schema ) {
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
		$this->assertSame( $expected, ! \is_wp_error( ( new Base_Object() )->validate( $data ) ) );
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

		$note = ( new Base_Object() )->sanitize(
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
		);

		\remove_filter( 'locale', $locale );

		$this->assertSame( 'Hallo', $note['content'] );
		$this->assertSame( array( 'https://www.w3.org/ns/activitystreams#Public' ), $note['to'] );
	}
}
