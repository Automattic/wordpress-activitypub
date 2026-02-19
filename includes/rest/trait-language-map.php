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
 * Properties like `summary`, `content`, and `name` can be either a plain
 * string or a language map (e.g. `{"en": "Hello"}`). Language maps should
 * use the `*Map` variant (`summaryMap`, `contentMap`, `nameMap`), but some
 * implementations incorrectly send them in the base property.
 *
 * @since unreleased
 *
 * @see https://www.w3.org/TR/activitystreams-core/#naturalLanguageValues
 * @see https://www.w3.org/wiki/Activity_Streams/Primer/Language_mapping
 */
trait Language_Map {
	/**
	 * Localize language map properties in an activity object array.
	 *
	 * Normalizes `summary`, `content`, and `name` (and their `*Map` variants)
	 * to plain strings. Also recurses into nested `object` properties.
	 *
	 * Can be used as a sanitize_callback for REST API args.
	 *
	 * @since unreleased
	 *
	 * @param mixed $data The activity object data (array or string URI).
	 *
	 * @return mixed The data with language maps resolved, or unchanged if not an array.
	 */
	public static function localize_language_maps( $data ) {
		if ( ! \is_array( $data ) ) {
			return $data;
		}

		$properties = array( 'summary', 'content', 'name' );

		foreach ( $properties as $key ) {
			if ( isset( $data[ $key ] ) || isset( $data[ $key . 'Map' ] ) ) {
				$data[ $key ] = self::get_localized_value(
					isset( $data[ $key ] ) ? $data[ $key ] : null,
					isset( $data[ $key . 'Map' ] ) ? $data[ $key . 'Map' ] : null,
					isset( $data['language'] ) ? $data['language'] : null
				);
			}
		}

		/* Also normalize within the nested object if it is an array. */
		if ( isset( $data['object'] ) && \is_array( $data['object'] ) ) {
			$data['object'] = self::localize_language_maps( $data['object'] );
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
	 * 4. Site locale, English, or first match from a language map incorrectly
	 *    placed in the base property (e.g. `summary: {"en": "Hello"}`).
	 *
	 * @since unreleased
	 *
	 * @param string|array|null $value       The base property value.
	 * @param array|null        $map         The `*Map` variant (e.g. `summaryMap`).
	 * @param string|null       $object_lang The object's language property.
	 *
	 * @return string|null The resolved string, or null if empty.
	 */
	public static function get_localized_value( $value, $map, $object_lang ) {
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

		/* Build preferred languages: site locale, then English as fallback. */
		$languages = array( $site_lang );
		if ( 'en' !== $site_lang ) {
			$languages[] = 'en';
		}

		/* Check the *Map variant for a locale match. */
		if ( \is_array( $map ) ) {
			$resolved = self::resolve_language_map( $map, $languages );
			if ( $resolved ) {
				return $resolved;
			}
		}

		/* Fall back to the base property as a plain string (the default). */
		if ( \is_string( $value ) ) {
			return $value;
		}

		/*
		 * Handle incorrectly placed language map in the base property:
		 * same locale resolution, then first available entry.
		 */
		if ( \is_array( $value ) ) {
			$resolved = self::resolve_language_map( $value, $languages );
			if ( $resolved ) {
				return $resolved;
			}

			return \reset( $value ) ?: null;
		}

		return null;
	}

	/**
	 * Resolve a language map to a single string.
	 *
	 * Tries each preferred language in order (site locale, then English).
	 *
	 * @since unreleased
	 *
	 * @param array    $map       The language map (e.g. `{"en": "Hello", "de": "Hallo"}`).
	 * @param string[] $languages Preferred language codes in priority order (e.g. `['de', 'en']`).
	 *
	 * @return string|null The matched string, or null if no match found.
	 */
	private static function resolve_language_map( $map, $languages ) {
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
