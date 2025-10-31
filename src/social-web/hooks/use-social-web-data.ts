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
		// Dependencies are dispatch functions from WordPress data store
		// which are stable references and safe to omit per WordPress patterns
		// eslint-disable-next-line react-hooks/exhaustive-deps
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
 *
 * @param resource - Optional resource type to filter ('followers' | 'following' | 'interactions')
 * @param id       - Optional ID to fetch a specific item
 * @return Object containing items and loading state
 */
export function useSocialWebData(
	resource?: 'followers' | 'following' | 'interactions',
	id?: string
): {
	items: any;
	isLoading: boolean;
} {
	const allData = useSocialWebDataFull();

	// Always call useSelect to comply with Rules of Hooks
	const item = useSelect(
		( select ) => {
			if ( ! id || ! resource ) {
				return null;
			}
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

	if ( ! resource ) {
		// Return all data if no resource specified
		return {
			items: allData,
			isLoading: false,
		};
	}

	if ( id ) {
		// Return single item
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
 *
 * @param id - The follower ID
 * @return The follower object or undefined if not found
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
 *
 * @param id - The following ID
 * @return The following object or undefined if not found
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
 *
 * @param id - The interaction ID
 * @return The interaction object or undefined if not found
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
