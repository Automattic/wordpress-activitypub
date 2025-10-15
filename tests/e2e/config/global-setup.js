/**
 * External dependencies
 */
import { request } from '@playwright/test';
import { exec } from 'child_process';
import { promisify } from 'util';

/**
 * WordPress dependencies
 */
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';

const execAsync = promisify( exec );

/**
 * Global setup for ActivityPub E2E tests.
 *
 * @param {import('@playwright/test').FullConfig} config
 * @returns {Promise<void>}
 */
async function globalSetup( config ) {
	// Set up pretty permalinks before any REST API calls
	try {
		await execAsync( 'npx wp-env run tests-cli wp rewrite structure "/%year%/%monthnum%/%postname%/"' );
		await execAsync( 'npx wp-env run tests-cli wp rewrite flush' );
	} catch ( error ) {
		console.error( 'Failed to set up permalinks:', error );
	}

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

	// Reset the test environment before running the tests.
	await Promise.all( [
		requestUtils.deleteAllPosts(),
		requestUtils.deleteAllBlocks(),
		requestUtils.resetPreferences(),
	] );

	await requestContext.dispose();
}

export default globalSetup;
