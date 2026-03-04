<?php
/**
 * Plugin Name:       Use Jetpack's Site Accelerator CDN (Photon) for Remote Media
 * Plugin URI:        https://github.com/Automattic/wordpress-activitypub
 * Description:       Rewrites ActivityPub remote media URLs through Jetpack's free image CDN instead of caching files locally.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Jeremy Herve
 * Author URI:        https://herve.bzh/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       activitypub-photon-cdn
 * Requires Plugins:  activitypub
 *
 * @package Activitypub
 */

namespace Activitypub\Snippets;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only register hooks if Jetpack's Image CDN is available.
if ( ! class_exists( '\Automattic\Jetpack\Image_CDN\Image_CDN_Core' ) ) {
	return;
}

/**
 * Rewrite remote media URLs through Jetpack's Photon CDN.
 *
 * Runs at priority 5, before the built-in cache handlers (priority 10),
 * so that remote media is served via Photon instead of being downloaded locally.
 *
 * @param string          $url       The remote media URL.
 * @param string          $context   The media context (avatar, media, emoji, audio, video).
 * @param int|string|null $entity_id The entity ID.
 * @param array           $options   Additional options.
 *
 * @return string The Photon-rewritten URL.
 */
function photon_cdn_url( $url, $context, $entity_id, $options ) {
	return \Automattic\Jetpack\Image_CDN\Image_CDN_Core::cdn_url( $url );
}

// Rewrite remote media URLs through Photon.
\add_filter( 'activitypub_remote_media_url', __NAMESPACE__ . '\photon_cdn_url', 5, 4 );

// Disable local file caching since Photon handles CDN proxying.
\add_filter( 'activitypub_remote_cache_enabled', '__return_false' );
