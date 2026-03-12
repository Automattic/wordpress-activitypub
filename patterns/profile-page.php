<?php
/**
 * Fediverse Profile Page pattern.
 *
 * @package Activitypub
 */

$selected_user = ! \Activitypub\is_user_type_disabled( 'blog' ) ? 'blog' : 'inherit';

$following_block = '';
if ( '1' === \get_option( 'activitypub_following_ui', '0' ) ) {
	$following_block = '
	<!-- wp:spacer {"height":"32px"} -->
	<div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/following {"selectedUser":"' . $selected_user . '"} -->
	<div class="wp-block-activitypub-following"><!-- wp:heading {"level":3} -->
	<h3 class="wp-block-heading">' . esc_html_x( 'Following', 'Block pattern content', 'activitypub' ) . '</h3>
	<!-- /wp:heading --></div>
	<!-- /wp:activitypub/following -->';
}

\register_block_pattern(
	'activitypub/profile-page',
	array(
		'title'         => _x( 'Fediverse Profile Page', 'Block pattern title', 'activitypub' ),
		'categories'    => array( 'activitypub' ),
		'keywords'      => array(
			_x( 'profile', 'Block pattern keyword', 'activitypub' ),
			_x( 'fediverse', 'Block pattern keyword', 'activitypub' ),
			_x( 'page', 'Block pattern keyword', 'activitypub' ),
			_x( 'extra fields', 'Block pattern keyword', 'activitypub' ),
			_x( 'followers', 'Block pattern keyword', 'activitypub' ),
		),
		'description'   => '1' === \get_option( 'activitypub_following_ui', '0' )
			? _x( 'Full profile page with extra fields, followers, and following lists.', 'Block pattern description', 'activitypub' )
			: _x( 'Full profile page with extra fields and followers list.', 'Block pattern description', 'activitypub' ),
		'viewportWidth' => 1200,
		'postTypes'     => array( 'page' ),
		'blockTypes'    => array( 'core/post-content' ),
		'content'       => '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:activitypub/follow-me {"selectedUser":"' . $selected_user . '","className":"is-style-profile"} /-->
	<!-- wp:spacer {"height":"32px"} -->
	<div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/extra-fields {"selectedUser":"' . $selected_user . '"} /-->
	<!-- wp:spacer {"height":"32px"} -->
	<div style="height:32px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/followers {"selectedUser":"' . $selected_user . '"} -->
	<div class="wp-block-activitypub-followers"><!-- wp:heading {"level":3} -->
	<h3 class="wp-block-heading">' . esc_html_x( 'Followers', 'Block pattern content', 'activitypub' ) . '</h3>
	<!-- /wp:heading --></div>
	<!-- /wp:activitypub/followers -->' . $following_block . '
</div>
<!-- /wp:group -->',
	)
);
