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
import { useTagFilter } from '../use-tag-filter';

describe( 'useTagFilter', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockView = { filters: [] };
	} );

	describe( 'selectedTagId', () => {
		it( 'should be null when no tag filter is set', () => {
			const { result } = renderHook( () => useTagFilter() );
			expect( result.current.selectedTagId ).toBeNull();
		} );

		it( 'should return the tag id when exactly one tag is selected', () => {
			mockView = { filters: [ { field: 'ap_tag', operator: 'isAny', value: [ 5 ] } ] };
			const { result } = renderHook( () => useTagFilter() );
			expect( result.current.selectedTagId ).toBe( 5 );
		} );

		it( 'should be null when multiple tags are selected', () => {
			mockView = { filters: [ { field: 'ap_tag', operator: 'isAny', value: [ 5, 6 ] } ] };
			const { result } = renderHook( () => useTagFilter() );
			expect( result.current.selectedTagId ).toBeNull();
		} );
	} );

	describe( 'updateTagFilter', () => {
		it( 'should add a tag filter when none exists', () => {
			mockView = { filters: [] };
			const { result } = renderHook( () => useTagFilter() );

			act( () => {
				result.current.updateTagFilter( 5 );
			} );

			expect( mockUpdateView ).toHaveBeenCalledWith( {
				filters: [ { field: 'ap_tag', operator: 'isAny', value: [ 5 ] } ],
				page: 1,
			} );
		} );

		it( 'should remove the filter when toggling the same tag', () => {
			mockView = { filters: [ { field: 'ap_tag', operator: 'isAny', value: [ 5 ] } ] };
			const { result } = renderHook( () => useTagFilter() );

			act( () => {
				result.current.updateTagFilter( 5 );
			} );

			expect( mockUpdateView ).toHaveBeenCalledWith( {
				filters: [],
				page: 1,
			} );
		} );

		it( 'should replace the tag when a different tag is selected', () => {
			mockView = { filters: [ { field: 'ap_tag', operator: 'isAny', value: [ 5 ] } ] };
			const { result } = renderHook( () => useTagFilter() );

			act( () => {
				result.current.updateTagFilter( 9 );
			} );

			expect( mockUpdateView ).toHaveBeenCalledWith( {
				filters: [ { field: 'ap_tag', operator: 'isAny', value: [ 9 ] } ],
				page: 1,
			} );
		} );

		it( 'should clear the tag filter when called with null', () => {
			mockView = {
				filters: [
					{ field: 'ap_tag', operator: 'isAny', value: [ 5 ] },
					{ field: 'ap_object_type', operator: 'is', value: 4 },
				],
			};
			const { result } = renderHook( () => useTagFilter() );

			act( () => {
				result.current.updateTagFilter( null );
			} );

			expect( mockUpdateView ).toHaveBeenCalledWith( {
				filters: [ { field: 'ap_object_type', operator: 'is', value: 4 } ],
				page: 1,
			} );
		} );

		it( 'should close the inspector by removing postId from the URL', () => {
			const { result } = renderHook( () => useTagFilter() );

			act( () => {
				result.current.updateTagFilter( 5 );
			} );

			expect( mockNavigate ).toHaveBeenCalledTimes( 1 );
			const searchFn = mockNavigate.mock.calls[ 0 ][ 0 ].search;
			expect( searchFn( { postId: 1, foo: 'bar' } ) ).toEqual( { foo: 'bar' } );
		} );

		it( 'should call the onComplete callback when provided', () => {
			const onComplete = jest.fn();
			const { result } = renderHook( () => useTagFilter() );

			act( () => {
				result.current.updateTagFilter( 5, { onComplete } );
			} );

			expect( onComplete ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
