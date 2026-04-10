/**
 * Utility functions for feed view management
 */

/**
 * WordPress dependencies
 */
import { useView } from '../../hooks/use-view';

// Using ReturnType to get the View type from useView to avoid version conflicts
// between @wordpress/views and @wordpress/dataviews
type ViewType = ReturnType< typeof useView >[ 'view' ];

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
