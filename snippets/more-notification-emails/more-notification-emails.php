<?php
/**
 * Plugin Name:       Allow More Notification Emails
 * Plugin URI:        https://github.com/Automattic/wordpress-activitypub
 * Description:       Raises the number of ActivityPub notification emails a user can get per hour.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Automattic
 * Author URI:        https://automattic.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       activitypub-more-notification-emails
 * Requires Plugins:  activitypub
 * Update URI:        https://github.com/Automattic/wordpress-activitypub
 *
 * @package Activitypub
 */

namespace Activitypub\Snippets;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Raise the hourly allowance of notification emails.
 *
 * The plugin sends at most 30 emails about new followers, direct messages and mentions to the
 * same recipient per hour (`mail`), and as many about replies and reactions (`comment_mail`).
 * The same filter also sets the requests per minute of the REST endpoints, so only these two
 * buckets are changed here.
 *
 * @param int    $limit  The allowance the plugin asks for.
 * @param string $bucket What is being limited.
 *
 * @return int The allowance.
 */
function more_notification_emails( $limit, $bucket ) {
	return \in_array( $bucket, array( 'mail', 'comment_mail' ), true ) ? 100 : $limit;
}
\add_filter( 'activitypub_rate_limit', __NAMESPACE__ . '\more_notification_emails', 10, 2 );
