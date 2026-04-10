/**
 * WordPress dependencies
 */
import { createRegistrySelector } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import type { State } from './types';

/**
 * Store selectors
 */
export const selectors = {
	/**
	 * Get the active actor ID, falling back to current user if not set.
	 */
	getActiveActorId: createRegistrySelector( ( select ) => ( state: State ): number | null => {
		if ( state.activeActorId !== null ) {
			return state.activeActorId;
		}

		// Fall back to current user ID if no actor is set
		const currentUser = select( coreStore ).getCurrentUser();
		return currentUser?.id ?? null;
	} ),
};
