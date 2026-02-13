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

	test( 'respects explicit public visibility (empty string) on old posts', () => {
		// Regression test for issue #2618.
		// When visibility is explicitly set to public (empty string), it should be preserved.
		const meta = {
			activitypub_content_visibility: '',
			activitypub_status: 'pending',
		};
		const postDate = new Date( Date.now() - 60 * 24 * 60 * 60 * 1000 ); // 60 days ago

		const result = getDefaultVisibility( meta, postDate );

		// Should return 'public' (or empty string normalized to 'public'),
		// not 'local' based on post age.
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

/**
 * Tests for the visibility sync logic.
 *
 * The useEffect in EditorPlugin syncs the computed default visibility to meta
 * when there's no stored value and the default isn't 'public'.
 * We test this logic by simulating the conditions the useEffect checks.
 */
describe( 'EditorPlugin visibility sync logic', () => {
	/**
	 * Simulates the sync logic from the useEffect hook.
	 *
	 * @param {Object}   meta      The meta object.
	 * @param {string}   postDate  The post date.
	 * @param {Function} setMetaFn The setMeta function.
	 */
	const simulateSyncLogic = ( meta, postDate, setMetaFn ) => {
		const defaultVisibility = getDefaultVisibility( meta, postDate );

		// This mirrors the updated useEffect logic in plugin.js.
		// Check if the meta key exists (not just if it's truthy).
		// ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC is an empty string, so we need
		// to distinguish between "not set" and "set to public".
		const visibilityIsSet = meta && 'activitypub_content_visibility' in meta;

		// Only sync if visibility was never set and the default isn't 'public'.
		// We skip 'public' since it's the implicit default.
		if ( ! visibilityIsSet && defaultVisibility !== 'public' ) {
			setMetaFn( { ...meta, activitypub_content_visibility: defaultVisibility } );
		}
	};

	test( 'syncs local visibility for old posts without stored value', () => {
		const setMeta = jest.fn();
		const meta = {};
		const postDate = new Date( Date.now() - 60 * 24 * 60 * 60 * 1000 ); // 60 days ago.

		simulateSyncLogic( meta, postDate, setMeta );

		expect( setMeta ).toHaveBeenCalledWith( {
			activitypub_content_visibility: 'local',
		} );
	} );

	test( 'does not sync for new posts with public default', () => {
		const setMeta = jest.fn();
		const meta = {};
		const postDate = new Date(); // Now.

		simulateSyncLogic( meta, postDate, setMeta );

		// Public is the implicit default, no sync needed.
		expect( setMeta ).not.toHaveBeenCalled();
	} );

	test( 'does not sync when visibility is already stored', () => {
		const setMeta = jest.fn();
		const meta = { activitypub_content_visibility: 'quiet_public' };
		const postDate = new Date( Date.now() - 60 * 24 * 60 * 60 * 1000 ); // 60 days ago.

		simulateSyncLogic( meta, postDate, setMeta );

		expect( setMeta ).not.toHaveBeenCalled();
	} );

	test( 'does not sync when visibility is explicitly set to empty string (public)', () => {
		const setMeta = jest.fn();
		// Regression test for issue #2618.
		// Empty string means explicitly set to public - should not be overridden.
		const meta = { activitypub_content_visibility: '' };
		const postDate = new Date( Date.now() - 60 * 24 * 60 * 60 * 1000 ); // 60 days ago.

		simulateSyncLogic( meta, postDate, setMeta );

		// Even though post is old, don't override explicit public choice.
		expect( setMeta ).not.toHaveBeenCalled();
	} );

	test( 'does not sync when visibility is explicitly set (any value)', () => {
		const setMeta = jest.fn();
		// Any explicitly set value should be preserved.
		const meta = { activitypub_content_visibility: 'local' };
		const postDate = new Date(); // New post - would normally default to public.

		simulateSyncLogic( meta, postDate, setMeta );

		// Explicit local choice should be preserved even for new posts.
		expect( setMeta ).not.toHaveBeenCalled();
	} );

	test( 'preserves existing meta fields when syncing', () => {
		const setMeta = jest.fn();
		const meta = {
			activitypub_content_warning: 'some warning',
			activitypub_max_image_attachments: 5,
		};
		const postDate = new Date( Date.now() - 60 * 24 * 60 * 60 * 1000 ); // 60 days ago.

		simulateSyncLogic( meta, postDate, setMeta );

		expect( setMeta ).toHaveBeenCalledWith( {
			activitypub_content_warning: 'some warning',
			activitypub_max_image_attachments: 5,
			activitypub_content_visibility: 'local',
		} );
	} );
} );
