<?php
/**
 * Title: Fediverse Sidebar
 * Slug: activitypub/social-sidebar
 * Categories: activitypub
 * Keywords: sidebar, widget, fediverse, follow, followers, activitypub
 * Description: Compact sidebar widget with follow button and followers list.
 * Viewport Width: 400
 * Block Types: core/template-part/sidebar
 *
 * @package Activitypub
 */

?>
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:heading {"level":3} -->
	<h3 class="wp-block-heading"><?php esc_html_e( 'Follow on Fediverse', 'activitypub' ); ?></h3>
	<!-- /wp:heading -->
	<!-- wp:activitypub/follow-me {"selectedUser":"inherit","className":"is-style-button"} /-->
	<!-- wp:spacer {"height":"16px"} -->
	<div style="height:16px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/followers {"selectedUser":"inherit","per_page":5} -->
	<!-- wp:heading {"level":4} -->
	<h4 class="wp-block-heading"><?php esc_html_e( 'Recent Followers', 'activitypub' ); ?></h4>
	<!-- /wp:heading -->
	<!-- /wp:activitypub/followers -->
</div>
<!-- /wp:group -->
