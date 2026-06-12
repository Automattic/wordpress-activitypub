/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import crypto from 'crypto';
import { execSync } from 'child_process';

/**
 * Generate a valid Ed25519 public key for testing.
 * Ed25519 public keys must be exactly 32 bytes.
 *
 * @return {string} Base64-encoded 32-byte public key.
 */
const generateValidEd25519PublicKey = () => {
	// Generate a real Ed25519 keypair and extract the raw public key.
	const { publicKey } = crypto.generateKeyPairSync( 'ed25519' );
	const rawPublicKey = publicKey.export( { type: 'spki', format: 'der' } );
	// SPKI format for Ed25519: 12 bytes header + 32 bytes key.
	const ed25519PublicKeyBytes = rawPublicKey.subarray( 12 );
	return ed25519PublicKeyBytes.toString( 'base64' );
};

// Enable FASP feature before running tests.
test.beforeAll( async () => {
	execSync( "npx wp-env run tests-cli wp option update activitypub_enable_fasp '1'" );
} );

// Disable FASP feature after tests complete.
test.afterAll( async () => {
	execSync( 'npx wp-env run tests-cli wp option delete activitypub_enable_fasp' );
} );

/**
 * FASP v0.1 Specification Compliance Tests
 *
 * Tests implementation against:
 * https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/tree/main/general/v0.1
 *
 * The fediverse server side of FASP consists of the `/registration` endpoint
 * (providers register with this site), the `faspBaseUrl` nodeinfo metadata,
 * and signed responses. Provider info and capability activation are endpoints
 * on the FASP that this site calls, so they have no inbound routes here.
 *
 * Note: Uses /?rest_route= URL format for mod_rewrite compatibility
 */
test.describe( 'FASP v0.1 Specification Compliance', () => {
	const faspBasePath = '/activitypub/1.0/fasp';

	// Helper to construct REST API URL that works with and without mod_rewrite
	const restUrl = ( baseURL, path ) => `${ baseURL }/?rest_route=${ path }`;

	// Helper for a valid registration payload with a unique serverId.
	const registrationPayload = () => ( {
		name: 'E2E Test FASP',
		baseUrl: 'https://fasp.example.com',
		serverId: `test${ Date.now() }${ Math.floor( Math.random() * 1000 ) }`,
		publicKey: generateValidEd25519PublicKey(),
	} );

	test.describe( 'Discovery - nodeinfo metadata', () => {
		test( 'nodeinfo MUST include faspBaseUrl when FASP is enabled', async ( { request, baseURL } ) => {
			const wellKnown = await request.get( restUrl( baseURL, '/activitypub/1.0/nodeinfo/2.0' ) );
			expect( wellKnown.status() ).toBe( 200 );

			const data = await wellKnown.json();
			expect( data.metadata ).toHaveProperty( 'faspBaseUrl' );
			expect( data.metadata.faspBaseUrl ).toContain( 'fasp' );
		} );
	} );

	test.describe( 'Registration Endpoint - Response Signing (RFC-9421/RFC-9530)', () => {
		test( 'registration response MUST include Content-Digest, Signature-Input and Signature headers', async ( {
			request,
			baseURL,
		} ) => {
			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: registrationPayload(),
			} );

			expect( response.status() ).toBe( 201 );

			const headers = response.headers();
			expect( headers[ 'content-digest' ] ).toBeDefined();
			expect( headers[ 'content-digest' ] ).toMatch( /^sha-256=:/ );
			expect( headers[ 'signature-input' ] ).toBeDefined();
			expect( headers.signature ).toBeDefined();
			expect( headers.signature ).toMatch( /^[a-z0-9_-]+=:[A-Za-z0-9+/=]+:$/ );
		} );

		test( 'Content-Digest MUST match actual response body', async ( { request, baseURL } ) => {
			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: registrationPayload(),
			} );

			const body = await response.text();
			const headers = response.headers();

			const digestMatch = headers[ 'content-digest' ].match( /^sha-256=:([A-Za-z0-9+/=]+):$/ );
			expect( digestMatch ).toBeTruthy();

			const receivedDigest = digestMatch[ 1 ];
			const expectedDigest = crypto.createHash( 'sha256' ).update( body ).digest( 'base64' );

			expect( receivedDigest ).toBe( expectedDigest );
		} );

		test( 'Signature-Input MUST cover @status and content-digest with created and keyid', async ( {
			request,
			baseURL,
		} ) => {
			const payload = registrationPayload();
			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: payload,
			} );

			const signatureInput = response.headers()[ 'signature-input' ];
			expect( signatureInput ).toContain( '"@status"' );
			expect( signatureInput ).toContain( '"content-digest"' );
			expect( signatureInput ).toMatch( /;created=\d+/ );
			// The keyid is the serverId this site received from the provider.
			expect( signatureInput ).toContain( `;keyid="${ payload.serverId }"` );
		} );

		test( 'signature MUST verify against the returned publicKey', async ( { request, baseURL } ) => {
			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: registrationPayload(),
			} );

			const body = await response.text();
			const headers = response.headers();
			const data = JSON.parse( body );

			const inputMatch = headers[ 'signature-input' ].match( /^([a-z0-9_-]+)=\(([^)]+)\)(.*)$/ );
			expect( inputMatch ).toBeTruthy();
			const [ , label, , params ] = inputMatch;

			const sigMatch = headers.signature.match( new RegExp( `${ label }=:([A-Za-z0-9+/=]+):` ) );
			expect( sigMatch ).toBeTruthy();
			const signature = Buffer.from( sigMatch[ 1 ], 'base64' );

			const signatureBase = [
				`"@status": ${ response.status() }`,
				`"content-digest": ${ headers[ 'content-digest' ] }`,
				`"@signature-params": ("@status" "content-digest")${ params }`,
			].join( '\n' );

			// Wrap the raw Ed25519 key into SPKI DER for Node's crypto.verify().
			const rawKey = Buffer.from( data.publicKey, 'base64' );
			const spkiHeader = Buffer.from( '302a300506032b6570032100', 'hex' );
			const publicKey = crypto.createPublicKey( {
				key: Buffer.concat( [ spkiHeader, rawKey ] ),
				format: 'der',
				type: 'spki',
			} );

			expect( crypto.verify( null, Buffer.from( signatureBase ), publicKey, signature ) ).toBe( true );
		} );
	} );

	test.describe( 'Registration Endpoint - Validation', () => {
		test( 'validates required fields', async ( { request, baseURL } ) => {
			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: {},
			} );

			expect( response.status() ).toBe( 400 );
		} );

		for ( const field of [ 'name', 'baseUrl', 'serverId', 'publicKey' ] ) {
			test( `validates ${ field } field`, async ( { request, baseURL } ) => {
				const payload = registrationPayload();
				delete payload[ field ];

				const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
					data: payload,
				} );

				expect( response.status() ).toBe( 400 );
			} );
		}

		test( 'rejects plain-http base URLs', async ( { request, baseURL } ) => {
			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: { ...registrationPayload(), baseUrl: 'http://fasp.example.com' },
			} );

			expect( response.status() ).toBe( 400 );
		} );

		test( 'rejects invalid public keys', async ( { request, baseURL } ) => {
			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: { ...registrationPayload(), publicKey: 'dGVzdHB1YmxpY2tleQ==' },
			} );

			expect( response.status() ).toBe( 400 );
		} );

		test( 'rejects duplicate serverIds with 409', async ( { request, baseURL } ) => {
			const payload = registrationPayload();

			const first = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: payload,
			} );
			expect( first.status() ).toBe( 201 );

			const second = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: { ...payload, publicKey: generateValidEd25519PublicKey() },
			} );
			expect( second.status() ).toBe( 409 );
		} );
	} );

	test.describe( 'Registration Endpoint - Response Body', () => {
		test( 'successful registration returns faspId, publicKey and registrationCompletionUri', async ( {
			request,
			baseURL,
		} ) => {
			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: registrationPayload(),
			} );

			expect( response.status() ).toBe( 201 );

			const data = await response.json();
			expect( data ).toHaveProperty( 'faspId' );
			expect( typeof data.faspId ).toBe( 'string' );

			expect( data ).toHaveProperty( 'publicKey' );
			// The returned key is a raw Ed25519 key: 32 bytes.
			expect( Buffer.from( data.publicKey, 'base64' ).length ).toBe( 32 );

			expect( data ).toHaveProperty( 'registrationCompletionUri' );
			expect( () => new URL( data.registrationCompletionUri ) ).not.toThrow();
		} );
	} );

	test.describe( 'Removed inbound endpoints', () => {
		test( 'provider_info is not served by this site (it lives on the FASP)', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );
			expect( response.status() ).toBe( 404 );
		} );

		test( 'capability activation is not served by this site (it lives on the FASP)', async ( {
			request,
			baseURL,
		} ) => {
			const response = await request.post(
				restUrl( baseURL, `${ faspBasePath }/capabilities/test/1/activation` )
			);
			expect( response.status() ).toBe( 404 );
		} );
	} );
} );
