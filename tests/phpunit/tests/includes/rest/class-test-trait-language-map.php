<?php
/**
 * Test Trait Language_Map.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Rest;

use Activitypub\Rest\Language_Map;

/**
 * Test Trait Language_Map.
 *
 * @group rest
 * @coversDefaultClass \Activitypub\Rest\Language_Map
 */
class Test_Trait_Language_Map extends \WP_UnitTestCase {

	/**
	 * Test class instance.
	 *
	 * @var object
	 */
	protected $instance;

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		/* Create a test class that uses the trait. */
		$this->instance = new class() {
			use Language_Map;
		};
	}

	/**
	 * Test localize_language_maps.
	 *
	 * @dataProvider data_language_map
	 * @covers ::localize_language_maps
	 *
	 * @param array       $data     The object data to localize.
	 * @param string      $key      The property key to check.
	 * @param string      $locale   The site locale to simulate.
	 * @param string|null $expected The expected resolved value.
	 * @param string      $message  Description of the test case.
	 */
	public function test_localize_language_maps( $data, $key, $locale, $expected, $message ) {
		/* Switch the site locale for this test. */
		$callback = function () use ( $locale ) {
			return $locale;
		};
		\add_filter( 'locale', $callback );

		$result = $this->instance->localize_language_maps( $data );

		\remove_filter( 'locale', $callback );

		$this->assertSame( $expected, isset( $result[ $key ] ) ? $result[ $key ] : null, $message );
	}

	/**
	 * Test that string values pass through unchanged.
	 *
	 * @covers ::localize_language_maps
	 */
	public function test_string_passthrough() {
		$url = 'https://example.com/post/1';
		$this->assertSame( $url, $this->instance->localize_language_maps( $url ) );
	}

	/**
	 * Test that nested object properties are localized.
	 *
	 * @covers ::localize_language_maps
	 */
	public function test_nested_object() {
		$callback = function () {
			return 'de_DE';
		};
		\add_filter( 'locale', $callback );

		$data   = array(
			'type'   => 'Create',
			'object' => array(
				'type'       => 'Note',
				'summary'    => 'English summary',
				'summaryMap' => array(
					'en' => 'English summary',
					'de' => 'Deutsche Zusammenfassung',
				),
				'content'    => 'Hello World',
				'contentMap' => array(
					'en' => 'Hello World',
					'de' => 'Hallo Welt',
				),
			),
		);
		$result = $this->instance->localize_language_maps( $data );

		\remove_filter( 'locale', $callback );

		$this->assertSame( 'Deutsche Zusammenfassung', $result['object']['summary'] );
		$this->assertSame( 'Hallo Welt', $result['object']['content'] );
	}

	/**
	 * Test get_localized_value directly using the same data provider.
	 *
	 * Decomposes the object array into individual parameters on the fly.
	 *
	 * @dataProvider data_language_map
	 * @covers ::get_localized_value
	 *
	 * @param array       $data     The object data to localize.
	 * @param string      $key      The property key to check.
	 * @param string      $locale   The site locale to simulate.
	 * @param string|null $expected The expected resolved value.
	 * @param string      $message  Description of the test case.
	 */
	public function test_get_localized_value( $data, $key, $locale, $expected, $message ) {
		$callback = function () use ( $locale ) {
			return $locale;
		};
		\add_filter( 'locale', $callback );

		$value       = isset( $data[ $key ] ) ? $data[ $key ] : null;
		$map         = isset( $data[ $key . 'Map' ] ) ? $data[ $key . 'Map' ] : null;
		$object_lang = isset( $data['language'] ) ? $data['language'] : null;

		$result = $this->instance->get_localized_value( $value, $map, $object_lang );

		\remove_filter( 'locale', $callback );

		$this->assertSame( $expected, $result, $message );
	}

	/**
	 * Test get_preferred_languages returns site locale and English fallback.
	 *
	 * @covers ::get_preferred_languages
	 */
	public function test_get_preferred_languages_default() {
		$this->assertSame( array( 'de', 'en' ), $this->instance->get_preferred_languages( 'de' ) );
		$this->assertSame( array( 'en' ), $this->instance->get_preferred_languages( 'en' ) );
	}

	/**
	 * Test that the activitypub_preferred_languages filter can add languages.
	 *
	 * @covers ::get_preferred_languages
	 */
	public function test_get_preferred_languages_filter() {
		$callback = function ( $languages ) {
			$languages[] = 'fr';
			return $languages;
		};
		\add_filter( 'activitypub_preferred_languages', $callback );

		$result = $this->instance->get_preferred_languages( 'de' );

		\remove_filter( 'activitypub_preferred_languages', $callback );

		$this->assertSame( array( 'de', 'en', 'fr' ), $result );
	}

	/**
	 * Data provider for language map localization.
	 *
	 * @return array[] Test cases.
	 */
	public function data_language_map() {
		return array(
			'plain_string'                    => array(
				array( 'summary' => 'Hello World' ),
				'summary',
				'en_US',
				'Hello World',
				'Plain string should be returned as-is.',
			),
			'null_value'                      => array(
				array(),
				'summary',
				'en_US',
				null,
				'Missing property should return null.',
			),
			'object_lang_matches_site'        => array(
				array(
					'summary'  => 'Hallo Welt',
					'language' => 'de_DE',
				),
				'summary',
				'de_DE',
				'Hallo Welt',
				'Object language matches site locale: return plain string immediately.',
			),
			'object_lang_does_not_match'      => array(
				array(
					'summary'    => 'Bonjour le monde',
					'summaryMap' => array( 'de' => 'Hallo Welt' ),
					'language'   => 'fr',
				),
				'summary',
				'de_DE',
				'Hallo Welt',
				'Object language differs: prefer *Map locale match over plain string.',
			),
			'map_site_locale_match'           => array(
				array(
					'summary'    => 'Hello World',
					'summaryMap' => array(
						'en' => 'Hello World',
						'de' => 'Hallo Welt',
					),
				),
				'summary',
				'de_DE',
				'Hallo Welt',
				'*Map with site locale match should win over plain string.',
			),
			'map_english_fallback'            => array(
				array(
					'summary'    => 'Bonjour le monde',
					'summaryMap' => array(
						'en' => 'Hello World',
						'fr' => 'Bonjour le monde',
					),
				),
				'summary',
				'de_DE',
				'Hello World',
				'*Map with no site locale should fall back to English.',
			),
			'map_no_match_default_wins'       => array(
				array(
					'summary'    => 'Bonjour le monde',
					'summaryMap' => array(
						'fr' => 'Bonjour le monde',
						'es' => 'Hola mundo',
					),
				),
				'summary',
				'de_DE',
				'Bonjour le monde',
				'*Map with no locale or English match: plain string (default) wins.',
			),
			'map_only_no_base_value'          => array(
				array(
					'summaryMap' => array(
						'en' => 'Hello World',
						'de' => 'Hallo Welt',
					),
				),
				'summary',
				'de_DE',
				'Hallo Welt',
				'*Map with locale match and no base value should resolve from map.',
			),
			'map_no_match_no_default'         => array(
				array(
					'summaryMap' => array(
						'fr' => 'Bonjour le monde',
						'es' => 'Hola mundo',
					),
				),
				'summary',
				'de_DE',
				'Bonjour le monde',
				'*Map with no match and no plain string should return first map entry.',
			),
			'value_is_language_map_ignored'   => array(
				array(
					'summary' => array(
						'en' => 'Hello World',
						'de' => 'Hallo Welt',
					),
				),
				'summary',
				'de_DE',
				null,
				'Language map in base property should return null.',
			),
			'content_key'                     => array(
				array(
					'content'    => 'Default content',
					'contentMap' => array( 'de' => 'Deutscher Inhalt' ),
				),
				'content',
				'de_DE',
				'Deutscher Inhalt',
				'Works for content key with contentMap.',
			),
			'name_key'                        => array(
				array(
					'name'    => 'Default name',
					'nameMap' => array( 'de' => 'Deutscher Name' ),
				),
				'name',
				'de_DE',
				'Deutscher Name',
				'Works for name key with nameMap.',
			),
			'english_site_no_double_fallback' => array(
				array(
					'summary'    => 'Hello World',
					'summaryMap' => array(
						'en' => 'English from map',
						'de' => 'Hallo Welt',
					),
				),
				'summary',
				'en_US',
				'English from map',
				'English site: *Map English match is used.',
			),
			'object_lang_matches_skips_map'   => array(
				array(
					'summary'    => 'Hallo Welt',
					'summaryMap' => array( 'de' => 'Hallo Welt aus der Map' ),
					'language'   => 'de',
				),
				'summary',
				'de_DE',
				'Hallo Welt',
				'Object language matches site: plain string returned, *Map skipped.',
			),
			'object_lang_partial_match'       => array(
				array(
					'summary'  => 'Grüezi',
					'language' => 'de-CH',
				),
				'summary',
				'de_DE',
				'Grüezi',
				'Object language de-CH matches site de_DE (both normalize to "de").',
			),
			'empty_language_map'              => array(
				array( 'summary' => array() ),
				'summary',
				'en_US',
				null,
				'Empty language map in base property should return null.',
			),
			'language_as_array'               => array(
				array(
					'summary'    => '<img style="max-width:100%" src="https://example.com/image.png"/>',
					'summaryMap' => array(
						'en' => 'English summary',
						'de' => 'Deutsche Zusammenfassung',
					),
					'language'   => array( 'en', 'de' ),
				),
				'summary',
				'de_DE',
				'Deutsche Zusammenfassung',
				'Array language property should not cause a fatal error, fall through to *Map resolution.',
			),
		);
	}
}
