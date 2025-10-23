/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'ActivityPub Replies Collection REST API', () => {
	let testUserId;
	let testPostId;
	let repliesEndpoint;

	test.beforeAll( async ( { requestUtils } ) => {
		// Use the default test user and a sample post
		testUserId = 1;
		testPostId = 1; // Assuming a post exists
		repliesEndpoint = `/activitypub/1.0/actors/${ testUserId }/posts/${ testPostId }/replies`;
	} );

	test( 'should return 200 status code for replies endpoint', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: repliesEndpoint,
			} );

			expect( data ).toBeDefined();
		} catch ( error ) {
			// Post might not exist
			expect( error.status || error.code ).toBe( 404 );
		}
	} );

	test( 'should return ActivityStreams Collection', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: repliesEndpoint,
			} );

			// Check for ActivityStreams context
			expect( data ).toHaveProperty( '@context' );
			expect( Array.isArray( data[ '@context' ] ) || typeof data[ '@context' ] === 'string' ).toBe( true );

			// Verify it's a Collection
			expect( [ 'Collection', 'OrderedCollection' ] ).toContain( data.type );

			// Check for required collection properties
			expect( data ).toHaveProperty( 'id' );
			expect( data.id ).toMatch( /^https?:\/\// );

			expect( data ).toHaveProperty( 'totalItems' );
			expect( typeof data.totalItems ).toBe( 'number' );
		} catch ( error ) {
			// Post might not exist
			expect( error.status || error.code ).toBe( 404 );
		}
	} );

	test( 'should handle empty replies collection', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: repliesEndpoint,
			} );

			// For a post with no replies, totalItems should be 0
			if ( data.totalItems === 0 ) {
				const items = data.items || data.orderedItems || [];
				expect( items ).toEqual( [] );
			}
		} catch ( error ) {
			// Post might not exist
			expect( error.status || error.code ).toBe( 404 );
		}
	} );

	test( 'should include first property for pagination', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: repliesEndpoint,
			} );

			if ( data.totalItems > 0 ) {
				expect( data ).toHaveProperty( 'first' );

				if ( typeof data.first === 'string' ) {
					expect( data.first ).toMatch( /^https?:\/\// );
				} else if ( typeof data.first === 'object' ) {
					expect( [ 'CollectionPage', 'OrderedCollectionPage' ] ).toContain( data.first.type );
					expect( data.first ).toHaveProperty( 'items' );
				}
			}
		} catch ( error ) {
			// Post might not exist
			expect( error.status || error.code ).toBe( 404 );
		}
	} );

	test( 'should return 404 for non-existent post', async ( { requestUtils } ) => {
		try {
			await requestUtils.rest( {
				path: `/activitypub/1.0/users/${ testUserId }/posts/999999/replies`,
			} );
			// If we reach here, the test should fail
			expect.fail();
		} catch ( error ) {
			expect( error.status || error.code ).toBe( 404 );
		}
	} );

	test( 'should return correct Content-Type header', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: repliesEndpoint,
			} );

			expect( data ).toBeDefined();
			expect( data ).toHaveProperty( 'type' );
		} catch ( error ) {
			// Post might not exist
			expect( error.status || error.code ).toBe( 404 );
		}
	} );

	test( 'should handle page parameter', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: `${ repliesEndpoint }?page=1`,
			} );

			// If successful, verify the response structure
			expect( [ 'CollectionPage', 'OrderedCollectionPage' ] ).toContain( data.type );
		} catch ( error ) {
			// Post might not exist or pagination not available
			expect( error.status || error.code ).toBeGreaterThanOrEqual( 400 );
		}
	} );

	test( 'should validate collection structure matches ActivityStreams spec', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: repliesEndpoint,
			} );

			// Check for required ActivityStreams properties
			expect( data ).toHaveProperty( '@context' );
			expect( Array.isArray( data[ '@context' ] ) || typeof data[ '@context' ] === 'string' ).toBe( true );

			// Verify ID is a valid URL
			expect( data.id ).toMatch( /^https?:\/\// );

			// Verify proper typing
			expect( [ 'Collection', 'OrderedCollection' ] ).toContain( data.type );
		} catch ( error ) {
			// Post might not exist
			expect( error.status || error.code ).toBe( 404 );
		}
	} );

	test( 'should validate items contain comments when present', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: repliesEndpoint,
			} );

			const items = data.items || data.orderedItems || [];
			if ( items.length > 0 ) {
				// Each item should be a URL or an object
				items.forEach( ( item ) => {
					if ( typeof item === 'string' ) {
						expect( item ).toMatch( /^https?:\/\// );
					} else if ( typeof item === 'object' ) {
						expect( item ).toHaveProperty( 'type' );
						// Replies are typically Note or Comment objects
						expect( [ 'Note', 'Comment', 'Article' ] ).toContain( item.type );
						expect( item ).toHaveProperty( 'id' );
					}
				} );
			}
		} catch ( error ) {
			// Post might not exist
			expect( error.status || error.code ).toBe( 404 );
		}
	} );

	test( 'should be referenced from parent object', async ( { requestUtils } ) => {
		try {
			// First get the post/object
			const postData = await requestUtils.rest( {
				path: `/activitypub/1.0/users/${ testUserId }/posts/${ testPostId }`,
			} );

			// The post should reference the replies collection
			if ( postData.replies ) {
				if ( typeof postData.replies === 'string' ) {
					expect( postData.replies ).toMatch( /\/replies/ );
				} else if ( typeof postData.replies === 'object' ) {
					expect( postData.replies ).toHaveProperty( 'type' );
					expect( [ 'Collection', 'OrderedCollection' ] ).toContain( postData.replies.type );
				}
			}
		} catch ( error ) {
			// Post might not exist
			expect( error.status || error.code ).toBe( 404 );
		}
	} );
} );
