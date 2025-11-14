/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useMemo } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../store';
import type { Follower, Following, Interaction, FeedPost } from '../types';

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
	blockFollower: ( id: string ) => void;
	removeFollower: ( id: string ) => void;
}

/**
 * Hook to access Social Web data and actions (full version - internal)
 */
function useSocialWebDataFull(): SocialWebData & SocialWebActions {
	const following = useSelect( ( select ) => {
		const store = select( STORE_NAME ) as any;
		return {
			followers: store.getFollowers() as Follower[],
			following: store.getFollowing() as Following[],
			interactions: store.getInteractions() as Interaction[],
			feed: store.getFeed() as FeedPost[],
			stats: store.getStats() as {
				followers: number;
				following: number;
				interactions: number;
				posts: number;
			},
			isLoading: {
				followers: store.isLoading( 'followers' ) as boolean,
				following: store.isLoading( 'following' ) as boolean,
				interactions: store.isLoading( 'interactions' ) as boolean,
				feed: store.isLoading( 'feed' ) as boolean,
			},
		};
	}, [] );

	const { fetchFollowers, fetchFollowing, fetchInteractions, fetchFeed, blockFollower, removeFollower } = useDispatch(
		STORE_NAME
	) as any;

	// Fetch initial data
	useEffect( () => {
		fetchFollowers();
		fetchFollowing();
		fetchInteractions();
		fetchFeed();
	}, [] );

	// Memoize the isLoading object to prevent re-renders
	const isLoading = useMemo(
		() => ( {
			following: isLoadingFollowing,
			interactions: isLoadingInteractions,
		} ),
		[ isLoadingFollowing, isLoadingInteractions ]
	);

	return {
		following,
		interactions,
		stats,
		isLoading,
		fetchFollowing,
		fetchInteractions,
		fetchFeed,
		blockFollower,
		removeFollower,
	};
}

/**
 * Hook to access Social Web data with optional resource filtering
 */
export function useSocialWebData(
	resource?: 'followers' | 'following' | 'interactions' | 'feed',
	id?: string | number
): {
	items: any;
	isLoading: boolean;
} {
	const allData = useSocialWebDataFull();

	if ( ! resource ) {
		// Return all data if no resource specified
		return {
			items: allData,
			isLoading: false,
		};
	}

	if ( id !== undefined ) {
		// Return single item
		const item = useSelect(
			( select ) => {
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
