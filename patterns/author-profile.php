<?php
/**
 * Author Profile with Follow pattern.
 *
 * @package Activitypub
 */

\register_block_pattern(
	'activitypub/author-profile',
	array(
		'title'         => _x( 'Author Profile with Follow', 'Block pattern title', 'activitypub' ),
		'categories'    => array( 'activitypub' ),
		'keywords'      => array(
			_x( 'author', 'Block pattern keyword', 'activitypub' ),
			_x( 'profile', 'Block pattern keyword', 'activitypub' ),
			_x( 'fediverse', 'Block pattern keyword', 'activitypub' ),
			_x( 'follow', 'Block pattern keyword', 'activitypub' ),
		),
		'description'   => _x( 'Display author profile with follow button and extra fields.', 'Block pattern description', 'activitypub' ),
		'viewportWidth' => 1200,
		'content'       => '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:activitypub/follow-me {"selectedUser":"inherit","className":"is-style-profile"} /-->
	<!-- wp:spacer {"height":"24px"} -->
	<div style="height:24px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/extra-fields {"selectedUser":"inherit"} /-->
</div>
<!-- /wp:group -->',
	)
);
