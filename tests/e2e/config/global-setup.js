/**
 * WordPress dependencies
 */
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Global setup for ActivityPub E2E tests.
 * Based on WordPress core patterns.
 *
 * @param {import('@playwright/test').FullConfig} config
 * @returns {Promise<void>}
 */
async function globalSetup( config ) {
	const { storageState, baseURL } = config.projects[ 0 ].use;

	// Setup request utils with authentication
	const requestUtils = await RequestUtils.setup( {
		baseURL,
		storageStatePath: storageState,
	} );

	// Clean up test environment
	// Note: Keep this minimal to avoid REST API issues during setup
	await requestUtils.activateTheme( 'twentytwentyone' );
	await requestUtils.deleteAllPosts();

	await requestUtils.rest.dispose();
}

export default globalSetup;
