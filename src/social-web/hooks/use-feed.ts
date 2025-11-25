import { useEntityRecords } from '@wordpress/core-data';
import { useMemo } from '@wordpress/element';
import type { FeedPost } from '../types';

interface Filter {
	field: string;
	operator: string;
	value: any;
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

export function useFeed( {
	perPage = 20,
	page = 1,
	orderBy = 'date',
	order = 'desc',
	search = '',
	userId,
	fields = [
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
	],
	filters = [],
}: UseFeedParams = {} ): UseFeedReturn {
	// Don't fetch if userId is not set
	const enabled = userId !== null && userId !== undefined;

	const queryArgs = useMemo( () => {
		const args: any = {
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
		const apObjectTypeFilter = filters.find( ( f ) => f.field === 'ap_object_type' );
		if ( apObjectTypeFilter?.value !== undefined ) {
			// Wrap single value in array for REST API
			args.ap_object_type = Array.isArray( apObjectTypeFilter.value )
				? apObjectTypeFilter.value
				: [ apObjectTypeFilter.value ];
		}

		return args;
	}, [ perPage, page, orderBy, order, search, userId, fields, enabled, filters ] );

	const { records, hasResolved, isResolving, totalItems, totalPages } = useEntityRecords< FeedPost >(
		'postType',
		'ap_post',
		enabled ? queryArgs : undefined
	);

	return {
		feed: enabled ? records || [] : [],
		hasResolved,
		isResolving,
		totalItems: enabled ? totalItems : null,
		totalPages: enabled ? totalPages : null,
	};
}
