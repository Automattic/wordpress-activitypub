<?php
/**
 * Functions file.
 *
 * General utility functions for the ActivityPub plugin.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Convert a string from camelCase to snake_case.
 *
 * @param string $input The string to convert.
 *
 * @return string The converted string.
 */
function camel_to_snake_case( $input ) {
	return strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $input ) );
}

/**
 * Convert a string from snake_case to camelCase.
 *
 * @param string $input The string to convert.
 *
 * @return string The converted string.
 */
function snake_to_camel_case( $input ) {
	return lcfirst( str_replace( '_', '', ucwords( $input, '_' ) ) );
}

/**
 * Convert seconds to ISO 8601 duration format.
 *
 * @param int $seconds The duration in seconds.
 *
 * @return string The duration in ISO 8601 format (e.g., "PT1H23M45S").
 */
function seconds_to_iso8601( $seconds ) {
	$seconds = (int) $seconds;

	if ( $seconds <= 0 ) {
		return 'PT0S';
	}

	$hours   = floor( $seconds / 3600 );
	$minutes = floor( ( $seconds % 3600 ) / 60 );
	$secs    = $seconds % 60;

	$duration = 'PT';

	if ( $hours > 0 ) {
		$duration .= $hours . 'H';
	}

	if ( $minutes > 0 ) {
		$duration .= $minutes . 'M';
	}

	if ( $secs > 0 || ( 0 === $hours && 0 === $minutes ) ) {
		$duration .= $secs . 'S';
	}

	return $duration;
}

/**
 * Check if a site supports the block editor.
 *
 * @return boolean True if the site supports the block editor, false otherwise.
 */
function site_supports_blocks() {
	/**
	 * Allow plugins to disable block editor support,
	 * thus disabling blocks registered by the ActivityPub plugin.
	 *
	 * @param boolean $supports_blocks True if the site supports the block editor, false otherwise.
	 */
	return apply_filters( 'activitypub_site_supports_blocks', true );
}

/**
 * Check if data is valid JSON.
 *
 * @deprecated 7.1.0 Use {@see \json_decode}.
 *
 * @param string $data The data to check.
 *
 * @return boolean True if the data is JSON, false otherwise.
 */
function is_json( $data ) {
	\_deprecated_function( __FUNCTION__, '7.1.0', 'json_decode' );

	return \is_array( \json_decode( $data, true ) );
}

/**
 * Check whether a blog is public based on the `blog_public` option.
 *
 * @return bool True if public, false if not
 */
function is_blog_public() {
	/**
	 * Filter whether the blog is public.
	 *
	 * @param bool $public Whether the blog is public.
	 */
	return (bool) apply_filters( 'activitypub_is_blog_public', \get_option( 'blog_public', 1 ) );
}

/**
 * Get the masked WordPress version to only show the major and minor version.
 *
 * @return string The masked version.
 */
function get_masked_wp_version() {
	// Only show the major and minor version.
	$version = get_bloginfo( 'version' );
	// Strip the RC or beta part.
	$version = preg_replace( '/-.*$/', '', $version );
	$version = explode( '.', $version );
	$version = array_slice( $version, 0, 2 );

	return implode( '.', $version );
}

/**
 * Check if a plugin is active, loading plugin.php if necessary.
 *
 * This is a wrapper around the core is_plugin_active() function that ensures
 * the function is available by loading wp-admin/includes/plugin.php if needed.
 * This is useful when checking plugin status outside of the admin context.
 *
 * @param string $plugin Plugin basename (e.g., 'plugin-folder/plugin-file.php').
 *
 * @return bool True if the plugin is active, false otherwise.
 */
function is_plugin_active( $plugin ) {
	// Include plugin.php if not already loaded (needed for core is_plugin_active).
	if ( ! \function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	return \is_plugin_active( $plugin );
}

/**
 * Returns the website hosts allowed to credit this blog.
 *
 * @return array|null The attribution domains or null if not found.
 */
function get_attribution_domains() {
	if ( '1' !== \get_option( 'activitypub_use_opengraph', '1' ) ) {
		return null;
	}

	$domains = \get_option( 'activitypub_attribution_domains', home_host() );
	$domains = explode( PHP_EOL, $domains );

	if ( ! $domains ) {
		$domains = null;
	}

	return $domains;
}

/**
 * Change the display of large numbers on the site.
 *
 * @author Jeremy Herve
 *
 * @see https://wordpress.org/support/topic/abbreviate-numbers-with-k/
 *
 * @param string $formatted Converted number in string format.
 * @param float  $number    The number to convert based on locale.
 *
 * @return string Converted number in string format.
 */
function custom_large_numbers( $formatted, $number ) {
	global $wp_locale;

	$decimals      = 0;
	$decimal_point = '.';
	$thousands_sep = ',';

	if ( isset( $wp_locale ) ) {
		$decimals      = (int) $wp_locale->number_format['decimal_point'];
		$decimal_point = $wp_locale->number_format['decimal_point'];
		$thousands_sep = $wp_locale->number_format['thousands_sep'];
	}

	if ( $number < 1000 ) { // Any number less than a Thousand.
		return \number_format( $number, $decimals, $decimal_point, $thousands_sep );
	} elseif ( $number < 1000000 ) { // Any number less than a million.
		return \number_format( $number / 1000, $decimals, $decimal_point, $thousands_sep ) . 'K';
	} elseif ( $number < 1000000000 ) { // Any number less than a billion.
		return \number_format( $number / 1000000, $decimals, $decimal_point, $thousands_sep ) . 'M';
	} else { // At least a billion.
		return \number_format( $number / 1000000000, $decimals, $decimal_point, $thousands_sep ) . 'B';
	}
}

/**
 * Escapes a Tag, to be used as a hashtag.
 *
 * @param string $input The string to escape.
 *
 * @return string The escaped hashtag.
 */
function esc_hashtag( $input ) {
	$hashtag = \wp_specialchars_decode( $input, ENT_QUOTES );
	// Remove all characters that are not letters, numbers, or hyphens.
	$hashtag = \preg_replace( '/emoji-regex(*SKIP)(?!)|[^\p{L}\p{Nd}-]+/u', '-', $hashtag );

	// Capitalize every letter that is preceded by a hyphen.
	$hashtag = preg_replace_callback(
		'/-+(.)/',
		static function ( $matches ) {
			return strtoupper( $matches[1] );
		},
		$hashtag
	);

	// Add a hashtag to the beginning of the string.
	$hashtag = ltrim( $hashtag, '#' );
	$hashtag = trim( $hashtag, '-' );
	$hashtag = '#' . $hashtag;

	/**
	 * Allow defining your own custom hashtag generation rules.
	 *
	 * @param string $hashtag The hashtag to be returned.
	 * @param string $input   The original string.
	 */
	$hashtag = apply_filters( 'activitypub_esc_hashtag', $hashtag, $input );

	return esc_html( $hashtag );
}

/**
 * Replace content with links, mentions or hashtags by Regex callback and not affect protected tags.
 *
 * @param string   $content        The content that should be changed.
 * @param string   $regex          The regex to use.
 * @param callable $regex_callback Callback for replacement logic.
 *
 * @return string The content with links, mentions, hashtags, etc.
 */
function enrich_content_data( $content, $regex, $regex_callback ) {
	// Small protection against execution timeouts: limit to 1 MB.
	if ( mb_strlen( $content ) > MB_IN_BYTES ) {
		return $content;
	}
	$tag_stack          = array();
	$protected_tags     = array(
		'pre',
		'code',
		'textarea',
		'style',
		'a',
	);
	$content_with_links = '';
	$in_protected_tag   = false;
	foreach ( wp_html_split( $content ) as $chunk ) {
		if ( preg_match( '#^<!--[\s\S]*-->$#i', $chunk, $m ) ) {
			$content_with_links .= $chunk;
			continue;
		}

		if ( preg_match( '#^<(/)?([a-z-]+)\b[^>]*>$#i', $chunk, $m ) ) {
			$tag = strtolower( $m[2] );
			if ( '/' === $m[1] ) {
				// Closing tag.
				$i = array_search( $tag, $tag_stack, true );
				// We can only remove the tag from the stack if it is in the stack.
				if ( false !== $i ) {
					$tag_stack = array_slice( $tag_stack, 0, $i );
				}
			} else {
				// Opening tag, add it to the stack.
				$tag_stack[] = $tag;
			}

			// If we're in a protected tag, the tag_stack contains at least one protected tag string.
			// The protected tag state can only change when we encounter a start or end tag.
			$in_protected_tag = array_intersect( $tag_stack, $protected_tags );

			// Never inspect tags.
			$content_with_links .= $chunk;
			continue;
		}

		if ( $in_protected_tag ) {
			// Don't inspect a chunk inside an inspected tag.
			$content_with_links .= $chunk;
			continue;
		}

		// Only reachable when there is no protected tag in the stack.
		$content_with_links .= \preg_replace_callback( $regex, $regex_callback, $chunk );
	}

	return $content_with_links;
}

/**
 * Get an ActivityPub embed HTML for a URL.
 *
 * @param string  $url        The URL to get the embed for.
 * @param boolean $inline_css Whether to inline CSS. Default true.
 *
 * @return string|false The embed HTML or false if not found.
 */
function get_embed_html( $url, $inline_css = true ) {
	return Embed::get_html( $url, $inline_css );
}
