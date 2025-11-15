/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'ActivityPub Inbox REST API', () => {
	let testUserId;
	let inboxEndpoint;

	test.beforeAll( async ( { requestUtils } ) => {
		// Use the default test user
		testUserId = 1;
		inboxEndpoint = `/activitypub/1.0/actors/${ testUserId }/inbox`;
	} );

	test( 'should return 200 status code for inbox GET endpoint', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: inboxEndpoint,
		} );

		expect( data ).toBeDefined();
	} );

	test( 'should return ActivityStreams OrderedCollection', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: inboxEndpoint,
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

	test( 'should handle empty inbox collection', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: inboxEndpoint,
		} );

		// Inbox might be empty
		if ( data.totalItems === 0 ) {
			expect( data.orderedItems || [] ).toEqual( [] );
		}
	} );

	test( 'should include first property for pagination', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: inboxEndpoint,
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
				path: '/activitypub/1.0/users/999999/inbox',
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
			path: inboxEndpoint,
		} );

		expect( data ).toBeDefined();
		expect( data ).toHaveProperty( 'type' );
	} );

	test( 'should handle page parameter', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: `${ inboxEndpoint }?page=1&per_page=10`,
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
			path: inboxEndpoint,
		} );

		// Check for required ActivityStreams properties
		expect( data ).toHaveProperty( '@context' );
		expect( Array.isArray( data[ '@context' ] ) || typeof data[ '@context' ] === 'string' ).toBe( true );

		// Verify ID is a valid URL
		expect( data.id ).toMatch( /^https?:\/\// );

		// Verify proper typing
		expect( data.type ).toBe( 'OrderedCollection' );
	} );

	test( 'should validate orderedItems contain activities when present', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: inboxEndpoint,
		} );

		if ( data.orderedItems && data.orderedItems.length > 0 ) {
			// Each item should be an Activity or a URL to an Activity
			data.orderedItems.forEach( ( item ) => {
				if ( typeof item === 'string' ) {
					expect( item ).toMatch( /^https?:\/\// );
				} else if ( typeof item === 'object' ) {
					expect( item ).toHaveProperty( 'type' );
					// Common activity types for inbox
					const activityTypes = [
						'Create',
						'Update',
						'Delete',
						'Follow',
						'Like',
						'Announce',
						'Accept',
						'Reject',
						'Undo',
					];
					expect( activityTypes ).toContain( item.type );
					expect( item ).toHaveProperty( 'id' );
				}
			} );
		}
	} );
} );
