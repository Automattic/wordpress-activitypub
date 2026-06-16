/**
 * @jest-environment jsdom
 */

const mockUpdateView = jest.fn();
const mockNavigate = jest.fn();
let mockView: { filters?: Array< { field: string; operator?: string; value: unknown } >; page?: number } = {
	filters: [],
};

jest.mock( '@wordpress/views', () => ( {
	useView: () => ( { view: mockView, updateView: mockUpdateView } ),
} ) );

jest.mock( '../../router', () => ( {
	useNavigate: () => mockNavigate,
} ) );

import { renderHook, act } from '@testing-library/react';
import { useObjectTypeFilter } from '../use-object-type-filter';

describe( 'useObjectTypeFilter', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockView = { filters: [] };
	} );

	describe( 'selectedObjectTypeId', () => {
		it( 'should be null when no object type filter is set', () => {
			const { result } = renderHook( () => useObjectTypeFilter() );
			expect( result.current.selectedObjectTypeId ).toBeNull();
		} );

		it( 'should return the filter value when an object type filter is set', () => {
			mockView = { filters: [ { field: 'ap_object_type', operator: 'is', value: 4 } ] };
			const { result } = renderHook( () => useObjectTypeFilter() );
			expect( result.current.selectedObjectTypeId ).toBe( 4 );
		} );

		it( 'should ignore unrelated filters', () => {
			mockView = { filters: [ { field: 'ap_tag', operator: 'isAny', value: [ 1 ] } ] };
			const { result } = renderHook( () => useObjectTypeFilter() );
			expect( result.current.selectedObjectTypeId ).toBeNull();
		} );
	} );

	describe( 'updateObjectTypeFilter', () => {
		it( 'should add a filter when none exists', () => {
			mockView = { filters: [] };
			const { result } = renderHook( () => useObjectTypeFilter() );

			act( () => {
				result.current.updateObjectTypeFilter( 4 );
			} );

			expect( mockUpdateView ).toHaveBeenCalledWith( {
				filters: [ { field: 'ap_object_type', operator: 'is', value: 4 } ],
				page: 1,
			} );
		} );

		it( 'should remove the filter when toggling the same object type', () => {
			mockView = { filters: [ { field: 'ap_object_type', operator: 'is', value: 4 } ] };
			const { result } = renderHook( () => useObjectTypeFilter() );

			act( () => {
				result.current.updateObjectTypeFilter( 4 );
			} );

			expect( mockUpdateView ).toHaveBeenCalledWith( {
				filters: [],
				page: 1,
			} );
		} );

		it( 'should replace the value when a different object type is selected', () => {
			mockView = { filters: [ { field: 'ap_object_type', operator: 'is', value: 4 } ] };
			const { result } = renderHook( () => useObjectTypeFilter() );

			act( () => {
				result.current.updateObjectTypeFilter( 9 );
			} );

			expect( mockUpdateView ).toHaveBeenCalledWith( {
				filters: [ { field: 'ap_object_type', operator: 'is', value: 9 } ],
				page: 1,
			} );
		} );

		it( 'should clear the object type filter when called with null', () => {
			mockView = {
				filters: [
					{ field: 'ap_object_type', operator: 'is', value: 4 },
					{ field: 'ap_tag', operator: 'isAny', value: [ 1 ] },
				],
			};
			const { result } = renderHook( () => useObjectTypeFilter() );

			act( () => {
				result.current.updateObjectTypeFilter( null );
			} );

			expect( mockUpdateView ).toHaveBeenCalledWith( {
				filters: [ { field: 'ap_tag', operator: 'isAny', value: [ 1 ] } ],
				page: 1,
			} );
		} );

		it( 'should close the inspector by removing postId from the URL', () => {
			const { result } = renderHook( () => useObjectTypeFilter() );

			act( () => {
				result.current.updateObjectTypeFilter( 4 );
			} );

			expect( mockNavigate ).toHaveBeenCalledTimes( 1 );
			const searchFn = mockNavigate.mock.calls[ 0 ][ 0 ].search;
			expect( searchFn( { postId: 1, foo: 'bar' } ) ).toEqual( { foo: 'bar' } );
		} );

		it( 'should call the onComplete callback when provided', () => {
			const onComplete = jest.fn();
			const { result } = renderHook( () => useObjectTypeFilter() );

			act( () => {
				result.current.updateObjectTypeFilter( 4, { onComplete } );
			} );

			expect( onComplete ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
