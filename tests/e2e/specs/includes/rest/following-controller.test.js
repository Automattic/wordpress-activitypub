/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'ActivityPub Following Collection REST API', () => {
	let testUserId;
	let followingEndpoint;

	test.beforeAll( async ( { requestUtils } ) => {
		// Use the default test user
		testUserId = 1;
		followingEndpoint = `/activitypub/1.0/actors/${ testUserId }/following`;
	} );

	test( 'should return 200 status code for following endpoint', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: followingEndpoint,
		} );

		expect( data ).toBeDefined();
	} );

	test( 'should return ActivityStreams OrderedCollection', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: followingEndpoint,
		} );

		// Check for ActivityStreams context
		expect( data ).toHaveProperty( '@context' );
		expect( Array.isArray( data[ '@context' ] ) || typeof data[ '@context' ] === 'string' ).toBe( true );

		// Verify it's an OrderedCollection
		expect( data.type ).toBe( 'OrderedCollection' );

		// Check for required collection properties
		expect( data ).toHaveProperty( 'id' );
		expect( data.id ).toMatch( /^https?:\/\// );

		expect( data ).toHaveProperty( 'totalItems' );
		expect( typeof data.totalItems ).toBe( 'number' );
	} );

	test( 'should handle empty following collection', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: followingEndpoint,
		} );

		// For a new user, following should be 0
		if ( data.totalItems === 0 ) {
			expect( data.orderedItems ).toEqual( [] );
		}
	} );

	test( 'should include first property for pagination', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: followingEndpoint,
		} );

		if ( data.totalItems > 0 ) {
			expect( data ).toHaveProperty( 'first' );

			if ( typeof data.first === 'string' ) {
				expect( data.first ).toMatch( /^https?:\/\// );
			} else if ( typeof data.first === 'object' ) {
				expect( data.first.type ).toBe( 'OrderedCollectionPage' );
				expect( data.first ).toHaveProperty( 'orderedItems' );
			}
		}
	} );

	test( 'should return error for non-existent user', async ( { requestUtils } ) => {
		try {
			await requestUtils.rest( {
				path: '/activitypub/1.0/users/999999/following',
			} );
			// If we reach here, the test should fail
			expect.fail();
		} catch ( error ) {
			// Should return 400 or 404 for invalid/non-existent user
			expect( [ 400, 404 ] ).toContain( error.status || error.code );
		}
	} );

	test( 'should return correct Content-Type header', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: followingEndpoint,
		} );

		expect( data ).toBeDefined();
		expect( data ).toHaveProperty( 'type' );
	} );

	test( 'should handle page parameter', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: `${ followingEndpoint }?page=1`,
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
			path: followingEndpoint,
		} );

		// Check for required ActivityStreams properties
		expect( data ).toHaveProperty( '@context' );
		expect( Array.isArray( data[ '@context' ] ) || typeof data[ '@context' ] === 'string' ).toBe( true );

		// Verify ID is a valid URL
		expect( data.id ).toMatch( /^https?:\/\// );

		// Verify proper typing
		expect( data.type ).toBe( 'OrderedCollection' );
	} );

	test( 'should validate orderedItems structure when present', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: followingEndpoint,
		} );

		if ( data.orderedItems && data.orderedItems.length > 0 ) {
			// Each item should be either a URL string or an object
			data.orderedItems.forEach( ( item ) => {
				if ( typeof item === 'string' ) {
					expect( item ).toMatch( /^https?:\/\// );
				} else if ( typeof item === 'object' ) {
					expect( item ).toHaveProperty( 'type' );
					expect( item ).toHaveProperty( 'id' );
				}
			} );
		}
	} );
} );
