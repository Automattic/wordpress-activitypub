<?php
/**
 * Fediverse Sidebar pattern.
 *
 * @package Activitypub
 */

\register_block_pattern(
	'activitypub/social-sidebar',
	array(
		'title'         => _x( 'Fediverse Sidebar', 'Block pattern title', 'activitypub' ),
		'categories'    => array( 'activitypub' ),
		'keywords'      => array(
			_x( 'sidebar', 'Block pattern keyword', 'activitypub' ),
			_x( 'widget', 'Block pattern keyword', 'activitypub' ),
			_x( 'fediverse', 'Block pattern keyword', 'activitypub' ),
			_x( 'follow', 'Block pattern keyword', 'activitypub' ),
			_x( 'followers', 'Block pattern keyword', 'activitypub' ),
		),
		'description'   => _x( 'Compact sidebar widget with follow button and followers list.', 'Block pattern description', 'activitypub' ),
		'viewportWidth' => 400,
		'blockTypes'    => array( 'core/template-part/sidebar' ),
		'content'       => '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:heading {"level":3} -->
	<h3 class="wp-block-heading">' . esc_html_x( 'Follow on Fediverse', 'Block pattern content', 'activitypub' ) . '</h3>
	<!-- /wp:heading -->
	<!-- wp:activitypub/follow-me {"selectedUser":"inherit","className":"is-style-button"} /-->
	<!-- wp:spacer {"height":"16px"} -->
	<div style="height:16px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/followers {"selectedUser":"inherit","per_page":5} -->
	<!-- wp:heading {"level":4} -->
	<h4 class="wp-block-heading">' . esc_html_x( 'Recent Followers', 'Block pattern content', 'activitypub' ) . '</h4>
	<!-- /wp:heading -->
	<!-- /wp:activitypub/followers -->
</div>
<!-- /wp:group -->',
	)
);
