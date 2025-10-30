/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../store';
import type { Follower, Following, Interaction } from '../types';

interface SocialWebData {
	followers: Follower[];
	following: Following[];
	interactions: Interaction[];
	stats: {
		followers: number;
		following: number;
		interactions: number;
		posts: number;
	};
	isLoading: {
		followers: boolean;
		following: boolean;
		interactions: boolean;
	};
}

interface SocialWebActions {
	fetchFollowers: () => void;
	fetchFollowing: () => void;
	fetchInteractions: () => void;
	blockFollower: ( id: string ) => void;
	removeFollower: ( id: string ) => void;
}

/**
 * Hook to access Social Web data and actions (full version - internal)
 */
function useSocialWebDataFull(): SocialWebData & SocialWebActions {
	const data = useSelect( ( select ) => {
		const store = select( STORE_NAME ) as any;
		return {
			followers: store.getFollowers() as Follower[],
			following: store.getFollowing() as Following[],
			interactions: store.getInteractions() as Interaction[],
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
			},
		};
	}, [] );

	const { fetchFollowers, fetchFollowing, fetchInteractions, blockFollower, removeFollower } = useDispatch(
		STORE_NAME
	) as any;

	// Fetch initial data
	useEffect( () => {
		fetchFollowers();
		fetchFollowing();
		fetchInteractions();
	}, [] );

	return {
		...data,
		fetchFollowers,
		fetchFollowing,
		fetchInteractions,
		blockFollower,
		removeFollower,
	};
}

/**
 * Hook to access Social Web data with optional resource filtering
 */
export function useSocialWebData(
	resource?: 'followers' | 'following' | 'interactions',
	id?: string
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

	if ( id ) {
		// Return single item
		const item = useSelect(
			( select ) => {
				const store = select( STORE_NAME ) as any;
				if ( resource === 'followers' ) {
					return store.getFollowerById( id ) as Follower | undefined;
				} else if ( resource === 'following' ) {
					return store.getFollowingById( id ) as Following | undefined;
				} else if ( resource === 'interactions' ) {
					return store.getInteractionById( id ) as Interaction | undefined;
				}
				return null;
			},
			[ resource, id ]
		);

		return {
			items: item,
			isLoading: allData.isLoading[ resource ],
		};
	}

	// Return list of items for the resource
	return {
		items: allData[ resource ],
		isLoading: allData.isLoading[ resource ],
	};
}

/**
 * Hook to get a specific follower by ID
 */
export function useFollower( id: string ): Follower | undefined {
	return useSelect(
		( select ) => {
			const store = select( STORE_NAME ) as any;
			return store.getFollowerById( id ) as Follower | undefined;
		},
		[ id ]
	);
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
