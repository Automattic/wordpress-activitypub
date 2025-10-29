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
 * Hook to access Social Web data and actions
 */
export function useSocialWebData(): SocialWebData & SocialWebActions {
	const data = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			followers: store.getFollowers(),
			following: store.getFollowing(),
			interactions: store.getInteractions(),
			stats: store.getStats(),
			isLoading: {
				followers: store.isLoading( 'followers' ),
				following: store.isLoading( 'following' ),
				interactions: store.isLoading( 'interactions' ),
			},
		};
	}, [] );

	const { fetchFollowers, fetchFollowing, fetchInteractions, blockFollower, removeFollower } =
		useDispatch( STORE_NAME );

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
 * Hook to get a specific follower by ID
 */
export function useFollower( id: string ): Follower | undefined {
	return useSelect(
		( select ) => {
			const store = select( STORE_NAME );
			return store.getFollowerById( id );
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
			const store = select( STORE_NAME );
			return store.getFollowingById( id );
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
			const store = select( STORE_NAME );
			return store.getInteractionById( id );
		},
		[ id ]
	);
}
