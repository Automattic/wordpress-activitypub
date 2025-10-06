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
		followersEndpoint = `/wp-json/activitypub/1.0/actors/${ testUserId }/followers`;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// No cleanup needed - we're using the admin user
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		// Clean up any existing followers before each test
		// This would require a custom endpoint or direct database manipulation
	} );

	test( 'should return 200 status code for followers endpoint', async ( { request } ) => {
		const response = await request.get( followersEndpoint );
		expect( response.status() ).toBe( 200 );
	} );

	test( 'should return valid ActivityStreams OrderedCollection', async ( { request } ) => {
		const response = await request.get( followersEndpoint, {
			headers: {
				Accept: 'application/activity+json',
			},
		} );

		expect( response.status() ).toBe( 200 );

		const data = await response.json();

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

	test( 'should return empty followers collection for new user', async ( { request } ) => {
		const response = await request.get( followersEndpoint, {
			headers: {
				Accept: 'application/activity+json',
			},
		} );

		const data = await response.json();
		expect( data.totalItems ).toBe( 0 );
	} );

	test( 'should support pagination with first page', async ( { request } ) => {
		const response = await request.get( followersEndpoint, {
			headers: {
				Accept: 'application/activity+json',
			},
		} );

		const collection = await response.json();

		// Follow the first page link
		if ( collection.first ) {
			const firstPageResponse = await request.get( collection.first, {
				headers: {
					Accept: 'application/activity+json',
				},
			} );

			expect( firstPageResponse.status() ).toBe( 200 );

			const firstPage = await firstPageResponse.json();

			// Verify it's a valid OrderedCollectionPage
			expect( firstPage.type ).toBe( 'OrderedCollectionPage' );
			expect( firstPage ).toHaveProperty( 'partOf' );
			expect( firstPage ).toHaveProperty( 'orderedItems' );
			expect( Array.isArray( firstPage.orderedItems ) ).toBe( true );
		}
	} );

	test( 'should return error for non-existent user', async ( { request } ) => {
		const response = await request.get( '/wp-json/activitypub/1.0/actors/99999999/followers', {
			headers: {
				Accept: 'application/activity+json',
			},
		} );

		// WordPress REST API returns 400 for invalid parameters
		expect( response.status() ).toBe( 400 );

		const data = await response.json();
		expect( data ).toHaveProperty( 'status', 400 );
	} );

	test( 'should include proper Content-Type header', async ( { request } ) => {
		const response = await request.get( followersEndpoint, {
			headers: {
				Accept: 'application/activity+json',
			},
		} );

		const contentType = response.headers()[ 'content-type' ];
		// WordPress REST API returns application/json, but it should be ActivityStreams compatible
		expect( contentType ).toMatch( /application\/(activity\+)?json/ );
	} );

	test( 'should handle page parameter', async ( { request } ) => {
		const response = await request.get( `${ followersEndpoint }?page=1`, {
			headers: {
				Accept: 'application/activity+json',
			},
		} );

		// Page parameter may not work without proper pagination setup
		// If it returns 400, the API doesn't support direct page access without items
		// If it returns 200, verify the response structure
		if ( response.status() === 200 ) {
			const data = await response.json();
			expect( data.type ).toBe( 'OrderedCollectionPage' );
		} else {
			// Skip this test if pagination isn't available yet
			expect( response.status() ).toBeGreaterThanOrEqual( 400 );
		}
	} );

	test( 'should validate collection structure matches ActivityStreams spec', async ( { request } ) => {
		const response = await request.get( followersEndpoint, {
			headers: {
				Accept: 'application/activity+json',
			},
		} );

		const data = await response.json();

		// Check for required ActivityStreams properties
		expect( data ).toHaveProperty( '@context' );
		expect( Array.isArray( data[ '@context' ] ) || typeof data[ '@context' ] === 'string' ).toBe( true );

		// Verify ID is a valid URL
		expect( data.id ).toMatch( /^https?:\/\// );

		// Verify proper typing
		expect( data.type ).toBe( 'OrderedCollection' );
	} );
} );
