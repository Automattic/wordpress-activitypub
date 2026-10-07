/**
 * External dependencies
 */
import { act, render, screen, fireEvent } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import { DataViews } from '@wordpress/dataviews/wp';
import { useSearch, useNavigate, useParams } from '@wordpress/route';
import { useView } from '@wordpress/views';

/**
 * Internal dependencies
 */
import FeedStage from '../stage';
import { DEFAULT_VIEW } from '../utils';
import { useFeed } from '../../../hooks/use-feed';

const mockDataViews = jest.mocked( DataViews );

jest.mock( '@wordpress/route', () => ( { useSearch: jest.fn(), useNavigate: jest.fn(), useParams: jest.fn() } ) );
jest.mock( '@wordpress/views', () => ( { useView: jest.fn() } ) );
jest.mock( '@wordpress/data', () => ( { useSelect: () => 0 } ) );
jest.mock( '@wordpress/dataviews/wp', () => ( {
	DataViews: Object.assign(
		jest.fn( ( { children } ) => <div>{ children }</div> ),
		{
			Search: () => null,
			FiltersToggle: () => null,
			ViewConfig: () => null,
			Filters: () => <div data-testid="filters" />,
			Layout: () => null,
			Footer: () => null,
		}
	),
} ) );
jest.mock( '@wordpress/components', () => ( {
	__experimentalHStack: ( { children } ) => <div>{ children }</div>,
	Button: ( { label, onClick, 'aria-expanded': expanded } ) => (
		<button aria-label={ label } aria-expanded={ expanded } onClick={ onClick } />
	),
} ) );
jest.mock( '../../../store', () => ( { STORE_NAME: 'activitypub/app' } ) );
jest.mock( '../../../hooks/use-feed', () => ( { useFeed: jest.fn() } ) );
jest.mock( '../../../components/empty-state', () => () => null );
jest.mock( '../../../components/fields', () => ( {
	titleField: { id: 'title.rendered' },
	dateField: { id: 'date' },
	metadataField: { id: 'metadata' },
	contentField: { id: 'content' },
	objectTypeField: { id: 'ap_object_type' },
	tagField: { id: 'ap_tag' },
} ) );

describe( 'FeedStage filters', () => {
	const navigate = jest.fn();
	const updateView = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		( useNavigate as jest.Mock ).mockReturnValue( navigate );
		( useSearch as jest.Mock ).mockReturnValue( {} );
		( useParams as jest.Mock ).mockReturnValue( { taxonomy: 'tag', termId: '7' } );
		( useView as jest.Mock ).mockReturnValue( { view: DEFAULT_VIEW, updateView } );
		( useFeed as jest.Mock ).mockReturnValue( { feed: [], isResolving: false, totalItems: 0, totalPages: 0 } );
	} );

	it( 'does not force primary or locked filters that disable the core toggle', () => {
		render( <FeedStage /> );
		const { fields, view } = mockDataViews.mock.calls[ 0 ][ 0 ];
		expect( fields.some( ( field ) => field.filterBy && field.filterBy.isPrimary ) ).toBe( false );
		expect( view.filters ).toEqual( [ { field: 'ap_tag', operator: 'isAny', value: [ 7 ] } ] );
	} );

	it.each( [ [], [ { field: 'ap_tag', operator: 'isAny' as const, value: [ 9 ] } ] ] )(
		'persists an edited shortcut and leaves the route that would reapply it (%j)',
		( ...filters ) => {
			render( <FeedStage /> );
			const { view, onChangeView } = mockDataViews.mock.calls[ 0 ][ 0 ];
			act( () => onChangeView( { ...view, filters } ) );
			expect( updateView ).toHaveBeenCalledWith( expect.objectContaining( { filters } ) );
			expect( navigate ).toHaveBeenCalledTimes( 1 );
			const options = navigate.mock.calls[ 0 ][ 0 ];
			expect( options.to ).toBe( '/' );
			expect( options.search( { postIds: [ '42' ], other: 'keep' } ) ).toEqual( {
				postIds: undefined,
				other: 'keep',
				page: 1,
				search: '',
			} );
		}
	);

	it( 'keeps the shortcut route when changing the search', () => {
		render( <FeedStage /> );
		const { view, onChangeView } = mockDataViews.mock.calls[ 0 ][ 0 ];
		act( () => onChangeView( { ...view, search: 'hello' } ) );
		expect( navigate.mock.calls[ 0 ][ 0 ].to ).toBe( '/feed/tag/7' );
		expect( navigate.mock.calls[ 0 ][ 0 ].search( {} ).search ).toBe( 'hello' );
		expect( updateView.mock.calls[ 0 ][ 0 ].filters ).toEqual( [] );
	} );

	it.each( [
		[ 'tag', { startPosition: 21 } ],
		[ 'type', { fields: [ 'metadata', 'title.rendered' ] } ],
		[ 'tag', { sort: { field: 'date', direction: 'asc' as const } } ],
	] )( 'does not save a %s shortcut during an unrelated view update (%j)', ( taxonomy, changes ) => {
		( useParams as jest.Mock ).mockReturnValue( { taxonomy, termId: '7' } );
		const { unmount } = render( <FeedStage /> );
		const { view, onChangeView } = mockDataViews.mock.calls[ 0 ][ 0 ];
		act( () => onChangeView( { ...view, ...changes } ) );
		const persistedView = updateView.mock.calls[ 0 ][ 0 ];
		expect( persistedView.filters ).toEqual( [] );
		expect( persistedView ).toMatchObject( changes );

		// All posts mounts a new route using the shared preference.
		unmount();
		( useParams as jest.Mock ).mockReturnValue( {} );
		( useView as jest.Mock ).mockReturnValue( { view: persistedView, updateView } );
		render( <FeedStage /> );
		expect( useFeed ).toHaveBeenLastCalledWith(
			expect.not.objectContaining( { [ taxonomy === 'tag' ? 'ap_tag' : 'ap_object_type' ]: expect.anything() } )
		);
	} );

	it( 'preserves a saved same-field filter when a shortcut temporarily replaces it', () => {
		const filters = [
			{ field: 'ap_tag', operator: 'isAny' as const, value: [ 3 ] },
			{ field: 'date', operator: 'before' as const, value: '2026-01-01T00:00:00' },
		];
		( useView as jest.Mock ).mockReturnValue( { view: { ...DEFAULT_VIEW, filters }, updateView } );
		render( <FeedStage /> );
		const { view, onChangeView } = mockDataViews.mock.calls[ 0 ][ 0 ];
		act( () => onChangeView( { ...view, fields: [ 'title.rendered' ] } ) );
		expect( updateView ).toHaveBeenCalledWith( expect.objectContaining( { filters } ) );
	} );

	it( 'queries the reset view on the first render of a different account route', () => {
		( useParams as jest.Mock ).mockReturnValue( { actorId: '7' } );
		( useView as jest.Mock ).mockReturnValue( {
			view: {
				...DEFAULT_VIEW,
				fields: [ 'title.rendered' ],
				filters: [ { field: 'ap_tag', operator: 'isAny', value: [ 7 ] } ],
				perPage: 50,
			},
			updateView,
		} );
		render( <FeedStage /> );
		expect( useFeed ).toHaveBeenCalledWith( expect.objectContaining( { user_id: 7, per_page: 20, page: 1 } ) );
		expect( useFeed ).not.toHaveBeenCalledWith( expect.objectContaining( { ap_tag: expect.anything() } ) );
		expect( mockDataViews.mock.calls[ 0 ][ 0 ].view.fields ).toEqual( [ 'title.rendered' ] );
	} );

	it( 'uses the account from the route before its preference is saved', () => {
		( useParams as jest.Mock ).mockReturnValue( { actorId: '7' } );
		render( <FeedStage /> );
		expect( useFeed ).toHaveBeenCalledWith( expect.objectContaining( { user_id: 7 } ) );
		const { view, onChangeView } = mockDataViews.mock.calls[ 0 ][ 0 ];
		act( () => onChangeView( { ...view, search: 'hello' } ) );
		expect( navigate.mock.calls[ 0 ][ 0 ].to ).toBe( '/account/7' );
	} );

	it( 'opens linked filters, allows collapse, and reopens on another shortcut', () => {
		const { rerender } = render( <FeedStage /> );
		expect( screen.getByTestId( 'filters' ) ).toBeTruthy();
		fireEvent.click( screen.getByRole( 'button', { name: 'Filter' } ) );
		expect( screen.queryByTestId( 'filters' ) ).toBeNull();
		( useParams as jest.Mock ).mockReturnValue( { taxonomy: 'tag', termId: '8' } );
		rerender( <FeedStage /> );
		expect( screen.getByTestId( 'filters' ) ).toBeTruthy();
		fireEvent.click( screen.getByRole( 'button', { name: 'Filter' } ) );
		expect( screen.queryByTestId( 'filters' ) ).toBeNull();
	} );
} );
