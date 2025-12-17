/**
 * Block markup fixtures for deprecation testing.
 *
 * Each fixture represents the saved HTML from a specific version of the block.
 * These are used to verify that deprecation migrations work correctly.
 *
 * Version history:
 * - v1 (plugin 1.0.0, block 1.0.0): Dynamic block with title attribute, no HTML saved
 * - v2 (plugin 2.0.0, block 2.0.0): Fragment with separate div, no wp-block- prefix
 * - v3 (plugin 3.0.0, block 3.0.0): Uses useBlockProps.save() with wp-block- prefix
 */

/**
 * Block v3 format (plugin 3.0.0+, commit c0c84100 - Interactivity API)
 * Uses useBlockProps.save() which adds wp-block-activitypub-reactions class
 */
export const v3Markup = {
	html: `<!-- wp:activitypub/reactions -->
<div class="wp-block-activitypub-reactions activitypub-reactions-block"></div>
<!-- /wp:activitypub/reactions -->`,
	attributes: {},
	innerBlocks: [],
};

/**
 * Block v3 format with inner blocks (heading)
 */
export const v3MarkupWithHeading = {
	html: `<!-- wp:activitypub/reactions -->
<div class="wp-block-activitypub-reactions activitypub-reactions-block"><!-- wp:heading {"level":6} -->
<h6 class="wp-block-heading">Fediverse reactions</h6>
<!-- /wp:heading --></div>
<!-- /wp:activitypub/reactions -->`,
	attributes: {},
	innerBlocks: [
		{
			name: 'core/heading',
			attributes: { level: 6, content: 'Fediverse reactions' },
		},
	],
};

/**
 * Block v2 format (plugin 2.0.0, commit 92e196e9 to c0c84100)
 * Used fragment with separate div, no wp-block- prefix class
 */
export const v2Markup = {
	html: `<!-- wp:activitypub/reactions -->
<div class="activitypub-reactions-block"></div>
<!-- /wp:activitypub/reactions -->`,
	attributes: {},
	innerBlocks: [],
};

/**
 * Block v2 format with inner blocks (heading)
 */
export const v2MarkupWithHeading = {
	html: `<!-- wp:activitypub/reactions -->
<!-- wp:heading {"level":6} -->
<h6 class="wp-block-heading">Fediverse reactions</h6>
<!-- /wp:heading -->
<div class="activitypub-reactions-block"></div>
<!-- /wp:activitypub/reactions -->`,
	attributes: {},
	innerBlocks: [
		{
			name: 'core/heading',
			attributes: { level: 6, content: 'Fediverse reactions' },
		},
	],
};

/**
 * Block v1 format (plugin 1.0.0, commit 77ae436c to 92e196e9)
 * Dynamic block with title attribute, no HTML saved
 */
export const v1Markup = {
	html: `<!-- wp:activitypub/reactions {"title":"Fediverse reactions"} /-->`,
	attributes: { title: 'Fediverse reactions' },
	innerBlocks: [],
};

/**
 * Block v1 format without explicit title (uses default)
 * This tests the fallback behavior when title attribute is missing.
 */
export const v1MarkupNoTitle = {
	html: `<!-- wp:activitypub/reactions /-->`,
	attributes: {},
	innerBlocks: [],
};

/**
 * Block v1 format with custom title
 */
export const v1MarkupCustomTitle = {
	html: `<!-- wp:activitypub/reactions {"title":"My Custom Reactions"} /-->`,
	attributes: { title: 'My Custom Reactions' },
	innerBlocks: [],
};

/**
 * Block v1 format with long custom title (matches PHP test)
 */
export const v1MarkupLongTitle = {
	html: `<!-- wp:activitypub/reactions {"title":"What people think about it on the Fediverse!"} /-->`,
	attributes: { title: 'What people think about it on the Fediverse!' },
	innerBlocks: [],
};

/**
 * Block v1 format with additional attributes
 */
export const v1MarkupWithAlign = {
	html: `<!-- wp:activitypub/reactions {"title":"Reactions","align":"wide"} /-->`,
	attributes: { title: 'Reactions', align: 'wide' },
	innerBlocks: [],
};
