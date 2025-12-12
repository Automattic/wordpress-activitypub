/**
 * WordPress dependencies
 */
import { Filter } from '@wordpress/dataviews';
import { useMemo, useCallback } from '@wordpress/element';
import { useView } from '@wordpress/views';

/**
 * Internal dependencies
 */
import { useNavigate } from '../router';

interface UpdateObjectTypeFilterOptions {
	onComplete?: () => void;
}

interface UseObjectTypeFilterReturn {
	selectedObjectTypeId: number | null;
	updateObjectTypeFilter: ( objectTypeId: number | null, options?: UpdateObjectTypeFilterOptions ) => void;
}

/**
 * Hook to manage object type filtering in the feed view
 *
 * Provides a consistent way to read and update object type filters across components.
 * Uses `view.filters` as the single source of truth.
 *
 * @return {UseObjectTypeFilterReturn} Selected object type ID and update function
 */
export function useObjectTypeFilter(): UseObjectTypeFilterReturn {
	const navigate = useNavigate();
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_post',
		slug: 'feed',
		defaultView: {
			type: 'list',
			filters: [],
		},
	} );

	// Derive selected object type from view.filters
	const selectedObjectTypeId: number | null = useMemo( (): number | null => {
		const objectTypeFilter: Filter = view.filters?.find( ( f: Filter ): boolean => f.field === 'ap_object_type' );
		// With 'is' operator, value is a single number, not an array
		return objectTypeFilter?.value ?? null;
	}, [ view.filters ] );

	// Update object type filter with toggle support
	const updateObjectTypeFilter = useCallback(
		( objectTypeId: number | null, options: UpdateObjectTypeFilterOptions = {} ): void => {
			const currentFilters: Filter[] = view.filters || [];
			const objectTypeFilterIndex: number = currentFilters.findIndex(
				( f: Filter ): boolean => f.field === 'ap_object_type'
			);

			let newFilters: Filter[];

			if ( objectTypeId === null ) {
				// Clear object type filter
				newFilters = currentFilters.filter( ( f: Filter ): boolean => f.field !== 'ap_object_type' );
			} else if ( objectTypeFilterIndex !== -1 ) {
				// Object type filter exists - toggle it
				const currentValue: number = currentFilters[ objectTypeFilterIndex ].value;
				if ( currentValue === objectTypeId ) {
					// Remove the object type filter if it's the same object type
					newFilters = currentFilters.filter( ( f: Filter ): boolean => f.field !== 'ap_object_type' );
				} else {
					// Replace with new object type
					newFilters = [
						...currentFilters.slice( 0, objectTypeFilterIndex ),
						{ field: 'ap_object_type', operator: 'is', value: objectTypeId },
						...currentFilters.slice( objectTypeFilterIndex + 1 ),
					];
				}
			} else {
				// No object type filter exists - add one
				newFilters = [ ...currentFilters, { field: 'ap_object_type', operator: 'is', value: objectTypeId } ];
			}

			// Update the view with new filters
			updateView( {
				...view,
				filters: newFilters,
				page: 1, // Reset to first page
			} );

			// Close inspector by removing postId from URL
			void navigate( {
				search: ( prev: Record< string, unknown > ) => {
					const { postId: _, ...rest } = prev as { postId?: number };
					return rest;
				},
			} );

			// Call completion callback if provided
			if ( options.onComplete ) {
				options.onComplete();
			}
		},
		[ view, updateView, navigate ]
	);

	return { selectedObjectTypeId, updateObjectTypeFilter };
}
