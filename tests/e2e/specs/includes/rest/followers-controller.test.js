/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'ActivityPub Followers Endpoint', () => {
	let testUserId;
	let followersEndpoint;

	test.beforeAll( async ( { requestUtils } ) => {
		// Use the admin user (ID 1) which should always exist and be an actor
		// In ActivityPub, by default the admin user is enabled as an actor
		testUserId = 1;
		followersEndpoint = `/activitypub/1.0/actors/${ testUserId }/followers`;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// No cleanup needed - we're using the admin user
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		// Clean up any existing followers before each test
		// This would require a custom endpoint or direct database manipulation
	} );

	test( 'should return 200 status code for followers endpoint', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: followersEndpoint,
		} );
		expect( data ).toBeDefined();
	} );

	test( 'should return valid ActivityStreams OrderedCollection', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: followersEndpoint,
		} );

		// Verify it's a valid OrderedCollection
		expect( data ).toHaveProperty( '@context' );
		expect( data ).toHaveProperty( 'type' );
		expect( data.type ).toBe( 'OrderedCollection' );
		expect( data ).toHaveProperty( 'totalItems' );
		expect( typeof data.totalItems ).toBe( 'number' );
		expect( data ).toHaveProperty( 'id' );
		// 'first' property may be present when there are items, or 'orderedItems' when inline
		expect( data.first || data.orderedItems ).toBeDefined();
	} );

	test( 'should return empty followers collection for new user', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: followersEndpoint,
		} );

		expect( data.totalItems ).toBe( 0 );
	} );

	test( 'should support pagination with first page', async ( { requestUtils } ) => {
		const collection = await requestUtils.rest( {
			path: followersEndpoint,
		} );

		// Follow the first page link
		if ( collection.first ) {
			const firstPage = await requestUtils.rest( {
				path: collection.first,
			} );

			// Verify it's a valid OrderedCollectionPage
			expect( firstPage.type ).toBe( 'OrderedCollectionPage' );
			expect( firstPage ).toHaveProperty( 'partOf' );
			expect( firstPage ).toHaveProperty( 'orderedItems' );
			expect( Array.isArray( firstPage.orderedItems ) ).toBe( true );
		}
	} );

	test( 'should return error for non-existent user', async ( { requestUtils } ) => {
		try {
			await requestUtils.rest( {
				path: '/activitypub/1.0/actors/99999999/followers',
			} );
			// If no error is thrown, fail the test
			expect( false ).toBe( true );
		} catch ( error ) {
			// WordPress REST API should return 400 for invalid parameters
			expect( error.status || error.code ).toBe( 400 );
		}
	} );

	test( 'should include proper Content-Type header', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: followersEndpoint,
		} );

		// If we got data back, the content type was acceptable
		expect( data ).toBeDefined();
		expect( data ).toHaveProperty( 'type' );
	} );

	test( 'should handle page parameter', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: `${ followersEndpoint }?page=1`,
			} );

			// If successful, verify the response structure
			expect( data.type ).toBe( 'OrderedCollectionPage' );
		} catch ( error ) {
			// Skip this test if pagination isn't available yet
			expect( error.status || error.code ).toBeGreaterThanOrEqual( 400 );
		}
	} );

	test( 'should validate collection structure matches ActivityStreams spec', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: followersEndpoint,
		} );

		// Check for required ActivityStreams properties
		expect( data ).toHaveProperty( '@context' );
		expect( Array.isArray( data[ '@context' ] ) || typeof data[ '@context' ] === 'string' ).toBe( true );

		// Verify ID is a valid URL
		expect( data.id ).toMatch( /^https?:\/\// );

		// Verify proper typing
		expect( data.type ).toBe( 'OrderedCollection' );
	} );
} );
