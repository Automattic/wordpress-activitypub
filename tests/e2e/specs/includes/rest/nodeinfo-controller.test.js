/**
 * WordPress dependencies
 */
import { test, expect } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'NodeInfo REST API', () => {
	test( 'should return 200 status code for nodeinfo discovery', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: '/activitypub/1.0/nodeinfo',
		} );

		expect( data ).toBeDefined();
	} );

	test( 'should return valid nodeinfo discovery document', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: '/activitypub/1.0/nodeinfo',
		} );

		// Should have links array
		expect( data ).toHaveProperty( 'links' );
		expect( Array.isArray( data.links ) ).toBe( true );
		expect( data.links.length ).toBeGreaterThan( 0 );
	} );

	test( 'should include nodeinfo 2.0 or 2.1 schema links', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: '/activitypub/1.0/nodeinfo',
		} );

		// Find NodeInfo schema links
		const nodeinfoLink = data.links.find(
			( link ) =>
				link.rel === 'http://nodeinfo.diaspora.software/ns/schema/2.0' ||
				link.rel === 'http://nodeinfo.diaspora.software/ns/schema/2.1'
		);

		expect( nodeinfoLink ).toBeDefined();
		expect( nodeinfoLink ).toHaveProperty( 'href' );
		expect( nodeinfoLink.href ).toMatch( /^https?:\/\// );
	} );

	test( 'should validate nodeinfo discovery links structure', async ( { requestUtils } ) => {
		const data = await requestUtils.rest( {
			path: '/activitypub/1.0/nodeinfo',
		} );

		data.links.forEach( ( link ) => {
			expect( link ).toHaveProperty( 'rel' );
			expect( typeof link.rel ).toBe( 'string' );

			expect( link ).toHaveProperty( 'href' );
			expect( typeof link.href ).toBe( 'string' );
			expect( link.href ).toMatch( /^https?:\/\// );
		} );
	} );

	test( 'should fetch nodeinfo 2.0 document', async ( { requestUtils } ) => {
		const nodeinfo = await requestUtils.rest( {
			path: '/activitypub/1.0/nodeinfo/2.0',
		} );

		// Validate NodeInfo structure
		expect( nodeinfo ).toHaveProperty( 'version' );
		expect( nodeinfo.version ).toBe( '2.0' );

		expect( nodeinfo ).toHaveProperty( 'software' );
		expect( nodeinfo.software ).toHaveProperty( 'name' );
		expect( nodeinfo.software ).toHaveProperty( 'version' );

		expect( nodeinfo ).toHaveProperty( 'protocols' );
		expect( Array.isArray( nodeinfo.protocols ) ).toBe( true );
		expect( nodeinfo.protocols ).toContain( 'activitypub' );

		expect( nodeinfo ).toHaveProperty( 'usage' );
		expect( nodeinfo.usage ).toHaveProperty( 'users' );
	} );

	test( 'should include server metadata in nodeinfo', async ( { requestUtils } ) => {
		const nodeinfo = await requestUtils.rest( {
			path: '/activitypub/1.0/nodeinfo/2.0',
		} );

		// Check for metadata
		if ( nodeinfo.metadata ) {
			expect( typeof nodeinfo.metadata ).toBe( 'object' );
		}

		// Check for openRegistrations
		expect( nodeinfo ).toHaveProperty( 'openRegistrations' );
		expect( typeof nodeinfo.openRegistrations ).toBe( 'boolean' );
	} );

	test( 'should include usage statistics', async ( { requestUtils } ) => {
		const nodeinfo = await requestUtils.rest( {
			path: '/activitypub/1.0/nodeinfo/2.0',
		} );

		expect( nodeinfo.usage ).toHaveProperty( 'users' );
		expect( nodeinfo.usage.users ).toHaveProperty( 'total' );
		expect( typeof nodeinfo.usage.users.total ).toBe( 'number' );

		if ( nodeinfo.usage.localPosts !== undefined ) {
			expect( typeof nodeinfo.usage.localPosts ).toBe( 'number' );
		}

		if ( nodeinfo.usage.localComments !== undefined ) {
			expect( typeof nodeinfo.usage.localComments ).toBe( 'number' );
		}
	} );

	test( 'should list supported services', async ( { requestUtils } ) => {
		const nodeinfo = await requestUtils.rest( {
			path: '/activitypub/1.0/nodeinfo/2.0',
		} );

		if ( nodeinfo.services ) {
			expect( nodeinfo.services ).toHaveProperty( 'inbound' );
			expect( Array.isArray( nodeinfo.services.inbound ) ).toBe( true );

			expect( nodeinfo.services ).toHaveProperty( 'outbound' );
			expect( Array.isArray( nodeinfo.services.outbound ) ).toBe( true );
		}
	} );
} );
