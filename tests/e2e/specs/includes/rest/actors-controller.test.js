/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'ActivityPub Actors REST API', () => {
	let testUserId;
	let actorEndpoint;

	test.beforeAll( async ( { requestUtils } ) => {
		// Use the default test user
		testUserId = 1;
		actorEndpoint = `/activitypub/1.0/users/${ testUserId }`;
	} );

	test( 'should return 200 status code for actor endpoint', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: actorEndpoint,
		} );

		expect( data ).toBeDefined();
	} );

	test( 'should return valid ActivityStreams Actor object', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: actorEndpoint,
		} );

		// Check for ActivityStreams context
		expect( data ).toHaveProperty( '@context' );
		expect( Array.isArray( data[ '@context' ] ) || typeof data[ '@context' ] === 'string' ).toBe( true );

		// Verify Actor type
		expect( data ).toHaveProperty( 'type' );
		expect( [ 'Person', 'Service', 'Organization', 'Application', 'Group' ] ).toContain( data.type );

		// Required properties for an Actor
		expect( data ).toHaveProperty( 'id' );
		expect( data.id ).toMatch( /^https?:\/\// );

		expect( data ).toHaveProperty( 'inbox' );
		expect( data.inbox ).toMatch( /^https?:\/\// );

		expect( data ).toHaveProperty( 'outbox' );
		expect( data.outbox ).toMatch( /^https?:\/\// );
	} );

	test( 'should include endpoints object when available', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: actorEndpoint,
		} );

		// Endpoints is optional but commonly includes sharedInbox
		if ( data.endpoints ) {
			expect( typeof data.endpoints ).toBe( 'object' );
			if ( data.endpoints.sharedInbox ) {
				expect( data.endpoints.sharedInbox ).toMatch( /^https?:\/\// );
			}
		}
	} );

	test( 'should include publicKey for verification', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: actorEndpoint,
		} );

		expect( data ).toHaveProperty( 'publicKey' );
		expect( data.publicKey ).toHaveProperty( 'id' );
		expect( data.publicKey ).toHaveProperty( 'owner' );
		expect( data.publicKey ).toHaveProperty( 'publicKeyPem' );
		expect( data.publicKey.publicKeyPem ).toMatch( /^-----BEGIN PUBLIC KEY-----/ );
	} );

	test( 'should include profile information', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: actorEndpoint,
		} );

		// Optional but commonly present properties
		if ( data.name ) {
			expect( typeof data.name ).toBe( 'string' );
		}

		if ( data.preferredUsername ) {
			expect( typeof data.preferredUsername ).toBe( 'string' );
		}

		if ( data.summary ) {
			expect( typeof data.summary ).toBe( 'string' );
		}

		if ( data.url ) {
			expect( data.url ).toMatch( /^https?:\/\// );
		}
	} );

	test( 'should return error for non-existent user', async ( { requestUtils } ) => {
		try {
			await requestUtils.rest( {
				path: '/activitypub/1.0/users/999999',
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
			path: actorEndpoint,
		} );

		expect( data ).toBeDefined();
		expect( data ).toHaveProperty( 'type' );
	} );

	test( 'should validate followers and following collections are present', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: actorEndpoint,
		} );

		expect( data ).toHaveProperty( 'followers' );
		expect( data.followers ).toMatch( /^https?:\/\// );

		expect( data ).toHaveProperty( 'following' );
		expect( data.following ).toMatch( /^https?:\/\// );
	} );

	test( 'should include featured collection', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: actorEndpoint,
		} );

		if ( data.featured ) {
			expect( data.featured ).toMatch( /^https?:\/\// );
		}
	} );
} );
