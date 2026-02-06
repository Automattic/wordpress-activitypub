<?php
/**
 * Author Header with Follow pattern.
 *
 * @package Activitypub
 */

\register_block_pattern(
	'activitypub/author-header',
	array(
		'title'         => _x( 'Author Header with Follow', 'Block pattern title', 'activitypub' ),
		'categories'    => array( 'activitypub' ),
		'keywords'      => array(
			_x( 'author', 'Block pattern keyword', 'activitypub' ),
			_x( 'header', 'Block pattern keyword', 'activitypub' ),
			_x( 'fediverse', 'Block pattern keyword', 'activitypub' ),
			_x( 'follow', 'Block pattern keyword', 'activitypub' ),
		),
		'description'   => _x( 'Compact author header with follow button.', 'Block pattern description', 'activitypub' ),
		'viewportWidth' => 1200,
		'content'       => '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:activitypub/follow-me {"selectedUser":"inherit"} /-->
</div>
<!-- /wp:group -->',
	)
);
