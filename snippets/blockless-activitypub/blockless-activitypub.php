<?php
/*
 * Plugin Name:       Blockless ActivityPub
 * Plugin URI:        https://github.com/Automattic/wordpress-activitypub
 * Description:       Make activitypub blockless and use less JS
 * Version:           0.3
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Frank Goossens
 * Author URI:        https://blog.futtta.be/2026/02/12/wordpress-activitypub-plugin-what-about-performance/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins:  activitypub
 */

namespace Activitypub\Snippets;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// don't do 'remote reply' by removing the filter.
\add_action(
	'template_redirect',
	function () {
		\remove_filter(
			'comment_reply_link',
			array( \Activitypub\Comment::class, 'comment_reply_link' ),
			10
		);
	}
);

// this could be useful at some point?
// \add_filter( 'activitypub_site_supports_blocks', '__return_false', 8 );

// render fediverse reactions server-side
\add_action(
	'comments_template',
	function () {
		$post_id            = \get_the_ID();
		$totalreactioncount = 0;

		// get all reactions per type of reaction.
		foreach ( array( 'like', 'repost', 'quote' ) as $reactiontype ) {
			$args = array(
				'post_id' => $post_id,
				'type'    => $reactiontype,
				'status'  => 'approve',
				'parent'  => 0,
			);

			// fetch results and store in array per reactiontype.
			$query                      = new \WP_Comment_Query();
			$reactions[ $reactiontype ] = $query->query( $args );

			// keep track of total number of reactions to show in the title.
			$totalreactioncount += count( $reactions[ $reactiontype ] );
		}

		// bail if none found.
		if ( $totalreactioncount == 0 ) {
			return;
		}

		// start output, including styles (ugly, I know).
		echo '<style>
			div#fedifeedback{
				padding-bottom:44px;
			}
			div.fedireactiontyperow{
  				display: flex;
				margin-bottom: 10px;
			}
			span.fedireactioncounter{
				margin-left:30px;
			}
			img.fedifacelet{
				width:32px;
				height:32px;
				border-radius:50%;
				border: 3px black;
				position: relative;
  				top: 0;
  				transition: transform ease 0.5s;
				-moz-force-broken-image-icon: 1;
				overflow: hidden;
			}
			img.fedifacelet:hover{
				transform: scale(1.77);
			}
		</style>
		<div id="fedifeedback">
		<h2>' . $totalreactioncount . ' Fediverse reactions</h2>';

		// iterate through the array for each reactiontype (like, repost, quote)
		foreach ( $reactions as $reactiontype => $reactions ) {
			if ( empty( $reactions ) ) {
				continue;
			}

			// output avatars for this reactiontype
			echo '<div class="fedireactiontyperow">';
			foreach ( $reactions as $reaction ) {
				echo '<a href="' . esc_url( $reaction->comment_author_url ) . '"><img class="fedifacelet" src="' . esc_url( get_avatar_url( $reaction ) ) . '" alt="' . esc_html( $reaction->comment_author ) . '" title="' . esc_html( $reaction->comment_author ) . '" /></a>';
			}

			// ugly shortcut for plurals, no multilang yet either.
			$reactioncount = count( $reactions );
			if ( $reactioncount > 1 ) {
				$reactiontype .= 's';
			}

			echo '<span class="fedireactioncounter">' . $reactioncount . ' ' . $reactiontype . '</span></div>';
		}

		echo '</div>';
	},
	10,
	0
);
