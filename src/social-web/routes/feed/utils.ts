/**
 * Utility functions for feed view management
 */

import type { View } from '@wordpress/dataviews';

/**
 * Normalizes view fields to maintain canonical order.
 * Sorts the visible fields according to the order defined in the fields array.
 *
 * @param view   - The current view configuration
 * @param fields - Array of field objects with their canonical order
 * @return The view with fields sorted in canonical order
 */
export function normalizeFieldOrder< T >( view: View, fields: Array< { id: string } > ): View {
	if ( ! view.fields ) {
		return view;
	}

	// Create a map of field IDs to their canonical order
	const fieldOrder = new Map( fields.map( ( field, index ) => [ field.id, index ] ) );

	// Sort view.fields according to the canonical order
	const sortedFields = [ ...view.fields ].sort( ( a, b ) => {
		const orderA = fieldOrder.get( a ) ?? Infinity;
		const orderB = fieldOrder.get( b ) ?? Infinity;
		return orderA - orderB;
	} );

	return {
		...view,
		fields: sortedFields,
	};
}
