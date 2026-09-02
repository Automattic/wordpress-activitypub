/**
 * @jest-environment jsdom
 */

let registered;

jest.mock( '@wordpress/interactivity', () => ( {
	store: ( _name, definition ) => {
		registered = definition;
		return definition;
	},
	getContext: () => global.__context,
	getConfig: () => ( { namespace: 'activitypub/v1' } ),
} ) );

jest.mock( '../../with-sync-event', () => ( {
	withSyncEvent: ( fn ) => fn,
} ) );

import { createActorListStore } from '../store';

/**
 * Drive fetchItems with a canned REST payload and return the mapped items.
 *
 * @param {Array} orderedItems Items the endpoint returns.
 * @return {Promise<Array>} The items written to the context.
 */
async function fetchWith( orderedItems ) {
	global.__context = {
		userId: 1,
		page: 1,
		perPage: 10,
		order: 'desc',
		endpoint: 'followers',
		items: [],
	};

	window.wp = {
		apiFetch: jest.fn().mockResolvedValue( { orderedItems, totalItems: orderedItems.length } ),
		url: { addQueryArgs: ( path ) => path },
	};

	createActorListStore( 'activitypub/followers' );
	await registered.actions.fetchItems();

	return global.__context.items;
}

describe( 'actor list store', () => {
	test( 'keeps an ordinary http(s) actor URL', async () => {
		const items = await fetchWith( [
			{ webfinger: 'alice@remote.example', name: 'Alice', url: 'https://remote.example/@alice' },
		] );

		expect( items[ 0 ].url ).toBe( 'https://remote.example/@alice' );
	} );

	test.each( [
		[ 'javascript scheme', 'javascript:alert(document.domain)//' ],
		[ 'mixed-case javascript scheme', 'JaVaScRiPt:alert(1)' ],
		[ 'data scheme', 'data:text/html;base64,PHNjcmlwdD48L3NjcmlwdD4=' ],
	] )( 'drops a %s supplied by a remote server', async ( _label, url ) => {
		const items = await fetchWith( [ { webfinger: 'mallory@evil.example', name: 'Mallory', url } ] );

		expect( items[ 0 ].url ).toBe( '' );
	} );

	test( 'falls back to the actor id, and gates that too', async () => {
		const items = await fetchWith( [
			{ webfinger: 'bob@remote.example', name: 'Bob', id: 'https://remote.example/users/bob' },
			{ webfinger: 'eve@evil.example', name: 'Eve', id: 'javascript:alert(1)' },
		] );

		expect( items[ 0 ].url ).toBe( 'https://remote.example/users/bob' );
		expect( items[ 1 ].url ).toBe( '' );
	} );
} );
