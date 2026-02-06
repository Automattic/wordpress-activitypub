<?php
/**
 * Title: Author Profile with Follow
 * Slug: activitypub/author-profile
 * Categories: activitypub
 * Keywords: author, profile, fediverse, follow, activitypub
 * Description: Display author profile with follow button and extra fields.
 * Viewport Width: 1200
 *
 * @package Activitypub
 */

?>
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:activitypub/follow-me {"selectedUser":"inherit","className":"is-style-profile"} /-->
	<!-- wp:spacer {"height":"24px"} -->
	<div style="height:24px" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->
	<!-- wp:activitypub/extra-fields {"selectedUser":"inherit"} /-->
</div>
<!-- /wp:group -->
