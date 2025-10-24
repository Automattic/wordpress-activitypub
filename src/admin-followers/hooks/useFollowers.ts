/**
 * Custom hook for fetching followers using WordPress entity records.
 */

import { useEntityRecords } from '@wordpress/core-data';
import { useMemo } from '@wordpress/element';
import type { APActor } from '../types';

interface UseFollowersOptions {
	userId: number;
	perPage?: number;
	page?: number;
	orderBy?: string;
	order?: 'asc' | 'desc';
	search?: string;
}

interface UseFollowersReturn {
	followers: APActor[];
	hasResolved: boolean;
	isResolving: boolean;
	totalItems: number;
	totalPages: number;
}

/**
 * Custom hook to fetch followers for a specific user.
 *
 * @param {UseFollowersOptions} options - Options for fetching followers.
 * @return {UseFollowersReturn} Followers data and status.
 */
export function useFollowers( {
	userId,
	perPage = 20,
	page = 1,
	orderBy = 'modified',
	order = 'desc',
	search = '',
}: UseFollowersOptions ): UseFollowersReturn {
	// Build query arguments with follower_of parameter for server-side filtering.
	const queryArgs = useMemo( () => {
		const args: Record< string, any > = {
			per_page: perPage,
			page,
			orderby: orderBy,
			order,
			follower_of: userId, // Custom parameter for server-side filtering.
			_fields: [
				'id',
				'date',
				'date_gmt',
				'modified',
				'modified_gmt',
				'slug',
				'status',
				'title',
				'meta',
				'actor_info',
				'follow_status',
			],
		};

		if ( search ) {
			args.search = search;
		}

		return args;
	}, [ userId, perPage, page, orderBy, order, search ] );

	// Fetch followers using the custom follower_of parameter.
	const { records, hasResolved, isResolving, totalItems, totalPages } = useEntityRecords(
		'postType',
		'ap_actor',
		queryArgs
	);

	return {
		followers: ( records as APActor[] ) ?? [],
		hasResolved: hasResolved ?? false,
		isResolving: isResolving ?? false,
		totalItems: totalItems ?? 0,
		totalPages: totalPages ?? 0,
	};
}
