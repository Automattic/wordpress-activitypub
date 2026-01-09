/**
 * Block markup fixtures for testing deprecations.
 *
 * Version history:
 * - v1 (plugin 1.0.0): Dynamic block with title attribute
 * - v2 (current): InnerBlocks for heading, uses useBlockProps.save()
 */

/**
 * v1: Block with title attribute (pre-InnerBlocks migration).
 * The title attribute was migrated to a core/heading innerBlock.
 */
export const v1Markup = {
	html: `<!-- wp:activitypub/followers {"title":"Fediverse Followers"} /-->`,
	attributes: {
		title: 'Fediverse Followers',
	},
	innerBlocks: [],
};

/**
 * v1 with no title attribute (should not be eligible for migration).
 */
export const v1MarkupNoTitle = {
	html: `<!-- wp:activitypub/followers /-->`,
	attributes: {},
	innerBlocks: [],
};

/**
 * v1 with custom title.
 */
export const v1MarkupCustomTitle = {
	html: `<!-- wp:activitypub/followers {"title":"My Followers"} /-->`,
	attributes: {
		title: 'My Followers',
	},
	innerBlocks: [],
};

/**
 * v1 with additional attributes (selectedUser, per_page, order).
 */
export const v1MarkupWithOptions = {
	html: `<!-- wp:activitypub/followers {"title":"Blog Followers","selectedUser":"blog","per_page":20,"order":"asc"} /-->`,
	attributes: {
		title: 'Blog Followers',
		selectedUser: 'blog',
		per_page: 20,
		order: 'asc',
	},
	innerBlocks: [],
};
