/**
 * Hook for fetching actors that a user is following.
 */

/**
 * WordPress dependencies
 */
import { useEntityRecords } from '@wordpress/core-data';
import { useMemo } from '@wordpress/element';

/**
 * Internal dependencies
 */
import type { Actor } from '../types';

interface UseFollowingParams {
	perPage?: number;
	page?: number;
	orderBy?: string;
	order?: 'asc' | 'desc';
	search?: string;
	userId?: number | null;
	fields?: string[];
}

interface UseFollowingReturn {
	following: Actor[];
	hasResolved: boolean;
	isResolving: boolean;
	totalItems: number | null;
	totalPages: number | null;
}

export function useFollowing( {
	perPage = 20,
	page = 1,
	orderBy = 'modified',
	order = 'desc',
	search = '',
	userId,
	fields = [ 'id', 'date', 'modified', 'slug', 'title', 'meta', 'actor_info', 'follow_status' ],
}: UseFollowingParams = {} ): UseFollowingReturn {
	// Don't fetch if userId is not set
	const enabled: boolean = userId !== null && userId !== undefined;

	interface QueryArgs extends Record< string, unknown > {
		per_page: number;
		page: number;
		orderby: string;
		order: 'asc' | 'desc';
		search: string;
		_fields: string[];
		followed_by?: number;
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

		// Only add followed_by if we have a valid userId
		if ( enabled ) {
			args.followed_by = userId as number;
		}

		return args;
	}, [ perPage, page, orderBy, order, search, userId, fields, enabled ] );

	const { records, hasResolved, isResolving, totalItems, totalPages } = useEntityRecords< Actor >(
		'postType',
		'ap_actor',
		enabled ? queryArgs : undefined
	);

	return {
		following: enabled ? records || [] : [],
		hasResolved,
		isResolving,
		totalItems: enabled ? totalItems : null,
		totalPages: enabled ? totalPages : null,
	};
}
