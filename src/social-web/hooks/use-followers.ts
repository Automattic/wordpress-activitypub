/**
 * WordPress dependencies
 */
import { useEntityRecords } from '@wordpress/core-data';
import { useMemo } from '@wordpress/element';

/**
 * Internal dependencies
 */
import type { Actor } from '../types';

interface UseFollowersParams {
	perPage?: number;
	page?: number;
	orderBy?: string;
	order?: 'asc' | 'desc';
	search?: string;
	userId?: number;
	fields?: string[];
}

interface UseFollowersReturn {
	followers: Actor[];
	hasResolved: boolean;
	isResolving: boolean;
	totalItems: number | null;
	totalPages: number | null;
}

export function useFollowers( {
	perPage = 20,
	page = 1,
	orderBy = 'modified',
	order = 'desc',
	search = '',
	userId,
	fields = [ 'id', 'date', 'modified', 'slug', 'title', 'meta', 'actor_info', 'follow_status' ],
}: UseFollowersParams = {} ): UseFollowersReturn {
	const queryArgs = useMemo( () => {
		const args: any = {
			per_page: perPage,
			page,
			orderby: orderBy,
			order,
			search,
			_fields: fields,
		};

		// Only add follower_of if userId is provided
		if ( userId ) {
			args.follower_of = userId;
		}

		return args;
	}, [ perPage, page, orderBy, order, search, userId, fields ] );

	const { records, hasResolved, isResolving, totalItems, totalPages } = useEntityRecords< Actor >(
		'postType',
		'ap_actor',
		queryArgs
	);

	return {
		followers: records || [],
		hasResolved,
		isResolving,
		totalItems,
		totalPages,
	};
}
