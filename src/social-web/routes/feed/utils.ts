/**
 * Utility functions for feed view management
 */

import type { View } from '@wordpress/dataviews';

/**
 * Enforces mutual exclusion between excerpt and content fields.
 * Ensures that only one of these fields is visible at a time, and at least one is always selected.
 *
 * @param oldFields - The previous array of visible field IDs
 * @param newFields - The new array of visible field IDs
 * @return The adjusted array of field IDs with enforcement applied
 */
export function enforceContentExcerptMutualExclusion( oldFields: string[], newFields: string[] ): string[] {
	// Check if content was just added
	const contentAdded = newFields.includes( 'content' ) && ! oldFields.includes( 'content' );
	// Check if excerpt was just added
	const excerptAdded = newFields.includes( 'excerpt.rendered' ) && ! oldFields.includes( 'excerpt.rendered' );
	// Check if content was just removed
	const contentRemoved = ! newFields.includes( 'content' ) && oldFields.includes( 'content' );
	// Check if excerpt was just removed
	const excerptRemoved = ! newFields.includes( 'excerpt.rendered' ) && oldFields.includes( 'excerpt.rendered' );

	if ( contentAdded ) {
		// Remove excerpt when content is added
		return newFields.filter( ( field ) => field !== 'excerpt.rendered' );
	}

	if ( excerptAdded ) {
		// Remove content when excerpt is added
		return newFields.filter( ( field ) => field !== 'content' );
	}

	if ( contentRemoved && ! newFields.includes( 'excerpt.rendered' ) ) {
		// Add excerpt when content is removed and excerpt is not already present
		return [ ...newFields, 'excerpt.rendered' ];
	}

	if ( excerptRemoved && ! newFields.includes( 'content' ) ) {
		// Add content when excerpt is removed and content is not already present
		return [ ...newFields, 'content' ];
	}

	return newFields;
}

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
