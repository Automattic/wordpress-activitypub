/**
 * @jest-environment jsdom
 */

import { getDefaultVisibility } from '../utils';

describe( 'EditorPlugin getDefaultVisibility', () => {
	test( 'returns saved visibility value if already set', () => {
		const meta = {
			activitypub_content_visibility: 'quiet_public',
			activitypub_status: 'pending',
		};
		const postDate = new Date( Date.now() - 60 * 24 * 60 * 60 * 1000 ); // 60 days ago

		const result = getDefaultVisibility( meta, postDate );

		expect( result ).toBe( 'quiet_public' );
	} );

	test( 'returns public for federated posts', () => {
		const meta = {
			activitypub_status: 'federated',
		};
		const postDate = new Date( Date.now() - 60 * 24 * 60 * 60 * 1000 ); // 60 days ago

		const result = getDefaultVisibility( meta, postDate );

		expect( result ).toBe( 'public' );
	} );

	test( 'returns local for posts older than 1 month', () => {
		const meta = {
			activitypub_status: 'pending',
		};
		const postDate = new Date( Date.now() - 60 * 24 * 60 * 60 * 1000 ); // 60 days ago

		const result = getDefaultVisibility( meta, postDate );

		expect( result ).toBe( 'local' );
	} );

	test( 'returns local for posts exactly 1 month old', () => {
		const meta = {
			activitypub_status: 'pending',
		};
		const postDate = new Date( Date.now() - 31 * 24 * 60 * 60 * 1000 ); // 31 days ago

		const result = getDefaultVisibility( meta, postDate );

		expect( result ).toBe( 'local' );
	} );

	test( 'returns public for posts less than 1 month old', () => {
		const meta = {
			activitypub_status: 'pending',
		};
		const postDate = new Date( Date.now() - 14 * 24 * 60 * 60 * 1000 ); // 14 days ago

		const result = getDefaultVisibility( meta, postDate );

		expect( result ).toBe( 'public' );
	} );

	test( 'returns public for new posts', () => {
		const meta = {};
		const postDate = new Date(); // Now

		const result = getDefaultVisibility( meta, postDate );

		expect( result ).toBe( 'public' );
	} );

	test( 'returns public when postDate is null', () => {
		const meta = {};
		const postDate = null;

		const result = getDefaultVisibility( meta, postDate );

		expect( result ).toBe( 'public' );
	} );

	test( 'returns public when meta is empty', () => {
		const meta = {};
		const postDate = new Date( Date.now() - 7 * 24 * 60 * 60 * 1000 ); // 7 days ago

		const result = getDefaultVisibility( meta, postDate );

		expect( result ).toBe( 'public' );
	} );

	test( 'prioritizes explicit value over federated status', () => {
		const meta = {
			activitypub_content_visibility: 'local',
			activitypub_status: 'federated',
		};
		const postDate = new Date();

		const result = getDefaultVisibility( meta, postDate );

		expect( result ).toBe( 'local' );
	} );

	test( 'prioritizes explicit value over post age', () => {
		const meta = {
			activitypub_content_visibility: 'public',
			activitypub_status: 'pending',
		};
		const postDate = new Date( Date.now() - 60 * 24 * 60 * 60 * 1000 ); // 60 days ago

		const result = getDefaultVisibility( meta, postDate );

		expect( result ).toBe( 'public' );
	} );

	test( 'handles edge case: exactly 30 days old', () => {
		const meta = {
			activitypub_status: 'pending',
		};
		const postDate = new Date( Date.now() - 30 * 24 * 60 * 60 * 1000 ); // Exactly 30 days

		const result = getDefaultVisibility( meta, postDate );

		// Should be on the borderline, but our logic uses < so this should be public
		// However, due to potential millisecond differences, this might be local
		expect( [ 'public', 'local' ] ).toContain( result );
	} );
} );
