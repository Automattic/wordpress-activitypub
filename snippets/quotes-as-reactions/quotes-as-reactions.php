<?php
/**
 * Plugin Name:       Quotes as Reactions
 * Plugin URI:        https://github.com/Automattic/wordpress-activitypub
 * Description:       Display ActivityPub quotes in the Reactions block instead of the comment list.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Matthias Pfefferle
 * Author URI:        https://notiz.blog/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins:  activitypub
 * Update URI:        https://github.com/Automattic/wordpress-activitypub
 *
 * @package Activitypub
 */

namespace Activitypub\Snippets;

use Activitypub\Comment;

use function Activitypub\register_comment_type;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the quote comment type as a reaction again.
 *
 * The ActivityPub plugin shows quotes as comments, because a quote is a post of its own that
 * others can reply to. A reaction is hidden from the comment list and the comment count, and
 * shown in the Reactions block. Replies to a quote are hidden together with the quote.
 *
 * Runs after the plugin registered its comment types on `init`.
 */
function register_quotes_as_reactions() {
	$quote = Comment::get_comment_type( 'quote' );

	if ( ! $quote ) {
		return;
	}

	$quote['reaction'] = true;

	register_comment_type( 'quote', $quote );
}
\add_action( 'init', __NAMESPACE__ . '\register_quotes_as_reactions', 11 );

/**
 * Recount the comments of all posts with ActivityPub comments, since quotes are counted
 * as comments without this snippet and not with it.
 */
function recount_quotes() {
	\wp_schedule_single_event( \time(), 'activitypub_update_comment_counts' );
}
\register_activation_hook( __FILE__, __NAMESPACE__ . '\recount_quotes' );
\register_deactivation_hook( __FILE__, __NAMESPACE__ . '\recount_quotes' );
