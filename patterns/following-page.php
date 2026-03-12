<?php
/**
 * Fediverse Following Page pattern.
 *
 * @package Activitypub
 */

$selected_user = ! \Activitypub\is_user_type_disabled( 'blog' ) ? 'blog' : 'inherit';

\register_block_pattern(
	'activitypub/following-page',
	array(
		'title'         => _x( 'Fediverse Following Page', 'Block pattern title', 'activitypub' ),
		'categories'    => array( 'activitypub' ),
		'keywords'      => array(
			_x( 'following', 'Block pattern keyword', 'activitypub' ),
			_x( 'fediverse', 'Block pattern keyword', 'activitypub' ),
			_x( 'page', 'Block pattern keyword', 'activitypub' ),
		),
		'description'   => _x( 'Following page layout with profile and following list.', 'Block pattern description', 'activitypub' ),
		'viewportWidth' => 1200,
		'postTypes'     => array( 'page' ),
		'blockTypes'    => array( 'core/post-content' ),
		'content'       => '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:paragraph -->
	<p>' . esc_html_x( 'These are the people and projects we follow on the Fediverse.', 'Block pattern content', 'activitypub' ) . '</p>
	<!-- /wp:paragraph -->
	<!-- wp:spacer {"height":"32px"} -->
	<div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/follow-me {"selectedUser":"' . $selected_user . '","className":"is-style-profile"} /-->
	<!-- wp:spacer {"height":"32px"} -->
	<div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/following {"selectedUser":"' . $selected_user . '"} -->
	<div class="wp-block-activitypub-following"><!-- wp:heading {"level":3} -->
	<h3 class="wp-block-heading">' . esc_html_x( 'Following on the Fediverse', 'Block pattern content', 'activitypub' ) . '</h3>
	<!-- /wp:heading --></div>
	<!-- /wp:activitypub/following -->
</div>
<!-- /wp:group -->',
	)
);
