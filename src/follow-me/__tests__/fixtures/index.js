/**
 * Block markup fixtures for testing deprecations.
 *
 * Version history:
 * - v1 (plugin 1.0.0): buttonText and buttonOnly attributes
 * - v2 (plugin 2.0.0): buttonOnly attribute migrated to block style
 * - v3 (plugin 2.x.x): Fix broken button HTML (tagName attribute issue)
 * - v4 (current): Uses core/button innerBlock with block styles
 */

/**
 * v1: Block with buttonText and buttonOnly attributes.
 * Both get migrated: buttonText → core/button innerBlock, buttonOnly → className.
 */
export const v1Markup = {
	html: `<!-- wp:activitypub/follow-me {"buttonText":"Follow","buttonOnly":false} /-->`,
	attributes: {
		buttonText: 'Follow',
		buttonOnly: false,
	},
	innerBlocks: [],
};

/**
 * v1 with buttonOnly=true (should migrate to is-style-button-only).
 */
export const v1MarkupButtonOnly = {
	html: `<!-- wp:activitypub/follow-me {"buttonText":"Subscribe","buttonOnly":true} /-->`,
	attributes: {
		buttonText: 'Subscribe',
		buttonOnly: true,
	},
	innerBlocks: [],
};

/**
 * v1 with custom buttonText.
 */
export const v1MarkupCustomText = {
	html: `<!-- wp:activitypub/follow-me {"buttonText":"Connect with me"} /-->`,
	attributes: {
		buttonText: 'Connect with me',
	},
	innerBlocks: [],
};

/**
 * v1 with selectedUser attribute.
 */
export const v1MarkupWithUser = {
	html: `<!-- wp:activitypub/follow-me {"buttonText":"Follow","selectedUser":"1"} /-->`,
	attributes: {
		buttonText: 'Follow',
		selectedUser: '1',
	},
	innerBlocks: [],
};

/**
 * v2: Block with buttonOnly attribute (no buttonText).
 * buttonOnly gets migrated to className block style.
 */
export const v2Markup = {
	html: `<!-- wp:activitypub/follow-me {"buttonOnly":true} /-->`,
	attributes: {
		buttonOnly: true,
	},
	innerBlocks: [],
};

/**
 * v2 with existing className.
 */
export const v2MarkupWithClassName = {
	html: `<!-- wp:activitypub/follow-me {"buttonOnly":true,"className":"my-custom-class"} /-->`,
	attributes: {
		buttonOnly: true,
		className: 'my-custom-class',
	},
	innerBlocks: [],
};

/**
 * v3: Block with broken button (tagName attribute due to unfiltered_html).
 * The button innerBlock has tagName="button" which needs to be removed.
 */
export const v3Markup = {
	html: `<!-- wp:activitypub/follow-me -->
<div class="wp-block-activitypub-follow-me"><!-- wp:button {"tagName":"button"} -->
<button class="wp-block-button__link wp-element-button">Follow</button>
<!-- /wp:button --></div>
<!-- /wp:activitypub/follow-me -->`,
	attributes: {},
	innerBlocks: [
		{
			name: 'core/button',
			attributes: {
				tagName: 'button',
			},
			originalContent: '<button class="wp-block-button__link wp-element-button">Follow</button>',
		},
	],
};

/**
 * v3 with custom button text.
 */
export const v3MarkupCustomText = {
	html: `<!-- wp:activitypub/follow-me -->
<div class="wp-block-activitypub-follow-me"><!-- wp:button {"tagName":"button"} -->
<button class="wp-block-button__link wp-element-button">Subscribe Now</button>
<!-- /wp:button --></div>
<!-- /wp:activitypub/follow-me -->`,
	attributes: {},
	innerBlocks: [
		{
			name: 'core/button',
			attributes: {
				tagName: 'button',
			},
			originalContent: '<button class="wp-block-button__link wp-element-button">Subscribe Now</button>',
		},
	],
};

/**
 * Block without buttonText, buttonOnly, or broken button (not eligible for any migration).
 */
export const noMigrationNeeded = {
	html: `<!-- wp:activitypub/follow-me /-->`,
	attributes: {},
	innerBlocks: [],
};
