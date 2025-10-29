/**
 * WordPress dependencies
 */
import { __dangerousOptInToUnstableAPIsOnlyForCoreModules } from '@wordpress/private-apis';

/**
 * Unlock private APIs from WordPress packages.
 *
 * WARNING: This uses unstable WordPress APIs that are not intended for use
 * in themes or plugins. These APIs may change or be removed in future versions
 * of WordPress without notice.
 */
export const { unlock } = __dangerousOptInToUnstableAPIsOnlyForCoreModules(
	'I acknowledge private features are not for use in themes or plugins and doing so will break in the next version of WordPress.',
	'@wordpress/activitypub'
);
