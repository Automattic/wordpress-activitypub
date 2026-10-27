/**
 * Tests for the feed route module.
 */

/**
 * WordPress dependencies
 */
import { dispatch, resolveSelect, select } from '@wordpress/data';
import { loadView } from '@wordpress/views';
import { redirect, notFound } from '@wordpress/route';

/**
 * Internal dependencies
 */
import { route } from '../route';
import { DEFAULT_VIEW, defaultLayouts, viewToQuery } from '../utils';

// The app store registers itself when the route module is imported, so the data package needs the
// pieces that registration touches; the loader itself only uses `resolveSelect`.
jest.mock( '@wordpress/data', () => ( {
	resolveSelect: jest.fn(),
	createReduxStore: jest.fn( () => ( {} ) ),
	register: jest.fn(),
	createRegistrySelector: jest.fn( ( selector ) => selector ),
	dispatch: jest.fn( () => ( {} ) ),
	select: jest.fn( () => ( {} ) ),
} ) );

jest.mock( '@wordpress/data-controls', () => ( { controls: {} } ) );
jest.mock( '@wordpress/core-data', () => ( { store: 'core' } ) );
jest.mock( '@wordpress/preferences', () => ( { store: 'core/preferences' } ) );
jest.mock( '@wordpress/views', () => ( { loadView: jest.fn(), useView: jest.fn() } ) );
jest.mock( '@wordpress/viewport', () => ( { store: 'core/viewport' } ) );
jest.mock( '@wordpress/route', () => ( {
	redirect: jest.fn( ( options ) => options ),
	notFound: jest.fn( () => new Error( 'Not found' ) ),
} ) );

const mockResolveSelect = resolveSelect as jest.Mock;
const mockLoadView = loadView as jest.Mock;

describe( 'feed route', () => {
	const getActiveActorId = jest.fn();
	const getEntityRecords = jest.fn();
	const getCurrentUser = jest.fn();
	const getEntityRecord = jest.fn();
	const canUser = jest.fn();
	const isViewportMatch = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		getActiveActorId.mockResolvedValue( 0 );
		isViewportMatch.mockReturnValue( true );
		( select as jest.Mock ).mockReturnValue( { isViewportMatch } );
		mockLoadView.mockResolvedValue( DEFAULT_VIEW );
		getEntityRecords.mockResolvedValue( [] );
		getCurrentUser.mockResolvedValue( { id: 42 } );
		getEntityRecord.mockResolvedValue( { activitypub_actor_mode: 'actor_blog' } );
		canUser.mockResolvedValue( true );
		mockResolveSelect.mockImplementation( ( store: unknown ) =>
			store === 'core' ? { getEntityRecords, getCurrentUser, getEntityRecord, canUser } : { getActiveActorId }
		);
	} );

	it( 'shows the inspector only when a post is selected', () => {
		const context = { params: {}, search: {} };

		expect( route.inspector?.( context ) ).toBe( false );
		expect( route.inspector?.( { ...context, search: { postIds: [] } } ) ).toBe( false );
		expect( route.inspector?.( { ...context, search: { postIds: [ '5' ] } } ) ).toBe( true );
	} );

	it.each( [ '0', '42' ] )( 'preloads account %s without changing the saved account', async ( actorId ) => {
		const context = { params: { actorId }, search: {} };
		await route.loader?.( context );
		expect( dispatch ).not.toHaveBeenCalled();
		expect( getEntityRecords ).toHaveBeenCalledWith(
			'postType',
			'ap_post',
			viewToQuery( DEFAULT_VIEW, Number( actorId ) )
		);
		expect( redirect ).not.toHaveBeenCalled();
	} );

	it.each( [ 0, 42 ] )(
		'uses the reset view only when preloading a different account (current: %s)',
		async ( activeActorId ) => {
			getActiveActorId.mockResolvedValue( activeActorId );
			const view = {
				...DEFAULT_VIEW,
				perPage: 50,
				filters: [ { field: 'ap_tag', operator: 'isAny' as const, value: [ 7 ] } ],
			};
			mockLoadView.mockResolvedValue( view );
			await route.loader?.( { params: { actorId: '42' }, search: {} } );
			expect( getEntityRecords ).toHaveBeenCalledWith(
				'postType',
				'ap_post',
				viewToQuery( activeActorId === 42 ? view : DEFAULT_VIEW, 42 )
			);
			expect( dispatch ).not.toHaveBeenCalled();
		}
	);

	it.each( [ '43', '-1', '00', 'NaN', '42.0' ] )( 'rejects invalid and foreign accounts %s', async ( actorId ) => {
		await expect( route.loader?.( { params: { actorId }, search: {} } ) ).rejects.toThrow( 'Not found' );
		expect( getEntityRecords ).not.toHaveBeenCalled();
	} );

	it.each( [ '0', '42' ] )( 'checks permissions before fetching account %s', async ( actorId ) => {
		canUser.mockResolvedValue( false );
		await expect( route.loader?.( { params: { actorId }, search: {} } ) ).rejects.toThrow( 'Not found' );
		expect( getEntityRecords ).not.toHaveBeenCalled();
	} );

	it.each( [
		[ '0', 'actor' ],
		[ '42', 'blog' ],
	] )( 'rejects account %s when mode is %s', async ( actorId, mode ) => {
		getEntityRecord.mockResolvedValue( { activitypub_actor_mode: mode } );
		await expect( route.loader?.( { params: { actorId }, search: {} } ) ).rejects.toThrow( 'Not found' );
		expect( getEntityRecords ).not.toHaveBeenCalled();
	} );

	it( 'keeps the account route when opening the first post', async () => {
		getEntityRecords.mockResolvedValue( [ { id: 99 } ] );
		await expect( route.loader?.( { params: { actorId: '0' }, search: {} } ) ).rejects.toEqual( {
			to: '/account/0',
			search: { postIds: [ '99' ] },
			replace: true,
		} );
	} );

	it( 'warms the records the stage will ask for, from the view it will use', async () => {
		const view = {
			...DEFAULT_VIEW,
			perPage: 10,
			page: 3,
			sort: { field: 'modified', direction: 'asc' as const },
			search: 'fediverse',
			filters: [ { field: 'ap_object_type', operator: 'isAny' as const, value: [ 7 ] } ],
		};
		getActiveActorId.mockResolvedValue( 42 );
		mockLoadView.mockResolvedValue( view );
		getEntityRecords.mockResolvedValue( [] );

		await route.loader?.( { params: {}, search: { page: 3, search: 'fediverse' } } );

		expect( mockLoadView ).toHaveBeenCalledWith( {
			kind: 'postType',
			name: 'ap_post',
			slug: 'feed',
			defaultView: DEFAULT_VIEW,
			defaultLayouts,
			queryParams: { page: 3, search: 'fediverse' },
		} );
		expect( getEntityRecords ).toHaveBeenCalledTimes( 1 );
		expect( getEntityRecords ).toHaveBeenCalledWith( 'postType', 'ap_post', viewToQuery( view, 42 ) );
	} );

	it( 'passes no page or search when the URL has none', async () => {
		getActiveActorId.mockResolvedValue( 1 );
		mockLoadView.mockResolvedValue( DEFAULT_VIEW );
		getEntityRecords.mockResolvedValue( [] );

		await route.loader?.( { params: {}, search: {} } );

		expect( mockLoadView.mock.calls[ 0 ][ 0 ].queryParams ).toEqual( { page: undefined, search: undefined } );
	} );

	it( 'resolves the first desktop selection before rendering, preserving other search parameters', async () => {
		getEntityRecords.mockResolvedValue( [ { id: 42 }, { id: 43 } ] );
		await expect( route.loader?.( { params: {}, search: { search: 'test' } } ) ).rejects.toEqual( {
			to: '/',
			search: { search: 'test', postIds: [ '42' ] },
			replace: true,
		} );
	} );

	it.each( [ { postIds: [] }, { postIds: [ '43' ] } ] )(
		'preserves explicit selection $postIds',
		async ( search ) => {
			getEntityRecords.mockResolvedValue( [ { id: 42 }, { id: 43 } ] );
			await route.loader?.( { params: {}, search } );
			expect( redirect ).not.toHaveBeenCalled();
		}
	);

	it( 'starts with the list on mobile', async () => {
		isViewportMatch.mockReturnValue( false );
		getEntityRecords.mockResolvedValue( [ { id: 42 } ] );
		await route.loader?.( { params: {}, search: {} } );
		expect( redirect ).not.toHaveBeenCalled();
	} );

	it( 'leaves an empty feed unselected', async () => {
		await route.loader?.( { params: {}, search: {} } );
		expect( redirect ).not.toHaveBeenCalled();
	} );

	it( 'keeps the filtered route when selecting its first item', async () => {
		getEntityRecords.mockResolvedValue( [ { id: 42 } ] );
		await expect( route.loader?.( { params: { taxonomy: 'type', termId: '7' }, search: {} } ) ).rejects.toEqual( {
			to: '/feed/type/7',
			search: { postIds: [ '42' ] },
			replace: true,
		} );
		expect( getEntityRecords ).toHaveBeenCalledWith(
			'postType',
			'ap_post',
			expect.objectContaining( { ap_object_type: [ 7 ] } )
		);
	} );

	it.each( [
		[ 'other', '7' ],
		[ 'tag', 'NaN' ],
		[ 'type', '0' ],
		[ 'tag', '-1' ],
		[ 'tag', '1.5' ],
	] )( 'rejects invalid shortcuts %s/%s', async ( taxonomy, termId ) => {
		await expect( route.loader?.( { params: { taxonomy, termId }, search: {} } ) ).rejects.toThrow( 'Not found' );
		expect( notFound ).toHaveBeenCalled();
		expect( getEntityRecords ).not.toHaveBeenCalled();
	} );
} );
