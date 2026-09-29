/**
 * Utility functions for feed view management
 */

/**
 * WordPress dependencies
 */
import { useView } from '@wordpress/views';

/**
 * Internal dependencies
 */
import type { FeedQuery } from '../../hooks/use-feed';

// Using ReturnType to get the View type from useView to avoid version conflicts
// between @wordpress/views and @wordpress/dataviews
export type ViewType = ReturnType< typeof useView >[ 'view' ];

export const DEFAULT_VIEW: ViewType = {
	type: 'list',
	perPage: 20,
	page: 1,
	sort: {
		field: 'date',
		direction: 'desc',
	},
	search: '',
	filters: [],
	fields: [ 'metadata', 'title.rendered', 'content' ],
	infiniteScrollEnabled: true,
	startPosition: 1,
};

export const defaultLayouts = {
	list: {
		primaryField: 'metadata',
		fields: [ 'metadata', 'title.rendered', 'content' ],
		mediaField: undefined,
	},
};

// The fields the list and the inspector read; everything else stays on the server.
const FIELDS: string[] = [
	'id',
	'date',
	'modified',
	'title',
	'excerpt',
	'content',
	'actor_info',
	'status',
	'link',
	'ap_object_type',
	'ap_tag',
];

/**
 * Turns a view into the REST query the feed is fetched with.
 *
 * Shared by the stage and the route loader so both ask for the same records.
 *
 * @param view   The resolved view.
 * @param userId The actor whose feed it is.
 * @return The query for `getEntityRecords( 'postType', 'ap_post', … )`.
 */
export function viewToQuery( view: ViewType, userId: number | null | undefined ): FeedQuery {
	const query: FeedQuery = {
		per_page: view.perPage || 20,
		page: view.page || 1,
		orderby: view.sort?.field || 'date',
		order: view.sort?.direction || 'desc',
		search: view.search || '',
		_fields: FIELDS,
	};

	if ( userId !== null && userId !== undefined ) {
		query.user_id = userId;
	}

	const objectType = view.filters?.find( ( filter ) => filter.field === 'ap_object_type' );
	if ( objectType?.value !== undefined ) {
		// The REST API takes a list of object types.
		query.ap_object_type = Array.isArray( objectType.value ) ? objectType.value : [ objectType.value ];
	}

	const tag = view.filters?.find( ( filter ) => filter.field === 'ap_tag' );
	if ( tag?.value !== undefined ) {
		query.ap_tag = tag.value;
	}

	const date = view.filters?.find( ( filter ) => filter.field === 'date' );
	if ( date?.value ) {
		if ( date.operator === 'before' ) {
			query.before = date.value;
		} else if ( date.operator === 'after' ) {
			query.after = date.value;
		}
	}

	return query;
}

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
