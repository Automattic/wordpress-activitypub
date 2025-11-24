/**
 * WordPress dependencies
 */
import { Filter } from '@wordpress/dataviews';
import { useMemo, useCallback } from '@wordpress/element';
import { useView } from '@wordpress/views';

interface UpdateTagFilterOptions {
	onComplete?: () => void;
}

interface UseTagFilterReturn {
	selectedTagId: number | null;
	updateTagFilter: ( tagId: number | null, options?: UpdateTagFilterOptions ) => void;
}

/**
 * Hook to manage tag filtering in the feed view
 *
 * Provides a consistent way to read and update tag filters across components.
 * Uses `view.filters` as the single source of truth.
 *
 * @return {UseTagFilterReturn} Selected tag ID and update function
 */
export function useTagFilter(): UseTagFilterReturn {
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_post',
		slug: 'feed',
		defaultView: {
			type: 'list',
			filters: [],
		},
	} );

	// Derive selected tag from view.filters
	const selectedTagId: number = useMemo( (): number => {
		const tagFilter: Filter = view.filters?.find( ( f: Filter ): boolean => f.field === 'ap_tag' );
		return tagFilter?.value?.[ 0 ] ?? null;
	}, [ view.filters ] );

	// Update tag filter with toggle support
	const updateTagFilter = useCallback(
		( tagId: number | null, options: UpdateTagFilterOptions = {} ): void => {
			const currentFilters: Filter[] = view.filters || [];
			const tagFilterIndex: number = currentFilters.findIndex( ( f: Filter ): boolean => f.field === 'ap_tag' );

			let newFilters: Filter[];

			if ( tagId === null ) {
				// Clear tag filter
				newFilters = currentFilters.filter( ( f: Filter ): boolean => f.field !== 'ap_tag' );
			} else if ( tagFilterIndex !== -1 ) {
				// Tag filter exists - toggle it
				const currentValue: number = currentFilters[ tagFilterIndex ].value;
				if ( Array.isArray( currentValue ) && currentValue.includes( tagId ) ) {
					// Remove the tag filter if it's the same tag
					newFilters = currentFilters.filter( ( f: Filter ): boolean => f.field !== 'ap_tag' );
				} else {
					// Replace with new tag
					newFilters = [
						...currentFilters.slice( 0, tagFilterIndex ),
						{ field: 'ap_tag', operator: 'isAny', value: [ tagId ] },
						...currentFilters.slice( tagFilterIndex + 1 ),
					];
				}
			} else {
				// No tag filter exists - add one
				newFilters = [ ...currentFilters, { field: 'ap_tag', operator: 'isAny', value: [ tagId ] } ];
			}

			// Update the view with new filters
			updateView( {
				...view,
				filters: newFilters,
				page: 1, // Reset to first page
			} );

			// Call completion callback if provided
			if ( options.onComplete ) {
				options.onComplete();
			}
		},
		[ view, updateView ]
	);

	return { selectedTagId, updateTagFilter };
}
