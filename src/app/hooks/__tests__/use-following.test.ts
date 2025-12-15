/**
 * @jest-environment jsdom
 */

import { renderHook } from '@testing-library/react';
import { useFollowing } from '../use-following';

// Mock @wordpress/core-data
const mockUseEntityRecords = jest.fn();

jest.mock( '@wordpress/core-data', () => ( {
	useEntityRecords: ( ...args: any[] ) => mockUseEntityRecords( ...args ),
} ) );

describe( 'useFollowing', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	describe( 'when userId is provided', () => {
		it( 'should call useEntityRecords with followed_by parameter', () => {
			mockUseEntityRecords.mockReturnValue( {
				records: [],
				hasResolved: true,
				isResolving: false,
				totalItems: 0,
				totalPages: 0,
			} );

			renderHook( () => useFollowing( { userId: 1 } ) );

			expect( mockUseEntityRecords ).toHaveBeenCalledWith(
				'postType',
				'ap_actor',
				expect.objectContaining( {
					followed_by: 1,
					per_page: 20,
					page: 1,
				} )
			);
		} );

		it( 'should return following data when resolved', () => {
			const mockActors = [
				{ id: 1, title: { rendered: 'Actor 1' } },
				{ id: 2, title: { rendered: 'Actor 2' } },
			];

			mockUseEntityRecords.mockReturnValue( {
				records: mockActors,
				hasResolved: true,
				isResolving: false,
				totalItems: 2,
				totalPages: 1,
			} );

			const { result } = renderHook( () => useFollowing( { userId: 1 } ) );

			expect( result.current.following ).toEqual( mockActors );
			expect( result.current.hasResolved ).toBe( true );
			expect( result.current.isResolving ).toBe( false );
			expect( result.current.totalItems ).toBe( 2 );
			expect( result.current.totalPages ).toBe( 1 );
		} );

		it( 'should return empty array when no following', () => {
			mockUseEntityRecords.mockReturnValue( {
				records: [],
				hasResolved: true,
				isResolving: false,
				totalItems: 0,
				totalPages: 0,
			} );

			const { result } = renderHook( () => useFollowing( { userId: 1 } ) );

			expect( result.current.following ).toEqual( [] );
			expect( result.current.totalItems ).toBe( 0 );
		} );

		it( 'should work with blog actor (userId = 0)', () => {
			mockUseEntityRecords.mockReturnValue( {
				records: [],
				hasResolved: true,
				isResolving: false,
				totalItems: 0,
				totalPages: 0,
			} );

			renderHook( () => useFollowing( { userId: 0 } ) );

			expect( mockUseEntityRecords ).toHaveBeenCalledWith(
				'postType',
				'ap_actor',
				expect.objectContaining( {
					followed_by: 0,
				} )
			);
		} );
	} );

	describe( 'when userId is null or undefined', () => {
		it( 'should not fetch when userId is null', () => {
			mockUseEntityRecords.mockReturnValue( {
				records: null,
				hasResolved: false,
				isResolving: false,
				totalItems: null,
				totalPages: null,
			} );

			const { result } = renderHook( () => useFollowing( { userId: null } ) );

			expect( mockUseEntityRecords ).toHaveBeenCalledWith( 'postType', 'ap_actor', undefined );
			expect( result.current.following ).toEqual( [] );
			expect( result.current.totalItems ).toBe( null );
		} );

		it( 'should not fetch when userId is undefined', () => {
			mockUseEntityRecords.mockReturnValue( {
				records: null,
				hasResolved: false,
				isResolving: false,
				totalItems: null,
				totalPages: null,
			} );

			const { result } = renderHook( () => useFollowing( {} ) );

			expect( mockUseEntityRecords ).toHaveBeenCalledWith( 'postType', 'ap_actor', undefined );
			expect( result.current.following ).toEqual( [] );
		} );
	} );

	describe( 'pagination and sorting', () => {
		it( 'should pass custom perPage and page parameters', () => {
			mockUseEntityRecords.mockReturnValue( {
				records: [],
				hasResolved: true,
				isResolving: false,
				totalItems: 0,
				totalPages: 0,
			} );

			renderHook( () => useFollowing( { userId: 1, perPage: 10, page: 2 } ) );

			expect( mockUseEntityRecords ).toHaveBeenCalledWith(
				'postType',
				'ap_actor',
				expect.objectContaining( {
					per_page: 10,
					page: 2,
				} )
			);
		} );

		it( 'should pass custom order parameters', () => {
			mockUseEntityRecords.mockReturnValue( {
				records: [],
				hasResolved: true,
				isResolving: false,
				totalItems: 0,
				totalPages: 0,
			} );

			renderHook( () => useFollowing( { userId: 1, orderBy: 'date', order: 'asc' } ) );

			expect( mockUseEntityRecords ).toHaveBeenCalledWith(
				'postType',
				'ap_actor',
				expect.objectContaining( {
					orderby: 'date',
					order: 'asc',
				} )
			);
		} );
	} );
} );
