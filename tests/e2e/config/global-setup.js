/**
 * External dependencies
 */
import { request } from '@playwright/test';

/**
 * WordPress dependencies
 */
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

/**
 * Wait for WordPress to be ready.
 *
 * @param {string} baseURL - The base URL of the WordPress site
 * @param {number} maxAttempts - Maximum number of attempts
 * @returns {Promise<void>}
 */
async function waitForWordPress( baseURL, maxAttempts = 30 ) {
	for ( let attempt = 1; attempt <= maxAttempts; attempt++ ) {
		try {
			const requestContext = await request.newContext( { baseURL } );
			const response = await requestContext.get( '/wp-json/' );
			await requestContext.dispose();

			if ( response.ok() ) {
				console.log( `✓ WordPress is ready after ${ attempt } attempt(s)` );
				return;
			}
		} catch ( error ) {
			// WordPress not ready yet
		}

		if ( attempt < maxAttempts ) {
			console.log( `Attempt ${ attempt }/${ maxAttempts }: WordPress not ready yet, waiting...` );
			await new Promise( ( resolve ) => setTimeout( resolve, 2000 ) );
		}
	}

	throw new Error( 'WordPress failed to become ready' );
}

/**
 * Global setup for ActivityPub E2E tests.
 *
 * @param {import('@playwright/test').FullConfig} config
 * @returns {Promise<void>}
 */
async function globalSetup( config ) {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const storageStatePath = typeof storageState === 'string' ? storageState : undefined;

	// Wait for WordPress to be ready before proceeding
	console.log( 'Waiting for WordPress to be ready...' );
	await waitForWordPress( baseURL );

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
