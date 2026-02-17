<?php
/**
 * Fediverse Follow Page pattern.
 *
 * @package Activitypub
 */

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
		'description'   => _x( 'Full follow page layout with profile, extra fields, and followers list.', 'Block pattern description', 'activitypub' ),
		'viewportWidth' => 1200,
		'blockTypes'    => array( 'core/post-content' ),
		'content'       => '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:heading {"level":1} -->
	<h1 class="wp-block-heading">' . esc_html_x( 'Follow Us on the Fediverse', 'Block pattern content', 'activitypub' ) . '</h1>
	<!-- /wp:heading -->
	<!-- wp:paragraph -->
	<p>' . esc_html_x( 'Follow this blog on Mastodon or the Fediverse to receive updates directly in your feed.', 'Block pattern content', 'activitypub' ) . '</p>
	<!-- /wp:paragraph -->
	<!-- wp:spacer {"height":"32px"} -->
	<div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/follow-me {"selectedUser":"blog","className":"is-style-profile"} /-->
	<!-- wp:spacer {"height":"24px"} -->
	<div style="height:24px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/extra-fields {"selectedUser":"blog"} /-->
	<!-- wp:spacer {"height":"32px"} -->
	<div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/followers {"selectedUser":"blog"} -->
	<!-- wp:heading {"level":3} -->
	<h3 class="wp-block-heading">' . esc_html_x( 'Our Fediverse Followers', 'Block pattern content', 'activitypub' ) . '</h3>
	<!-- /wp:heading -->
	<!-- /wp:activitypub/followers -->
</div>
<!-- /wp:group -->',
	)
);
