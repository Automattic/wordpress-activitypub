/**
 * WordPress dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../store';
import type { Following, Interaction } from '../types';

interface SocialWebData {
	following: Following[];
	interactions: Interaction[];
	stats: {
		following: number;
		interactions: number;
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
	const data = useSelect( ( select ) => {
		const store = select( STORE_NAME ) as any;
		return {
			following: store.getFollowing() as Following[],
			interactions: store.getInteractions() as Interaction[],
			stats: store.getStats() as {
				following: number;
				interactions: number;
			},
			isLoading: {
				following: store.isLoading( 'following' ) as boolean,
				interactions: store.isLoading( 'interactions' ) as boolean,
			},
		};
	}, [] );

	const { fetchFollowing, fetchInteractions } = useDispatch( STORE_NAME ) as any;

	// Fetch initial data
	useEffect( () => {
		fetchFollowing();
		fetchInteractions();
	}, [ fetchFollowing, fetchInteractions ] );

	return {
		...data,
		fetchFollowing,
		fetchInteractions,
	};
}

/**
 * Hook to access Social Web data with optional resource filtering
 *
 * @param {string}        resource Resource type (followers, following, or interactions)
 * @param {string|number} id       Optional item ID
 * @return {Object} Items and loading state
 */
export function useSocialWebData(
	resource?: 'followers' | 'following' | 'interactions',
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
