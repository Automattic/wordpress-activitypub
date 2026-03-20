<?php
/**
 * Plugin Name:       Quotes as Comments
 * Plugin URI:        https://github.com/Automattic/wordpress-activitypub
 * Description:       Display ActivityPub quotes as regular comments instead of facepile reactions.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Matthias Pfefferle
 * Author URI:        https://notiz.blog/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
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
 * Remove 'quote' from the list of comment types excluded from the
 * normal comment query so that quotes appear alongside regular comments.
 *
 * The ActivityPub plugin hides likes, reposts, and quotes from the
 * comment list and renders them as facepiles instead. This filter
 * keeps likes and reposts as facepiles but lets quotes through as
 * full comments since they carry meaningful content.
 */
\add_action(
	'pre_get_comments',
	function ( $query ) {
		if ( ! $query instanceof \WP_Comment_Query ) {
			return;
		}

		if ( empty( $query->query_vars['type__not_in'] ) ) {
			return;
		}

		$excluded = $query->query_vars['type__not_in'];

		if ( ! \is_array( $excluded ) ) {
			return;
		}

		// Remove 'quote' from the exclusion list.
		$excluded = \array_diff( $excluded, array( 'quote' ) );

		$query->query_vars['type__not_in'] = \array_values( $excluded );
	},
	20
);

/**
 * Also remove 'quote' from the REST API comment exclusion so that
 * quotes appear in the WordPress REST comments endpoint.
 */
\add_filter(
	'rest_comment_query',
	function ( $prepared_args ) {
		if ( empty( $prepared_args['type__not_in'] ) || ! \is_array( $prepared_args['type__not_in'] ) ) {
			return $prepared_args;
		}

		$prepared_args['type__not_in'] = \array_values(
			\array_diff( $prepared_args['type__not_in'], array( 'quote' ) )
		);

		return $prepared_args;
	},
	20
);
