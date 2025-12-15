/**
 * WordPress dependencies
 */
import { useEntityRecords } from '@wordpress/core-data';
import { useMemo } from '@wordpress/element';

/**
 * Internal dependencies
 */
import type { FeedPost } from '../types';

interface Filter {
	field: string;
	operator: string;
	value: number | number[] | string | string[];
}

interface UseFeedParams {
	perPage?: number;
	page?: number;
	orderBy?: string;
	order?: 'asc' | 'desc';
	search?: string;
	userId?: number;
	fields?: string[];
	filters?: Filter[];
}

interface UseFeedReturn {
	feed: FeedPost[];
	hasResolved: boolean;
	isResolving: boolean;
	totalItems: number | null;
	totalPages: number | null;
}

// Stable default values to prevent unnecessary re-renders
const DEFAULT_FIELDS: string[] = [
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
const DEFAULT_FILTERS: Filter[] = [];
const EMPTY_FEED: FeedPost[] = [];

export function useFeed( {
	perPage = 20,
	page = 1,
	orderBy = 'date',
	order = 'desc',
	search = '',
	userId,
	fields = DEFAULT_FIELDS,
	filters = DEFAULT_FILTERS,
}: UseFeedParams = {} ): UseFeedReturn {
	// Don't fetch if userId is not set
	const enabled: boolean = userId !== null && userId !== undefined;

	interface QueryArgs extends Record< string, unknown > {
		per_page: number;
		page: number;
		orderby: string;
		order: 'asc' | 'desc';
		search: string;
		_fields: string[];
		user_id?: number;
		ap_object_type?: number[];
		ap_tag?: number | number[] | string | string[];
	}

	const queryArgs: QueryArgs = useMemo( (): QueryArgs => {
		const args: QueryArgs = {
			per_page: perPage,
			page,
			orderby: orderBy,
			order,
			search,
			_fields: fields,
		};

		// Only add user_id if we have a valid userId
		if ( enabled ) {
			args.user_id = userId;
		}

		// Extract ap_object_type filter from filters array
		const apObjectTypeFilter: Filter | undefined = filters.find(
			( f: Filter ): boolean => f.field === 'ap_object_type'
		);
		if ( apObjectTypeFilter?.value !== undefined ) {
			// Wrap single value in array for REST API
			args.ap_object_type = Array.isArray( apObjectTypeFilter.value )
				? ( apObjectTypeFilter.value as number[] )
				: [ apObjectTypeFilter.value as number ];
		}

		// Extract ap_tag filter from filters array
		const apTagFilter: Filter | undefined = filters.find( ( f: Filter ): boolean => f.field === 'ap_tag' );
		if ( apTagFilter?.value !== undefined ) {
			args.ap_tag = apTagFilter.value;
		}

		return args;
	}, [ perPage, page, orderBy, order, search, userId, fields, enabled, filters ] );

	const { records, hasResolved, isResolving, totalItems, totalPages } = useEntityRecords< FeedPost >(
		'postType',
		'ap_post',
		enabled ? queryArgs : undefined
	);

	return {
		feed: enabled ? records || EMPTY_FEED : EMPTY_FEED,
		hasResolved,
		isResolving,
		totalItems: enabled ? totalItems : null,
		totalPages: enabled ? totalPages : null,
	};
}
