/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'OAuth Controller CORS Headers', () => {
	const restBase = 'http://localhost:8889/index.php?rest_route=';

	test( 'should include CORS headers on outbox endpoint', async ( { request } ) => {
		const response = await request.get( `${ restBase }/activitypub/1.0/actors/1/outbox` );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'access-control-allow-origin' ] ).toBe( '*' );
		expect( response.headers()[ 'access-control-allow-methods' ] ).toContain( 'GET' );
		expect( response.headers()[ 'access-control-allow-headers' ] ).toContain( 'Authorization' );
	} );

	test( 'should include CORS headers on inbox endpoint', async ( { request } ) => {
		const response = await request.get( `${ restBase }/activitypub/1.0/actors/1/inbox` );

		expect( response.headers()[ 'access-control-allow-origin' ] ).toBe( '*' );
	} );

	test( 'should include CORS headers on webfinger endpoint', async ( { request } ) => {
		const resource = encodeURIComponent( 'http://localhost:8889/?author=1' );
		const response = await request.get( `${ restBase }/activitypub/1.0/webfinger&resource=${ resource }` );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'access-control-allow-origin' ] ).toBe( '*' );
	} );

	test( 'should include CORS headers on OAuth token endpoint', async ( { request } ) => {
		const response = await request.post( `${ restBase }/activitypub/1.0/oauth/token`, {
			form: {
				grant_type: 'authorization_code',
				code: 'invalid',
				client_id: 'invalid',
				redirect_uri: 'http://localhost',
			},
		} );

		/*
		 * The request will fail (invalid code), but CORS headers
		 * should still be present on error responses.
		 */
		expect( response.headers()[ 'access-control-allow-origin' ] ).toBe( '*' );
	} );

	test( 'should NOT include CORS headers on actors endpoint', async ( { request } ) => {
		const response = await request.get( `${ restBase }/activitypub/1.0/actors/1` );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'access-control-allow-origin' ] ).toBeUndefined();
	} );

	test( 'should NOT include CORS headers on followers endpoint', async ( { request } ) => {
		const response = await request.get( `${ restBase }/activitypub/1.0/actors/1/followers` );

		expect( response.status() ).toBe( 200 );
		expect( response.headers()[ 'access-control-allow-origin' ] ).toBeUndefined();
	} );
} );
