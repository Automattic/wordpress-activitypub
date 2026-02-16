<?php
/**
 * Plugin Name:       Locale from Tags
 * Plugin URI:        https://github.com/Automattic/wordpress-activitypub
 * Description:       Sets a post's ActivityPub language based on post tags matching language codes.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Jeremy Herve
 * Author URI:        https://herve.bzh/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       activitypub-locale-from-tags
 * Requires Plugins:  activitypub
 *
 * @package Activitypub
 */

namespace Activitypub\Snippets;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Set a post's language in its ActivityPub representation based on post tags.
 *
 * When a post has tags matching configured language codes,
 * the first matching tag is used as the post's locale for ActivityPub.
 * When no matching tags are found, the default locale is preserved.
 *
 * @see https://herve.bzh/wordpress-set-a-posts-language-in-its-activitypub-representation-based-on-post-tags/
 *
 * @param string $lang The locale of the post.
 * @param mixed  $item The post object or other item being transformed.
 *
 * @return string The filtered locale of the post.
 */
function set_locale_from_tags( $lang, $item ) {
	if ( ! $item instanceof \WP_Post ) {
		return $lang;
	}

	$post_tags = \get_the_tags( $item->ID );

	if ( ! \is_array( $post_tags ) ) {
		return $lang;
	}

	/**
	 * Filters the list of language codes to detect in post tags.
	 *
	 * Each entry should be an ISO 639-1 two-letter language code
	 * that you use as a tag slug on your site.
	 *
	 * @param string[] $language_codes Array of language code strings to look for in tags.
	 */
	$language_codes = \apply_filters(
		'activitypub_snippet_locale_from_tags_codes',
		array( 'en', 'fr', 'de', 'es', 'it', 'pt', 'nl', 'ja', 'zh', 'ko' )
	);

	foreach ( $post_tags as $tag ) {
		if ( \in_array( $tag->slug, $language_codes, true ) ) {
			return $tag->slug;
		}
	}

	return $lang;
}

// Hook into the ActivityPub locale filter.
\add_filter( 'activitypub_locale', __NAMESPACE__ . '\set_locale_from_tags', 10, 2 );
