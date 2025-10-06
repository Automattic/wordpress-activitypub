/**
 * External dependencies
 */
import { request } from '@playwright/test';

/**
 * WordPress dependencies
 */
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Global setup for ActivityPub E2E tests.
 *
 * @param {import('@playwright/test').FullConfig} config
 * @returns {Promise<void>}
 */
async function globalSetup( config ) {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const storageStatePath = typeof storageState === 'string' ? storageState : undefined;

	const requestContext = await request.newContext( {
		baseURL,
	} );

	const requestUtils = new RequestUtils( requestContext, {
		storageStatePath,
	} );

	// Authenticate and save the storageState to disk.
	await requestUtils.setupRest();

	// Ensure pretty permalinks are enabled for ActivityPub REST API endpoints
	// This is critical for the /wp-json/ routes to work properly
	await requestUtils.updateSiteSettings( {
		permalink_structure: '/%year%/%monthnum%/%postname%/',
	} );

	// Reset the test environment before running the tests.
	await Promise.all( [
		requestUtils.activateTheme( 'twentytwentyone' ),
		requestUtils.deleteAllPosts(),
		requestUtils.resetPreferences(),
	] );

	// Note: ActivityPub plugin should already be active in the test environment
	// If you need to activate it, use wp-cli instead:
	// wp-env run tests-cli wp plugin activate activitypub

	await requestContext.dispose();
}

export default globalSetup;
