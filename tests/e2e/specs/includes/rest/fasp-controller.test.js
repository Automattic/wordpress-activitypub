/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';
import crypto from 'crypto';

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

/**
 * FASP v0.1 Specification Compliance Tests
 *
 * Tests implementation against:
 * https://github.com/mastodon/fediverse_auxiliary_service_provider_specifications/tree/main/general/v0.1
 *
 * This test validates SPEC COMPLIANCE, not just API responses.
 *
 * Authentication Pattern:
 * - All FASP endpoints use the standard ActivityPub signature verification pattern (Server::verify_signature)
 * - Provider info endpoint: Verifies HTTP signatures (GET requests with authorized fetch enabled)
 * - Capability endpoints: Require HTTP signatures (POST/DELETE requests always require signatures)
 * - Registration endpoint: Publicly accessible (no signature required)
 *
 * Note: Uses /?rest_route= URL format for mod_rewrite compatibility
 */
test.describe( 'FASP v0.1 Specification Compliance', () => {
	const faspBasePath = '/activitypub/1.0/fasp';

	// Helper to construct REST API URL that works with and without mod_rewrite
	const restUrl = ( baseURL, path ) => `${ baseURL }/?rest_route=${ path }`;

	test.describe( 'Protocol Basics - Request Integrity (RFC-9530)', () => {
		test( 'provider_info response MUST include Content-Digest header with SHA-256', async ( {
			request,
			baseURL,
		} ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );

			const headers = response.headers();
			expect( headers[ 'content-digest' ] ).toBeDefined();
			expect( headers[ 'content-digest' ] ).toMatch( /^sha-256=:/ );

			const digestMatch = headers[ 'content-digest' ].match( /^sha-256=:([A-Za-z0-9+/=]+):$/ );
			expect( digestMatch ).toBeTruthy();
			expect( digestMatch[ 1 ] ).toBeTruthy();
		} );

		test( 'Content-Digest MUST match actual response body', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );

			const body = await response.text();
			const headers = response.headers();

			const digestMatch = headers[ 'content-digest' ].match( /^sha-256=:([A-Za-z0-9+/=]+):$/ );
			expect( digestMatch ).toBeTruthy();

			const receivedDigest = digestMatch[ 1 ];
			const expectedDigest = crypto.createHash( 'sha256' ).update( body ).digest( 'base64' );

			expect( receivedDigest ).toBe( expectedDigest );
		} );
	} );

	test.describe( 'Protocol Basics - Authentication (RFC-9421)', () => {
		test( 'provider_info response MUST include Signature-Input header', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );

			const headers = response.headers();
			expect( headers[ 'signature-input' ] ).toBeDefined();

			const signatureInput = headers[ 'signature-input' ];
			expect( signatureInput ).toMatch( /^[a-z0-9_-]+=\([^)]+\);/ );
		} );

		test( 'provider_info response MUST include Signature header', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );

			const headers = response.headers();
			expect( headers.signature ).toBeDefined();

			const signature = headers.signature;
			expect( signature ).toMatch( /^[a-z0-9_-]+=:[A-Za-z0-9+/=]+:$/ );
		} );

		test( 'Signature-Input MUST include @status derived component', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );

			const headers = response.headers();
			const signatureInput = headers[ 'signature-input' ];
			expect( signatureInput ).toContain( '"@status"' );
		} );

		test( 'Signature-Input MUST include content-digest component', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );

			const headers = response.headers();
			const signatureInput = headers[ 'signature-input' ];
			expect( signatureInput ).toContain( '"content-digest"' );
		} );

		test( 'Signature-Input MUST include created parameter', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );

			const headers = response.headers();
			const signatureInput = headers[ 'signature-input' ];
			expect( signatureInput ).toMatch( /;created=\d+/ );
		} );

		test( 'Signature-Input MUST include keyid parameter', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );

			const headers = response.headers();
			const signatureInput = headers[ 'signature-input' ];
			expect( signatureInput ).toMatch( /;keyid=/ );
		} );

		test( 'Signature labels MUST match', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );

			const headers = response.headers();
			const signatureInput = headers[ 'signature-input' ];
			const signature = headers.signature;

			const inputLabelMatch = signatureInput.match( /^([a-z0-9_-]+)=/ );
			expect( inputLabelMatch ).toBeTruthy();
			const inputLabel = inputLabelMatch[ 1 ];

			const sigLabelMatch = signature.match( /^([a-z0-9_-]+)=/ );
			expect( sigLabelMatch ).toBeTruthy();
			const sigLabel = sigLabelMatch[ 1 ];

			expect( inputLabel ).toBe( sigLabel );
		} );

		test( 'created timestamp within acceptable range', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );

			const headers = response.headers();
			const signatureInput = headers[ 'signature-input' ];

			const createdMatch = signatureInput.match( /;created=(\d+)/ );
			expect( createdMatch ).toBeTruthy();

			const created = parseInt( createdMatch[ 1 ], 10 );
			const now = Math.floor( Date.now() / 1000 );

			expect( created ).toBeLessThanOrEqual( now + 60 );
			expect( created ).toBeGreaterThan( now - 3600 );
		} );
	} );

	test.describe( 'Provider Info Endpoint', () => {
		test( 'endpoint accessible', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );
			expect( response.status() ).toBe( 200 );
		} );

		test( 'returns valid JSON', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );

			expect( response.headers()[ 'content-type' ] ).toContain( 'application/json' );

			const data = await response.json();
			expect( data ).toBeDefined();
			expect( typeof data ).toBe( 'object' );
		} );

		test( 'contains required field: name', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );
			const data = await response.json();

			expect( data ).toHaveProperty( 'name' );
			expect( typeof data.name ).toBe( 'string' );
			expect( data.name.length ).toBeGreaterThan( 0 );
		} );

		test( 'contains required field: privacyPolicy', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );
			const data = await response.json();

			expect( data ).toHaveProperty( 'privacyPolicy' );
			expect( Array.isArray( data.privacyPolicy ) ).toBe( true );
		} );

		test( 'privacyPolicy items have url and language', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );
			const data = await response.json();

			if ( data.privacyPolicy.length > 0 ) {
				for ( const policy of data.privacyPolicy ) {
					expect( policy ).toHaveProperty( 'url' );
					expect( policy ).toHaveProperty( 'language' );
					expect( typeof policy.url ).toBe( 'string' );
					expect( typeof policy.language ).toBe( 'string' );

					expect( () => new URL( policy.url ) ).not.toThrow();
					expect( policy.language ).toMatch( /^[a-z]{2}(-[A-Z]{2})?$/ );
				}
			}
		} );

		test( 'contains required field: capabilities', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );
			const data = await response.json();

			expect( data ).toHaveProperty( 'capabilities' );
			expect( Array.isArray( data.capabilities ) ).toBe( true );
		} );

		test( 'capabilities items have id and version', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );
			const data = await response.json();

			if ( data.capabilities.length > 0 ) {
				for ( const capability of data.capabilities ) {
					expect( capability ).toHaveProperty( 'id' );
					expect( capability ).toHaveProperty( 'version' );
					expect( typeof capability.id ).toBe( 'string' );
					expect( typeof capability.version ).toBe( 'string' );
				}
			}
		} );

		test( 'signInUrl valid if present', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );
			const data = await response.json();

			if ( data.signInUrl ) {
				expect( typeof data.signInUrl ).toBe( 'string' );
				expect( () => new URL( data.signInUrl ) ).not.toThrow();
			}
		} );

		test( 'contactEmail valid if present', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );
			const data = await response.json();

			if ( data.contactEmail ) {
				expect( typeof data.contactEmail ).toBe( 'string' );
				expect( data.contactEmail ).toMatch( /^[^\s@]+@[^\s@]+\.[^\s@]+$/ );
			}
		} );

		test( 'fediverseAccount valid if present', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );
			const data = await response.json();

			if ( data.fediverseAccount ) {
				expect( typeof data.fediverseAccount ).toBe( 'string' );
				expect( data.fediverseAccount ).toMatch( /^@[a-zA-Z0-9_]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/ );
			}
		} );
	} );

	test.describe( 'Registration Endpoint', () => {
		test( 'endpoint accessible', async ( { request, baseURL } ) => {
			const testPayload = {
				name: 'Test FASP',
				baseUrl: 'https://fasp.example.com',
				serverId: 'test123456',
				publicKey: 'dGVzdHB1YmxpY2tleQ==',
			};

			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: testPayload,
			} );

			expect( response.status() ).not.toBe( 404 );
			expect( [ 201, 400, 401 ] ).toContain( response.status() );
		} );

		test( 'validates required fields', async ( { request, baseURL } ) => {
			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: {},
			} );

			expect( response.status() ).toBe( 400 );
		} );

		test( 'validates name field', async ( { request, baseURL } ) => {
			const testPayload = {
				baseUrl: 'https://fasp.example.com',
				serverId: 'test123456',
				publicKey: 'dGVzdHB1YmxpY2tleQ==',
			};

			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: testPayload,
			} );

			expect( response.status() ).toBe( 400 );
		} );

		test( 'validates baseUrl field', async ( { request, baseURL } ) => {
			const testPayload = {
				name: 'Test FASP',
				serverId: 'test123456',
				publicKey: 'dGVzdHB1YmxpY2tleQ==',
			};

			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: testPayload,
			} );

			expect( response.status() ).toBe( 400 );
		} );

		test( 'validates serverId field', async ( { request, baseURL } ) => {
			const testPayload = {
				name: 'Test FASP',
				baseUrl: 'https://fasp.example.com',
				publicKey: 'dGVzdHB1YmxpY2tleQ==',
			};

			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: testPayload,
			} );

			expect( response.status() ).toBe( 400 );
		} );

		test( 'validates publicKey field', async ( { request, baseURL } ) => {
			const testPayload = {
				name: 'Test FASP',
				baseUrl: 'https://fasp.example.com',
				serverId: 'test123456',
			};

			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: testPayload,
			} );

			expect( response.status() ).toBe( 400 );
		} );

		test( 'successful registration returns 201', async ( { request, baseURL } ) => {
			const testPayload = {
				name: 'E2E Test FASP',
				baseUrl: 'https://fasp.example.com',
				serverId: `test${ Date.now() }`,
				publicKey: generateValidEd25519PublicKey(),
			};

			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: testPayload,
			} );

			expect( response.status() ).toBe( 201 );
		} );

		test( 'response includes faspId', async ( { request, baseURL } ) => {
			const testPayload = {
				name: 'E2E Test FASP',
				baseUrl: 'https://fasp.example.com',
				serverId: `test${ Date.now() }`,
				publicKey: generateValidEd25519PublicKey(),
			};

			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: testPayload,
			} );

			if ( response.status() === 201 ) {
				const data = await response.json();
				expect( data ).toHaveProperty( 'faspId' );
				expect( typeof data.faspId ).toBe( 'string' );
			}
		} );

		test( 'response includes publicKey', async ( { request, baseURL } ) => {
			const testPayload = {
				name: 'E2E Test FASP',
				baseUrl: 'https://fasp.example.com',
				serverId: `test${ Date.now() }`,
				publicKey: generateValidEd25519PublicKey(),
			};

			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: testPayload,
			} );

			if ( response.status() === 201 ) {
				const data = await response.json();
				expect( data ).toHaveProperty( 'publicKey' );
				expect( typeof data.publicKey ).toBe( 'string' );
				expect( () => Buffer.from( data.publicKey, 'base64' ) ).not.toThrow();
			}
		} );

		test( 'response includes registrationCompletionUri', async ( { request, baseURL } ) => {
			const testPayload = {
				name: 'E2E Test FASP',
				baseUrl: 'https://fasp.example.com',
				serverId: `test${ Date.now() }`,
				publicKey: generateValidEd25519PublicKey(),
			};

			const response = await request.post( restUrl( baseURL, `${ faspBasePath }/registration` ), {
				data: testPayload,
			} );

			if ( response.status() === 201 ) {
				const data = await response.json();
				expect( data ).toHaveProperty( 'registrationCompletionUri' );
				expect( typeof data.registrationCompletionUri ).toBe( 'string' );
				expect( () => new URL( data.registrationCompletionUri ) ).not.toThrow();
			}
		} );
	} );

	test.describe( 'Capability Activation Endpoints', () => {
		/**
		 * Note: Capability endpoints require HTTP Message Signatures (RFC-9421) for authentication.
		 * These tests verify endpoint routing and error handling for unauthenticated requests.
		 * TODO: Add tests with properly signed requests to verify full capability activation flow.
		 */

		test( 'endpoint accessible (rejects unauthenticated requests)', async ( { request, baseURL } ) => {
			const response = await request.post(
				restUrl( baseURL, `${ faspBasePath }/capabilities/test/1/activation` )
			);
			// Endpoint exists (not 404) but requires signature authentication
			expect( response.status() ).not.toBe( 404 );
			expect( response.status() ).toBe( 401 ); // Unauthenticated
		} );

		test( 'POST requires HTTP signature authentication', async ( { request, baseURL } ) => {
			const response = await request.post(
				restUrl( baseURL, `${ faspBasePath }/capabilities/test_capability/1/activation` )
			);
			// Without valid HTTP signature, request is rejected
			expect( response.status() ).toBe( 401 );
		} );

		test( 'DELETE requires HTTP signature authentication', async ( { request, baseURL } ) => {
			const response = await request.delete(
				restUrl( baseURL, `${ faspBasePath }/capabilities/test_capability/1/activation` )
			);
			// Without valid HTTP signature, request is rejected
			expect( response.status() ).toBe( 401 );
		} );

		test( 'rejects requests with missing signature headers', async ( { request, baseURL } ) => {
			const response = await request.post(
				restUrl( baseURL, `${ faspBasePath }/capabilities/test/1/activation` ),
				{
					headers: {
						'Content-Type': 'application/json',
					},
				}
			);
			expect( response.status() ).toBe( 401 );
		} );
	} );

	test.describe( 'HTTP Headers Compliance', () => {
		test( 'endpoint responds successfully', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );
			expect( response.status() ).toBeLessThan( 500 );
		} );

		test( 'has correct Content-Type', async ( { request, baseURL } ) => {
			const response = await request.get( restUrl( baseURL, `${ faspBasePath }/provider_info` ) );
			expect( response.headers()[ 'content-type' ] ).toContain( 'application/json' );
		} );
	} );
} );
