/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'Environment Setup', () => {
	test( 'should have pretty permalinks enabled', async ( { request } ) => {
		// Test that pretty permalinks work by checking if REST API routes are accessible
		// If permalinks aren't set, /wp-json/ routes won't work
		const response = await request.get( '/wp-json/' );

		expect( response.status() ).toBe( 200 );

		const data = await response.json();
		// If we can access wp-json and get routes, permalinks are working
		expect( data ).toHaveProperty( 'routes' );
		expect( Object.keys( data.routes ).length ).toBeGreaterThan( 0 );
	} );

	test( 'should have ActivityPub plugin active', async ( { request } ) => {
		// Test that the ActivityPub REST API namespace is accessible
		const response = await request.get( '/wp-json/activitypub/1.0' );

		expect( response.status() ).toBe( 200 );

		const data = await response.json();
		expect( data ).toHaveProperty( 'namespace', 'activitypub/1.0' );
	} );

	test( 'should have rewrite rules active', async ( { request } ) => {
		// Test that the wp-json endpoint works (requires rewrite rules)
		const response = await request.get( '/wp-json/' );

		expect( response.status() ).toBe( 200 );

		const data = await response.json();
		expect( data ).toHaveProperty( 'namespaces' );
		expect( data.namespaces ).toContain( 'activitypub/1.0' );
	} );
} );
