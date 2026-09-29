/**
 * WordPress dependencies
 */
import { useEntityRecords } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import type { FeedPost } from '../types';

export interface FeedQuery extends Record< string, unknown > {
	per_page: number;
	page: number;
	orderby: string;
	order: 'asc' | 'desc';
	search: string;
	_fields: string[];
	user_id?: number;
	ap_object_type?: number[];
	ap_tag?: number | number[] | string | string[];
	before?: string;
	after?: string;
}

interface UseFeedReturn {
	feed: FeedPost[];
	hasResolved: boolean;
	isResolving: boolean;
	totalItems: number | null;
	totalPages: number | null;
}

const EMPTY_FEED: FeedPost[] = [];

export function useFeed( query: FeedQuery ): UseFeedReturn {
	// Don't fetch until the actor is known.
	const enabled: boolean = query.user_id !== undefined;

	const { records, hasResolved, isResolving, totalItems, totalPages } = useEntityRecords< FeedPost >(
		'postType',
		'ap_post',
		query,
		{ enabled }
	);

	return {
		feed: enabled ? records || EMPTY_FEED : EMPTY_FEED,
		hasResolved,
		isResolving,
		totalItems: enabled ? totalItems : null,
		totalPages: enabled ? totalPages : null,
	};
}
