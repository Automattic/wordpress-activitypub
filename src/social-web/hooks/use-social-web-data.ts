/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../store';
import type { Following, Interaction, FeedPost } from '../types';

interface SocialWebData {
	following: Following[];
	interactions: Interaction[];
	feed: FeedPost[];
	stats: {
		following: number;
		interactions: number;
		posts: number;
	};
	isLoading: {
		following: boolean;
		interactions: boolean;
		feed: boolean;
	};
}

interface SocialWebActions {
	fetchFollowing: () => void;
	fetchInteractions: () => void;
	fetchFeed: () => void;
}

/**
 * Hook to access Social Web data and actions (full version - internal)
 */
function useSocialWebDataFull(): SocialWebData & SocialWebActions {
	const data = useSelect( ( select ) => {
		const store = select( STORE_NAME ) as any;
		return {
			following: store.getFollowing() as Following[],
			interactions: store.getInteractions() as Interaction[],
			feed: store.getFeed() as FeedPost[],
			stats: store.getStats() as {
				following: number;
				interactions: number;
				posts: number;
			},
			isLoading: {
				following: store.isLoading( 'following' ) as boolean,
				interactions: store.isLoading( 'interactions' ) as boolean,
				feed: store.isLoading( 'feed' ) as boolean,
			},
		};
	}, [] );

	const { fetchFollowing, fetchInteractions, fetchFeed } = useDispatch( STORE_NAME ) as any;

	// Fetch initial data
	useEffect( () => {
		fetchFollowing();
		fetchInteractions();
		fetchFeed();
	}, [ fetchFollowing, fetchInteractions, fetchFeed ] );

	return {
		...data,
		fetchFollowing,
		fetchInteractions,
		fetchFeed,
	};
}

/**
 * Hook to access Social Web data with optional resource filtering
 *
 * @param {string}        resource Resource type (followers, following, interactions, or feed)
 * @param {string|number} id       Optional item ID
 * @return {Object} Items and loading state
 */
export function useSocialWebData(
	resource?: 'followers' | 'following' | 'interactions' | 'feed',
	id?: string | number
): {
	items: any;
	isLoading: boolean;
} {
	const allData = useSocialWebDataFull();

	// Always call useSelect (Hooks must be called unconditionally)
	const item = useSelect(
		( select ) => {
			if ( ! resource || id === undefined ) {
				return null;
			}
			const store = select( STORE_NAME ) as any;
			if ( resource === 'following' ) {
				return store.getFollowingById( id ) as Following | undefined;
			} else if ( resource === 'interactions' ) {
				return store.getInteractionById( id ) as Interaction | undefined;
			} else if ( resource === 'feed' ) {
				return store.getFeedPostById( id ) as FeedPost | undefined;
			}
			return null;
		},
		[ resource, id ]
	);

	if ( ! resource ) {
		// Return all data if no resource specified
		return {
			items: allData,
			isLoading: false,
		};
	}

	if ( id !== undefined ) {
		// Return single item
		return {
			items: item,
			isLoading: allData?.isLoading?.[ resource ] || false,
		};
	}

	// Return list of items for the resource
	return {
		items: allData?.[ resource ] || [],
		isLoading: allData?.isLoading?.[ resource ] || false,
	};
}

/**
 * Hook to get a specific following by ID
 */
export function useFollowing( id: string ): Following | undefined {
	return useSelect(
		( select ) => {
			const store = select( STORE_NAME ) as any;
			return store.getFollowingById( id ) as Following | undefined;
		},
		[ id ]
	);
}

/**
 * Hook to get a specific interaction by ID
 */
export function useInteraction( id: string ): Interaction | undefined {
	return useSelect(
		( select ) => {
			const store = select( STORE_NAME ) as any;
			return store.getInteractionById( id ) as Interaction | undefined;
		},
		[ id ]
	);
}
