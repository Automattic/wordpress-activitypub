<?php
/**
 * Plugin Name:       Bot Account
 * Plugin URI:        https://github.com/Automattic/wordpress-activitypub
 * Description:       Marks ActivityPub profiles as bot/automated accounts, displaying a "BOT" badge on Mastodon and other Fediverse platforms.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Automattic
 * Author URI:        https://automattic.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       activitypub-bot-account
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
 * Set the blog actor type to "Service" (bot) in single-user mode.
 *
 * In multi-user mode the blog actor is a "Group" per FEP-1b12,
 * so this filter intentionally skips that case.
 *
 * @param array $array The ActivityPub actor array.
 *
 * @return array The filtered actor array.
 */
function set_blog_bot_type( $array ) {
	if ( \Activitypub\is_single_user() ) {
		$array['type'] = 'Service';
	}

	return $array;
}

/**
 * Set the user actor type to "Service" (bot).
 *
 * @param array $array The ActivityPub actor array.
 *
 * @return array The filtered actor array.
 */
function set_user_bot_type( $array ) {
	$array['type'] = 'Service';

	return $array;
}

// Hook into the ActivityPub actor object array filters.
\add_filter( 'activitypub_activity_blog_object_array', __NAMESPACE__ . '\set_blog_bot_type' );
\add_filter( 'activitypub_activity_user_object_array', __NAMESPACE__ . '\set_user_bot_type' );
