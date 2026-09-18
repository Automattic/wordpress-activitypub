/**
 * @jest-environment jsdom
 */

const mockUpdateView = jest.fn();
let mockView: { filters?: unknown[]; page?: number } = { filters: [] };

jest.mock( '@wordpress/views', () => ( {
	useView: () => ( { view: mockView, updateView: mockUpdateView } ),
} ) );

import { renderHook, act } from '@testing-library/react';
import { useFeedFilters } from '../use-feed-filters';

describe( 'useFeedFilters', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockView = { filters: [] };
	} );

	describe( 'hasActiveFilters', () => {
		it( 'should be false when there are no filters', () => {
			mockView = { filters: [] };
			const { result } = renderHook( () => useFeedFilters() );
			expect( result.current.hasActiveFilters ).toBe( false );
		} );

		it( 'should be false when filters is undefined', () => {
			mockView = {};
			const { result } = renderHook( () => useFeedFilters() );
			expect( result.current.hasActiveFilters ).toBe( false );
		} );

		it( 'should be true when at least one filter is active', () => {
			mockView = { filters: [ { field: 'ap_tag', operator: 'isAny', value: [ 1 ] } ] };
			const { result } = renderHook( () => useFeedFilters() );
			expect( result.current.hasActiveFilters ).toBe( true );
		} );
	} );

	describe( 'clearAllFilters', () => {
		it( 'should reset filters and return to the first page', () => {
			mockView = { filters: [ { field: 'ap_tag', value: [ 1 ] } ], page: 3 };
			const { result } = renderHook( () => useFeedFilters() );

			act( () => {
				result.current.clearAllFilters();
			} );

			expect( mockUpdateView ).toHaveBeenCalledWith( {
				filters: [],
				page: 1,
			} );
		} );
	} );
} );
