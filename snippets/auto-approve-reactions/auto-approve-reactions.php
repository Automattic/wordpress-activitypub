<?php
/**
 * Plugin Name:       Auto-Approve Reactions
 * Plugin URI:        https://github.com/Automattic/wordpress-activitypub
 * Description:       Automatically approves all incoming ActivityPub reactions (likes and reposts) without requiring manual moderation.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Automattic
 * Author URI:        https://automattic.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       activitypub-auto-approve-reactions
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
 * Auto-approve all ActivityPub reactions (likes and reposts).
 *
 * Hooks into the comment approval flow at a higher priority than
 * the core plugin (which runs at 11) to approve reactions regardless
 * of the "Auto approve reactions" setting.
 *
 * @param int|string|\WP_Error $approved     The approved comment status.
 * @param array                $comment_data The comment data.
 *
 * @return int|string|\WP_Error The approval status.
 */
function auto_approve_reactions( $approved, $comment_data ) {
	// Don't override trash or errors.
	if ( 'trash' === $approved || \is_wp_error( $approved ) ) {
		return $approved;
	}

	// Only handle ActivityPub comments.
	if (
		empty( $comment_data['comment_meta']['protocol'] ) ||
		'activitypub' !== $comment_data['comment_meta']['protocol']
	) {
		return $approved;
	}

	$reaction_types = \Activitypub\Comment::get_comment_type_slugs();

	if ( \in_array( $comment_data['comment_type'], $reaction_types, true ) ) {
		return 1;
	}

	return $approved;
}

// Run at priority 10, before the core plugin's handler at priority 11.
\add_filter( 'pre_comment_approved', __NAMESPACE__ . '\auto_approve_reactions', 10, 2 );
