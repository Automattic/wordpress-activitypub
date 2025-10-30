/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useMemo } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../store';
import type { Follower, Following, Interaction } from '../types';

interface SocialWebData {
	following: Following[];
	interactions: Interaction[];
	stats: {
		following: number;
		interactions: number;
		posts: number;
	};
	isLoading: {
		following: boolean;
		interactions: boolean;
	};
}

interface SocialWebActions {
	fetchFollowing: () => void;
	fetchInteractions: () => void;
}

/**
 * Hook to access Social Web data and actions (full version - internal)
 */
function useSocialWebDataFull(): SocialWebData & SocialWebActions {
	const following = useSelect( ( select ) => {
		const store = select( STORE_NAME ) as any;
		return store.getFollowing() as Following[];
	}, [] );

	const interactions = useSelect( ( select ) => {
		const store = select( STORE_NAME ) as any;
		return store.getInteractions() as Interaction[];
	}, [] );

	const stats = useSelect( ( select ) => {
		const store = select( STORE_NAME ) as any;
		return store.getStats() as {
			following: number;
			interactions: number;
			posts: number;
		};
	}, [] );

	const isLoadingFollowing = useSelect( ( select ) => {
		const store = select( STORE_NAME ) as any;
		return store.isLoading( 'following' ) as boolean;
	}, [] );

	const isLoadingInteractions = useSelect( ( select ) => {
		const store = select( STORE_NAME ) as any;
		return store.isLoading( 'interactions' ) as boolean;
	}, [] );

	const { fetchFollowing, fetchInteractions } = useDispatch( STORE_NAME ) as any;

	// Fetch initial data
	useEffect( () => {
		//	fetchFollowing();
		//	fetchInteractions();
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
	};
}

/**
 * Hook to access Social Web data with optional resource filtering
 */
export function useSocialWebData(
	resource?: 'following' | 'interactions',
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
				if ( resource === 'following' ) {
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
