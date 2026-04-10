/**
 * WordPress dependencies
 */
import { useMemo, useCallback } from '@wordpress/element';
import { useView } from '@wordpress/views';

interface UseFeedFiltersReturn {
	hasActiveFilters: boolean;
	clearAllFilters: () => void;
}

/**
 * Hook to manage feed filters
 *
 * Provides utilities to detect if any filters are active and clear them all.
 * Uses `view.filters` as the single source of truth.
 *
 * @return {UseFeedFiltersReturn} Filter status and clear function
 */
export function useFeedFilters(): UseFeedFiltersReturn {
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_post',
		slug: 'feed',
		defaultView: {
			type: 'list',
			filters: [],
		},
	} );

	// Check if any filters are active
	const hasActiveFilters: boolean = useMemo( (): boolean => {
		return ( view.filters?.length ?? 0 ) > 0;
	}, [ view.filters ] );

	// Clear all filters
	const clearAllFilters = useCallback( (): void => {
		updateView( {
			...view,
			filters: [],
			page: 1, // Reset to first page
		} );
	}, [ view, updateView ] );

	return { hasActiveFilters, clearAllFilters };
}
