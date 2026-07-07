/**
 * Utility functions for feed view management
 */

/**
 * WordPress dependencies
 */
import { useView } from '@wordpress/views';

// Using ReturnType to get the View type from useView to avoid version conflicts
// between @wordpress/views and @wordpress/dataviews
type ViewType = ReturnType< typeof useView >[ 'view' ];

/**
 * Gets the next feed view state after a DataViews update.
 *
 * @param currentView The current view configuration.
 * @param updatedView The requested view update.
 * @return The normalized view update.
 */
export function getFeedViewUpdate( currentView: ViewType, updatedView: ViewType ): ViewType {
	const filtersChanged: boolean = JSON.stringify( currentView.filters ) !== JSON.stringify( updatedView.filters );
	const searchChanged: boolean = currentView.search !== updatedView.search;
	const perPage: number = updatedView.perPage || 20;
	let page: number = updatedView.page ?? 1;

	if ( filtersChanged || searchChanged ) {
		page = 1;
	} else if (
		typeof updatedView.startPosition === 'number' &&
		updatedView.startPosition !== currentView.startPosition
	) {
		// DataViews 14 advances startPosition as the user scrolls;
		// map it to the next page we need to fetch.
		const targetPage: number = Math.max( 1, Math.ceil( updatedView.startPosition / perPage ) );
		page = Math.max( page, targetPage );
	}

	return {
		...updatedView,
		page,
		startPosition: page === 1 ? 1 : updatedView.startPosition,
	};
}

/**
 * Normalizes view fields to maintain canonical order.
 * Sorts the visible fields according to the order defined in the fields array.
 *
 * @param view   The current view configuration
 * @param fields Array of field objects with their canonical order
 * @return The view with fields sorted in canonical order
 */
export function normalizeFieldOrder( view: ViewType, fields: Array< { id: string } > ): ViewType {
	if ( ! view.fields ) {
		return view;
	}

	// Create a map of field IDs to their canonical order
	const fieldOrder: Map< string, number > = new Map(
		fields.map( ( field: { id: string }, index: number ): [ string, number ] => [ field.id, index ] )
	);

	// Sort view.fields according to the canonical order
	const sortedFields: string[] = [ ...view.fields ].sort( ( a: string, b: string ): number => {
		const orderA: number = fieldOrder.get( a ) ?? Infinity;
		const orderB: number = fieldOrder.get( b ) ?? Infinity;
		return orderA - orderB;
	} );

	return {
		...view,
		fields: sortedFields,
	};
}
