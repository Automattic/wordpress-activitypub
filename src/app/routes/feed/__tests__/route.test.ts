/**
 * Tests for the feed route module.
 */

/**
 * WordPress dependencies
 */
import { resolveSelect } from '@wordpress/data';
import { loadView } from '@wordpress/views';

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

const mockResolveSelect = resolveSelect as jest.Mock;
const mockLoadView = loadView as jest.Mock;

describe( 'feed route', () => {
	const getActiveActorId = jest.fn();
	const getEntityRecords = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		mockResolveSelect.mockImplementation( ( store: unknown ) =>
			store === 'core' ? { getEntityRecords } : { getActiveActorId }
		);
	} );

	it( 'shows the inspector only when a post is selected', () => {
		const context = { params: {}, search: {} };

		expect( route.inspector?.( context ) ).toBe( false );
		expect( route.inspector?.( { ...context, search: { postIds: [] } } ) ).toBe( false );
		expect( route.inspector?.( { ...context, search: { postIds: [ '5' ] } } ) ).toBe( true );
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
} );
