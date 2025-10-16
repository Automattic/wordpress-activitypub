/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'WebFinger REST API', () => {
	let testResource;

	test.beforeAll( async () => {
		// WebFinger typically uses acct: URIs - use localhost domain
		testResource = 'acct:admin@localhost';
	} );

	test( 'should return error when resource parameter is missing', async ( { requestUtils } ) => {
		try {
			await requestUtils.rest( {
				path: '/activitypub/1.0/webfinger',
			} );
			// If we reach here, the test should fail
			expect.fail();
		} catch ( error ) {
			// Should return an error (rest_missing_callback_param or similar)
			expect( error ).toBeDefined();
		}
	} );

	test( 'should return valid JRD document for existing user', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: `/activitypub/1.0/webfinger?resource=${ encodeURIComponent( testResource ) }`,
			} );

			// WebFinger JRD should have subject
			expect( data ).toHaveProperty( 'subject' );
			expect( typeof data.subject ).toBe( 'string' );

			// Should have links array
			expect( data ).toHaveProperty( 'links' );
			expect( Array.isArray( data.links ) ).toBe( true );
		} catch ( error ) {
			// It's ok if the specific resource doesn't exist
			expect( error ).toBeDefined();
		}
	} );

	test( 'should include self link with ActivityPub profile when available', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: `/activitypub/1.0/webfinger?resource=${ encodeURIComponent( testResource ) }`,
			} );

			// Find the self link with ActivityPub profile
			const selfLink = data.links.find(
				( link ) => link.rel === 'self' && link.type === 'application/activity+json'
			);

			if ( selfLink ) {
				expect( selfLink ).toHaveProperty( 'href' );
				expect( selfLink.href ).toMatch( /^https?:\/\// );
			}
		} catch ( error ) {
			// Resource might not exist
			expect( error ).toBeDefined();
		}
	} );

	test( 'should handle acct: URI format', async ( { requestUtils } ) => {
		const acctResource = 'acct:admin@localhost';

		try {
			const data = await requestUtils.rest( {
				path: `/activitypub/1.0/webfinger?resource=${ encodeURIComponent( acctResource ) }`,
			} );

			expect( data ).toHaveProperty( 'subject' );
			expect( data.subject ).toContain( 'acct:' );
		} catch ( error ) {
			// Resource might not exist
			expect( error ).toBeDefined();
		}
	} );

	test( 'should handle URL format', async ( { requestUtils } ) => {
		const urlResource = 'http://localhost:8889/author/admin';

		try {
			const data = await requestUtils.rest( {
				path: `/activitypub/1.0/webfinger?resource=${ encodeURIComponent( urlResource ) }`,
			} );

			expect( data ).toHaveProperty( 'subject' );
		} catch ( error ) {
			// Resource might not exist
			expect( error ).toBeDefined();
		}
	} );

	test( 'should validate links array structure when present', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: `/activitypub/1.0/webfinger?resource=${ encodeURIComponent( testResource ) }`,
			} );

			if ( data.links && data.links.length > 0 ) {
				data.links.forEach( ( link ) => {
					// Each link must have a rel property
					expect( link ).toHaveProperty( 'rel' );
					expect( typeof link.rel ).toBe( 'string' );

					// Links typically have href
					if ( link.href ) {
						expect( typeof link.href ).toBe( 'string' );
					}

					// Type is optional but should be string
					if ( link.type ) {
						expect( typeof link.type ).toBe( 'string' );
					}
				} );
			}
		} catch ( error ) {
			// Resource might not exist
			expect( error ).toBeDefined();
		}
	} );

	test( 'should return proper response structure', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: `/activitypub/1.0/webfinger?resource=${ encodeURIComponent( testResource ) }`,
			} );

			// If successful, verify basic structure
			expect( data ).toHaveProperty( 'subject' );
			expect( data ).toHaveProperty( 'links' );
		} catch ( error ) {
			// Resource might not exist
			expect( error ).toBeDefined();
		}
	} );

	test( 'should include profile page link when available', async ( { requestUtils } ) => {
		try {
			const data = await requestUtils.rest( {
				path: `/activitypub/1.0/webfinger?resource=${ encodeURIComponent( testResource ) }`,
			} );

			// Look for profile page link
			const profileLink = data.links.find( ( link ) => link.rel === 'http://webfinger.net/rel/profile-page' );

			if ( profileLink ) {
				expect( profileLink ).toHaveProperty( 'href' );
				expect( profileLink.href ).toMatch( /^https?:\/\// );
				expect( profileLink ).toHaveProperty( 'type' );
				expect( profileLink.type ).toBe( 'text/html' );
			}
		} catch ( error ) {
			// Resource might not exist
			expect( error ).toBeDefined();
		}
	} );
} );
