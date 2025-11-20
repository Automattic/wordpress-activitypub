import { useEntityRecords } from '@wordpress/core-data';
import { useMemo } from '@wordpress/element';
import type { FeedPost } from '../types';

interface UseFeedParams {
	perPage?: number;
	page?: number;
	orderBy?: string;
	order?: 'asc' | 'desc';
	search?: string;
	userId?: number;
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
		'featured_image',
	],
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

		return args;
	}, [ perPage, page, orderBy, order, search, userId, fields, enabled ] );

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
