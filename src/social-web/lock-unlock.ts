/**
 * WordPress dependencies
 */
import { __dangerousOptInToUnstableAPIsOnlyForCoreModules } from '@wordpress/private-apis';

/**
 * Unlock private APIs from WordPress packages.
 *
 * We use '@wordpress/edit-site' as the module name since it's an approved
 * core module that uses the router. This allows us to access the same
 * private router APIs.
 */
export const { lock, unlock } = __dangerousOptInToUnstableAPIsOnlyForCoreModules(
	'I acknowledge private features are not for use in themes or plugins and doing so will break in the next version of WordPress.',
	'@wordpress/edit-site'
);
