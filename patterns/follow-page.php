<?php
/**
 * Fediverse Follow Page pattern.
 *
 * @package Activitypub
 */

$selected_user = ! \Activitypub\is_user_type_disabled( 'blog' ) ? 'blog' : 'inherit';

\register_block_pattern(
	'activitypub/follow-page',
	array(
		'title'         => _x( 'Fediverse Follow Page', 'Block pattern title', 'activitypub' ),
		'categories'    => array( 'activitypub' ),
		'keywords'      => array(
			_x( 'follow', 'Block pattern keyword', 'activitypub' ),
			_x( 'fediverse', 'Block pattern keyword', 'activitypub' ),
			_x( 'page', 'Block pattern keyword', 'activitypub' ),
			_x( 'followers', 'Block pattern keyword', 'activitypub' ),
		),
		'description'   => _x( 'Follow page layout with profile and followers list.', 'Block pattern description', 'activitypub' ),
		'viewportWidth' => 1200,
		'postTypes'     => array( 'page' ),
		'blockTypes'    => array( 'core/post-content' ),
		'content'       => '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:paragraph -->
	<p>' . esc_html_x( 'Follow this blog on Mastodon or the Fediverse and get new posts delivered straight to your feed.', 'Block pattern content', 'activitypub' ) . '</p>
	<!-- /wp:paragraph -->
	<!-- wp:spacer {"height":"32px"} -->
	<div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/follow-me {"selectedUser":"' . $selected_user . '","className":"is-style-profile"} /-->
	<!-- wp:spacer {"height":"32px"} -->
	<div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/followers {"selectedUser":"' . $selected_user . '"} -->
	<div class="wp-block-activitypub-followers"><!-- wp:heading {"level":3} -->
	<h3 class="wp-block-heading">' . esc_html_x( 'Our Fediverse Followers', 'Block pattern content', 'activitypub' ) . '</h3>
	<!-- /wp:heading --></div>
	<!-- /wp:activitypub/followers -->
</div>
<!-- /wp:group -->',
	)
);
