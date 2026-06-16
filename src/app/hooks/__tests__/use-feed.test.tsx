/**
 * @jest-environment jsdom
 */

const mockUseEntityRecords = jest.fn();

jest.mock( '@wordpress/core-data', () => ( {
	useEntityRecords: ( ...args: unknown[] ) => mockUseEntityRecords( ...args ),
} ) );

import { renderHook } from '@testing-library/react';
import { useFeed } from '../use-feed';

/**
 * Returns the arguments the hook passed to `useEntityRecords` on its last call.
 */
function lastCall(): { kind: string; name: string; query: Record< string, unknown >; options: { enabled: boolean } } {
	const call = mockUseEntityRecords.mock.calls[ mockUseEntityRecords.mock.calls.length - 1 ];
	return { kind: call[ 0 ], name: call[ 1 ], query: call[ 2 ], options: call[ 3 ] };
}

describe( 'useFeed', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockUseEntityRecords.mockReturnValue( {
			records: [],
			hasResolved: true,
			isResolving: false,
			totalItems: 0,
			totalPages: 0,
		} );
	} );

	it( 'should query the ap_post post type', () => {
		renderHook( () => useFeed( { userId: 1 } ) );
		const { kind, name } = lastCall();
		expect( kind ).toBe( 'postType' );
		expect( name ).toBe( 'ap_post' );
	} );

	it( 'should be disabled and return empty data when userId is missing', () => {
		const { result } = renderHook( () => useFeed() );
		const { query, options } = lastCall();

		expect( options.enabled ).toBe( false );
		expect( query.user_id ).toBeUndefined();
		expect( result.current.feed ).toEqual( [] );
		expect( result.current.totalItems ).toBeNull();
		expect( result.current.totalPages ).toBeNull();
	} );

	it( 'should be enabled and pass user_id when userId is provided', () => {
		renderHook( () => useFeed( { userId: 0 } ) );
		const { query, options } = lastCall();
		expect( options.enabled ).toBe( true );
		expect( query.user_id ).toBe( 0 );
	} );

	it( 'should map pagination and ordering params to REST query args', () => {
		renderHook( () =>
			useFeed( { userId: 1, perPage: 5, page: 2, orderBy: 'title', order: 'asc', search: 'hello' } )
		);
		const { query } = lastCall();
		expect( query.per_page ).toBe( 5 );
		expect( query.page ).toBe( 2 );
		expect( query.orderby ).toBe( 'title' );
		expect( query.order ).toBe( 'asc' );
		expect( query.search ).toBe( 'hello' );
	} );

	it( 'should wrap a single ap_object_type filter value in an array', () => {
		renderHook( () =>
			useFeed( { userId: 1, filters: [ { field: 'ap_object_type', operator: 'is', value: 4 } ] } )
		);
		expect( lastCall().query.ap_object_type ).toEqual( [ 4 ] );
	} );

	it( 'should pass an array ap_object_type filter value through unchanged', () => {
		renderHook( () =>
			useFeed( { userId: 1, filters: [ { field: 'ap_object_type', operator: 'isAny', value: [ 4, 5 ] } ] } )
		);
		expect( lastCall().query.ap_object_type ).toEqual( [ 4, 5 ] );
	} );

	it( 'should pass the ap_tag filter value through as-is', () => {
		renderHook( () => useFeed( { userId: 1, filters: [ { field: 'ap_tag', operator: 'isAny', value: [ 7 ] } ] } ) );
		expect( lastCall().query.ap_tag ).toEqual( [ 7 ] );
	} );

	it( 'should not add filter args when no filters are provided', () => {
		renderHook( () => useFeed( { userId: 1 } ) );
		const { query } = lastCall();
		expect( query.ap_object_type ).toBeUndefined();
		expect( query.ap_tag ).toBeUndefined();
	} );

	it( 'should request a limited set of _fields', () => {
		renderHook( () => useFeed( { userId: 1 } ) );
		const fields = lastCall().query._fields as string[];
		expect( Array.isArray( fields ) ).toBe( true );
		// A few representative fields the feed list and inspector rely on.
		expect( fields ).toEqual(
			expect.arrayContaining( [ 'id', 'title', 'actor_info', 'ap_object_type', 'ap_tag' ] )
		);
	} );

	it( 'should pass custom fields through to the query', () => {
		renderHook( () => useFeed( { userId: 1, fields: [ 'id', 'title' ] } ) );
		expect( lastCall().query._fields ).toEqual( [ 'id', 'title' ] );
	} );

	it( 'should return the resolved records when enabled', () => {
		const records = [ { id: 1 }, { id: 2 } ];
		mockUseEntityRecords.mockReturnValue( {
			records,
			hasResolved: true,
			isResolving: false,
			totalItems: 2,
			totalPages: 1,
		} );

		const { result } = renderHook( () => useFeed( { userId: 1 } ) );
		expect( result.current.feed ).toBe( records );
		expect( result.current.totalItems ).toBe( 2 );
		expect( result.current.totalPages ).toBe( 1 );
	} );

	it( 'should fall back to an empty feed when records is null', () => {
		mockUseEntityRecords.mockReturnValue( {
			records: null,
			hasResolved: true,
			isResolving: false,
			totalItems: null,
			totalPages: null,
		} );

		const { result } = renderHook( () => useFeed( { userId: 1 } ) );
		expect( result.current.feed ).toEqual( [] );
	} );
} );
