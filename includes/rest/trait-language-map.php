<?php
/**
 * Language_Map Trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

/**
 * Language_Map Trait.
 *
 * Provides methods for resolving ActivityStreams natural language values.
 *
 * Properties like `summary`, `content`, and `name` should be plain strings.
 * Language maps should use the `*Map` variant (`summaryMap`, `contentMap`,
 * `nameMap`).
 *
 * @since 8.0.0
 *
 * @see https://www.w3.org/TR/activitystreams-core/#naturalLanguageValues
 * @see https://www.w3.org/wiki/Activity_Streams/Primer/Language_mapping
 */
trait Language_Map {

	/**
	 * Default fallback language code.
	 *
	 * @since 8.0.0
	 *
	 * @var string
	 */
	protected $fallback_language = 'en';

	/**
	 * Localize language map properties in an activity object array.
	 *
	 * Normalizes `summary`, `content`, and `name` (and their `*Map` variants)
	 * to plain strings. Also recurses into nested `object` properties.
	 *
	 * Can be used as a sanitize_callback for REST API args.
	 *
	 * @since 8.0.0
	 *
	 * @param mixed $data The activity object data (array or string URI).
	 *
	 * @return mixed The data with language maps resolved, or unchanged if not an array.
	 */
	public function localize_language_maps( $data ) {
		if ( ! \is_array( $data ) ) {
			return $data;
		}

		$properties = array( 'summary', 'content', 'name' );

		foreach ( $properties as $key ) {
			if ( isset( $data[ $key ] ) || isset( $data[ $key . 'Map' ] ) ) {
				$data[ $key ] = $this->get_localized_value(
					isset( $data[ $key ] ) ? $data[ $key ] : null,
					isset( $data[ $key . 'Map' ] ) ? $data[ $key . 'Map' ] : null,
					isset( $data['language'] ) ? $data['language'] : null
				);
			}
		}

		/* Also normalize within the nested object if it is an array. */
		if ( isset( $data['object'] ) && \is_array( $data['object'] ) ) {
			$data['object'] = $this->localize_language_maps( $data['object'] );
		}

		return $data;
	}

	/**
	 * Resolve a natural language value to a plain string.
	 *
	 * Resolution priority:
	 * 1. The base property when the object's language matches the site locale.
	 * 2. Site locale or English match in the `*Map` variant.
	 * 3. The base property as a plain string (the default).
	 * 4. First `*Map` entry if no base string and no preferred language match.
	 *
	 * Non-string base values (e.g. arrays) are ignored.
	 *
	 * @since 8.0.0
	 *
	 * @param mixed       $value       The base property value (only strings are used).
	 * @param array|null  $map         The `*Map` variant (e.g. `summaryMap`).
	 * @param string|null $object_lang The object's language property.
	 *
	 * @return string|null The resolved string, or null if empty.
	 */
	public function get_localized_value( $value, $map, $object_lang ) {
		$site_lang = \strtolower( \strtok( \get_locale(), '_-' ) );

		/*
		 * If the object's language matches the site locale,
		 * the base property is already in the right language.
		 */
		if ( $object_lang && \is_string( $value ) ) {
			if ( \strtolower( \strtok( $object_lang, '_-' ) ) === $site_lang ) {
				return $value;
			}
		}

		$languages = $this->get_preferred_languages( $site_lang );

		/* Check the *Map variant for a locale match. */
		if ( \is_array( $map ) ) {
			$resolved = $this->resolve_language_map( $map, $languages );
			if ( $resolved ) {
				return $resolved;
			}
		}

		if ( \is_string( $value ) ) {
			return $value;
		}

		/* No base value and no language match: use first map entry. */
		if ( \is_array( $map ) && ! empty( $map ) ) {
			return \current( $map );
		}

		return null;
	}

	/**
	 * Get the preferred language codes in priority order.
	 *
	 * Returns the site locale as primary, with English as fallback
	 * (unless the site is already English). Additional languages can
	 * be added via the `activitypub_preferred_languages` filter.
	 *
	 * @since 8.0.0
	 *
	 * @param string $site_lang The site's primary language code (e.g. 'de').
	 *
	 * @return string[] Language codes in priority order.
	 */
	public function get_preferred_languages( $site_lang ) {
		$languages = array( $site_lang );

		if ( $this->fallback_language !== $site_lang ) {
			$languages[] = $this->fallback_language;
		}

		/**
		 * Filters the preferred language codes for language map resolution.
		 *
		 * @since 8.0.0
		 *
		 * @param string[] $languages Preferred language codes in priority order.
		 * @param string   $site_lang The site's primary language code.
		 */
		return \apply_filters( 'activitypub_preferred_languages', $languages, $site_lang );
	}

	/**
	 * Resolve a language map to a single string.
	 *
	 * Tries each preferred language in order (site locale, then English).
	 *
	 * @since 8.0.0
	 *
	 * @param array    $map       The language map (e.g. `{"en": "Hello", "de": "Hallo"}`).
	 * @param string[] $languages Preferred language codes in priority order (e.g. `['de', 'en']`).
	 *
	 * @return string|null The matched string, or null if no match found.
	 */
	private function resolve_language_map( $map, $languages ) {
		if ( empty( $map ) ) {
			return null;
		}

		foreach ( $languages as $lang ) {
			if ( isset( $map[ $lang ] ) ) {
				return $map[ $lang ];
			}
		}

		return null;
	}
}
