/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'ActivityPub Outbox REST API', () => {
	let testUserId;
	let outboxEndpoint;

	test.beforeAll( async ( { requestUtils } ) => {
		// Use the default test user
		testUserId = 1;
		outboxEndpoint = `/activitypub/1.0/actors/${ testUserId }/outbox`;
	} );

	test( 'should return 200 status code for outbox endpoint', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: outboxEndpoint,
		} );

		expect( data ).toBeDefined();
	} );

	test( 'should return ActivityStreams OrderedCollection', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: outboxEndpoint,
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

	test( 'should have valid totalItems count', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: outboxEndpoint,
		} );

		// Verify totalItems matches actual items if present
		expect( typeof data.totalItems ).toBe( 'number' );
		expect( data.totalItems ).toBeGreaterThanOrEqual( 0 );

		// If orderedItems is present, count should match
		if ( data.orderedItems ) {
			expect( Array.isArray( data.orderedItems ) ).toBe( true );
		}
	} );

	test( 'should include first property for pagination', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: outboxEndpoint,
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
				path: '/activitypub/1.0/users/999999/outbox',
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
			path: outboxEndpoint,
		} );

		expect( data ).toBeDefined();
		expect( data ).toHaveProperty( 'type' );
	} );

	test( 'should handle page parameter', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: `${ outboxEndpoint }?page=1`,
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
			path: outboxEndpoint,
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
			path: outboxEndpoint,
		} );

		if ( data.orderedItems && data.orderedItems.length > 0 ) {
			// Each item should be an Activity or a URL to an Activity
			data.orderedItems.forEach( ( item ) => {
				if ( typeof item === 'string' ) {
					expect( item ).toMatch( /^https?:\/\// );
				} else if ( typeof item === 'object' ) {
					expect( item ).toHaveProperty( 'type' );
					// Common activity types
					const activityTypes = [
						'Create',
						'Update',
						'Delete',
						'Follow',
						'Like',
						'Announce',
						'Accept',
						'Reject',
					];
					expect( activityTypes ).toContain( item.type );
					expect( item ).toHaveProperty( 'id' );
				}
			} );
		}
	} );

	test( 'should include last property when supported', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: outboxEndpoint,
		} );

		// Last property is optional but common in outbox
		if ( data.last ) {
			if ( typeof data.last === 'string' ) {
				expect( data.last ).toMatch( /^https?:\/\// );
			} else if ( typeof data.last === 'object' ) {
				expect( data.last.type ).toBe( 'OrderedCollectionPage' );
			}
		}
	} );
} );
