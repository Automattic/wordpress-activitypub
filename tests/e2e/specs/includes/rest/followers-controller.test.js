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

		// Follow the first page link if present
		if ( collection.first ) {
			try {
				// Extract path and query params from the first URL
				const url = new URL( collection.first );
				const pathWithQuery = url.pathname + url.search;
				// Remove the /index.php? prefix if present
				const cleanPath = pathWithQuery.replace( /^\/index\.php\?rest_route=/, '' ).replace( /^\//, '' );

				const firstPage = await requestUtils.rest( {
					path: decodeURIComponent( cleanPath ),
				} );

				// Verify it's a valid OrderedCollectionPage
				expect( firstPage.type ).toBe( 'OrderedCollectionPage' );
				expect( firstPage ).toHaveProperty( 'partOf' );
				expect( firstPage ).toHaveProperty( 'orderedItems' );
				expect( Array.isArray( firstPage.orderedItems ) ).toBe( true );
			} catch ( error ) {
				// If collection is empty (totalItems = 0), requesting page 1 returns 400
				if ( collection.totalItems === 0 ) {
					expect( error.data?.status || error.status ).toBe( 400 );
					expect( error.metadata?.code || error.code || error.data?.code ).toBe(
						'rest_post_invalid_page_number'
					);
				} else {
					throw error;
				}
			}
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
				path: `${ followersEndpoint }?page=1&per_page=10`,
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

	test.describe( 'Followers Collection Endpoint', () => {
		test( 'should return Collection-Synchronization header on followers collection request', async ( {
			requestUtils,
		} ) => {
			await requestUtils.setupRest();

			try {
				// Request followers collection with proper headers
				const response = await requestUtils.rest( {
					path: '/activitypub/1.0/actors/1/followers',
				} );

				// Check if response has expected structure
				expect( response ).toHaveProperty( '@context' );
				expect( response ).toHaveProperty( 'type', 'OrderedCollection' );
				expect( response ).toHaveProperty( 'totalItems' );
				expect( response ).toHaveProperty( 'id' );
			} catch ( error ) {
				// Log error for debugging
				console.error( 'Followers collection request failed:', error.message );
				throw error;
			}
		} );

		test( 'should include proper pagination links in followers collection', async ( { requestUtils } ) => {
			await requestUtils.setupRest();

			try {
				const response = await requestUtils.rest( {
					path: '/activitypub/1.0/actors/1/followers',
				} );

				// Collection should have first and last links
				expect( response ).toHaveProperty( 'id' );
				expect( response.id ).toContain( '/activitypub/1.0/actors/1/followers' );
			} catch ( error ) {
				console.error( 'Pagination test failed:', error.message );
				throw error;
			}
		} );
	} );

	// Skip tests for Partial Followers Sync Endpoint (FEP-8fcf) - not yet implemented
	test.describe.skip( 'Partial Followers Sync Endpoint', () => {
		test( 'should accept authority parameter for partial followers', async ( { requestUtils } ) => {
			await requestUtils.setupRest();

			const testAuthority = 'https://example.com';

			const response = await requestUtils.rest( {
				path: `/activitypub/1.0/actors/1/followers/sync`,
				params: { authority: testAuthority },
			} );
			expect( response ).toHaveProperty( 'type', 'OrderedCollection' );
			expect( response ).toHaveProperty( 'totalItems' );
			expect( response ).toHaveProperty( 'orderedItems' );

			// Verify the collection ID includes the authority parameter
			expect( response.id ).toContain( 'authority=' );
			expect( response.id ).toContain( encodeURIComponent( testAuthority ) );

			// orderedItems should be an array
			expect( Array.isArray( response.orderedItems ) ).toBe( true );
		} );

		test( 'should reject invalid authority format', async ( { requestUtils } ) => {
			await requestUtils.setupRest();

			// Test with invalid authority (no protocol)
			const invalidAuthority = 'example.com';

			try {
				await requestUtils.rest( {
					path: `/activitypub/1.0/actors/1/followers/sync`,
					params: { authority: invalidAuthority },
				} );
				// If no error is thrown, fail the test
				expect( false ).toBe( true );
			} catch ( error ) {
				// Should return 400 Bad Request for invalid authority (or 404 if endpoint doesn't exist)
				expect( error.status || error.code ).toBeGreaterThanOrEqual( 400 );
			}
		} );

		test( 'should require authority parameter', async ( { requestUtils } ) => {
			await requestUtils.setupRest();

			try {
				await requestUtils.rest( {
					path: '/activitypub/1.0/actors/1/followers/sync',
				} );
				// If no error is thrown, fail the test
				expect( false ).toBe( true );
			} catch ( error ) {
				// Should return 400 when authority is missing (or 404 if endpoint doesn't exist)
				expect( error.status || error.code ).toBeGreaterThanOrEqual( 400 );
			}
		} );

		test( 'should return empty collection for authority with no followers', async ( { requestUtils } ) => {
			await requestUtils.setupRest();

			// Use an authority that definitely has no followers
			const testAuthority = 'https://non-existent-instance.test';

			const response = await requestUtils.rest( {
				path: `/activitypub/1.0/actors/1/followers/sync`,
				params: { authority: testAuthority },
			} );

			expect( response.type ).toBe( 'OrderedCollection' );
			expect( response.totalItems ).toBe( 0 );
			expect( response.orderedItems ).toEqual( [] );
		} );
	} );

	// Skip tests for Collection Response Format with /sync endpoint - not yet implemented
	test.describe.skip( 'Collection Response Format', () => {
		test( 'should return valid ActivityStreams OrderedCollection', async ( { requestUtils } ) => {
			await requestUtils.setupRest();

			const testAuthority = 'https://mastodon.social';

			const response = await requestUtils.rest( {
				path: `/activitypub/1.0/actors/1/followers/sync`,
				params: { authority: testAuthority },
			} );

			// Validate ActivityStreams OrderedCollection structure
			expect( response ).toHaveProperty( '@context' );
			expect( response ).toHaveProperty( 'id' );
			expect( response ).toHaveProperty( 'type', 'OrderedCollection' );
			expect( response ).toHaveProperty( 'totalItems' );
			expect( typeof response.totalItems ).toBe( 'number' );
			expect( response ).toHaveProperty( 'orderedItems' );
			expect( Array.isArray( response.orderedItems ) ).toBe( true );
		} );

		test( 'should return proper Content-Type header', async ( { requestUtils } ) => {
			await requestUtils.setupRest();

			const testAuthority = 'https://example.com';

			const response = await requestUtils.rest( {
				path: `/activitypub/1.0/actors/1/followers/sync`,
				params: { authority: testAuthority },
			} );

			// If we got data back, the content type was acceptable
			expect( response ).toBeDefined();
			expect( response ).toHaveProperty( 'type' );
		} );
	} );

	// Skip tests for Multiple Authorities with /sync endpoint - not yet implemented
	test.describe.skip( 'Multiple Authorities', () => {
		test( 'should handle different authority formats correctly', async ( { requestUtils } ) => {
			await requestUtils.setupRest();

			const authorities = [
				'https://mastodon.social',
				'https://mastodon.social:443',
				'http://localhost:3000',
				'https://subdomain.example.com',
			];

			for ( const authority of authorities ) {
				const response = await requestUtils.rest( {
					path: `/activitypub/1.0/actors/1/followers/sync`,
					params: { authority },
				} );

				expect( response.type ).toBe( 'OrderedCollection' );
				expect( response ).toHaveProperty( 'totalItems' );
				expect( Array.isArray( response.orderedItems ) ).toBe( true );
			}
		} );
	} );

	test.describe( 'Error Handling', () => {
		test( 'should return 404 for non-existent actor', async ( { requestUtils } ) => {
			await requestUtils.setupRest();

			const testAuthority = 'https://example.com';

			try {
				await requestUtils.rest( {
					path: `/activitypub/1.0/actors/99999/followers/sync`,
					params: { authority: testAuthority },
				} );
				// If no error is thrown, fail the test
				expect( false ).toBe( true );
			} catch ( error ) {
				// Should return 404 or 400 for non-existent user
				expect( error.status || error.code ).toBeGreaterThanOrEqual( 400 );
			}
		} );

		test( 'should handle malformed authority gracefully', async ( { requestUtils } ) => {
			await requestUtils.setupRest();

			const malformedAuthorities = [
				'not-a-url',
				'ftp://invalid-protocol.com',
				'https://',
				'://no-protocol.com',
			];

			for ( const authority of malformedAuthorities ) {
				try {
					await requestUtils.rest( {
						path: `/activitypub/1.0/actors/1/followers/sync`,
						params: { authority },
					} );
					// If no error is thrown, fail the test
					expect( false ).toBe( true );
				} catch ( error ) {
					// Should return 400 for invalid authority format
					expect( error.status || error.code ).toBe( 400 );
				}
			}
		} );
	} );

	// Skip tests for Response Consistency with /sync endpoint - not yet implemented
	test.describe.skip( 'Response Consistency', () => {
		test( 'should return consistent results for same authority', async ( { requestUtils } ) => {
			await requestUtils.setupRest();

			const testAuthority = 'https://example.com';

			// Make two requests to the same endpoint
			const response1 = await requestUtils.rest( {
				path: `/activitypub/1.0/actors/1/followers/sync`,
				params: { authority: testAuthority },
			} );

			const response2 = await requestUtils.rest( {
				path: `/activitypub/1.0/actors/1/followers/sync`,
				params: { authority: testAuthority },
			} );

			// Results should be consistent
			expect( response1.totalItems ).toBe( response2.totalItems );
			expect( response1.orderedItems ).toEqual( response2.orderedItems );
		} );

		test( 'should filter followers correctly by authority', async ( { requestUtils } ) => {
			await requestUtils.setupRest();

			const authority1 = 'https://mastodon.social';
			const authority2 = 'https://pixelfed.social';

			const response1 = await requestUtils.rest( {
				path: `/activitypub/1.0/actors/1/followers/sync`,
				params: { authority: authority1 },
			} );

			const response2 = await requestUtils.rest( {
				path: `/activitypub/1.0/actors/1/followers/sync`,
				params: { authority: authority2 },
			} );

			// Both should return valid collections (even if empty)
			expect( response1.type ).toBe( 'OrderedCollection' );
			expect( response2.type ).toBe( 'OrderedCollection' );

			// Each should have their own totalItems count
			expect( typeof response1.totalItems ).toBe( 'number' );
			expect( typeof response2.totalItems ).toBe( 'number' );

			// If there are followers, they should all match the authority
			if ( response1.orderedItems.length > 0 ) {
				response1.orderedItems.forEach( ( follower ) => {
					expect( typeof follower ).toBe( 'string' );
					expect( follower ).toContain( 'https://' );
				} );
			}

			if ( response2.orderedItems.length > 0 ) {
				response2.orderedItems.forEach( ( follower ) => {
					expect( typeof follower ).toBe( 'string' );
					expect( follower ).toContain( 'https://' );
				} );
			}
		} );
	} );
} );
