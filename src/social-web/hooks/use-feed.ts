import { useEntityRecords } from '@wordpress/core-data';
import { useMemo } from '@wordpress/element';
import type { FeedPost } from '../types';

interface UseFeedParams {
	perPage?: number;
	page?: number;
	orderBy?: string;
	order?: 'asc' | 'desc';
	search?: string;
	fields?: string[];
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
	fields = [ 'id', 'date', 'modified', 'title', 'excerpt', 'content', 'actor', 'status', 'link' ],
}: UseFeedParams = {} ): UseFeedReturn {
	const queryArgs = useMemo(
		() => ( {
			per_page: perPage,
			page,
			orderby: orderBy,
			order,
			search,
			_fields: fields,
		} ),
		[ perPage, page, orderBy, order, search, fields ]
	);

	const { records, hasResolved, isResolving, totalItems, totalPages } = useEntityRecords< FeedPost >(
		'postType',
		'ap_post',
		queryArgs
	);

	return {
		feed: records || [],
		hasResolved,
		isResolving,
		totalItems,
		totalPages,
	};
}
