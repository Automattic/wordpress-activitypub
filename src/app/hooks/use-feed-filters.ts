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
const DEFAULT_VIEW = {
	type: 'list' as const,
	filters: [],
};

export function useFeedFilters(): UseFeedFiltersReturn {
	const { view, updateView } = useView( {
		kind: 'postType',
		name: 'ap_post',
		slug: 'feed',
		defaultView: DEFAULT_VIEW,
	} );

	// Guard against undefined view
	const safeView = view ?? DEFAULT_VIEW;

	// Check if any filters are active
	const hasActiveFilters: boolean = useMemo( (): boolean => {
		return ( safeView.filters?.length ?? 0 ) > 0;
	}, [ safeView.filters ] );

	// Clear all filters
	const clearAllFilters = useCallback( (): void => {
		updateView( {
			...safeView,
			filters: [],
			page: 1, // Reset to first page
		} );
	}, [ safeView, updateView ] );

	return { hasActiveFilters, clearAllFilters };
}
