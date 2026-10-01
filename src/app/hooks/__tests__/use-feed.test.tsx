/**
 * @jest-environment jsdom
 */

const mockUseEntityRecords = jest.fn();

jest.mock( '@wordpress/core-data', () => ( {
	useEntityRecords: ( ...args: unknown[] ) => mockUseEntityRecords( ...args ),
} ) );

import { renderHook } from '@testing-library/react';
import { useFeed } from '../use-feed';
import type { FeedQuery } from '../use-feed';

const QUERY: FeedQuery = {
	per_page: 20,
	page: 1,
	orderby: 'date',
	order: 'desc',
	search: '',
	_fields: [ 'id' ],
};

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

	it( 'should query the ap_post post type with the given query', () => {
		const query = { ...QUERY, user_id: 1 };
		renderHook( () => useFeed( query ) );
		const { kind, name, query: passed } = lastCall();
		expect( kind ).toBe( 'postType' );
		expect( name ).toBe( 'ap_post' );
		expect( passed ).toBe( query );
	} );

	it( 'should be disabled and return empty data when user_id is missing', () => {
		const { result } = renderHook( () => useFeed( QUERY ) );

		expect( lastCall().options.enabled ).toBe( false );
		expect( result.current.feed ).toEqual( [] );
		expect( result.current.totalItems ).toBeNull();
		expect( result.current.totalPages ).toBeNull();
	} );

	it( 'should be enabled when user_id is provided, even when it is 0', () => {
		renderHook( () => useFeed( { ...QUERY, user_id: 0 } ) );
		expect( lastCall().options.enabled ).toBe( true );
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

		const { result } = renderHook( () => useFeed( { ...QUERY, user_id: 1 } ) );
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

		const { result } = renderHook( () => useFeed( { ...QUERY, user_id: 1 } ) );
		expect( result.current.feed ).toEqual( [] );
	} );
} );
