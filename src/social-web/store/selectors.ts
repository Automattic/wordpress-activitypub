/**
 * Internal dependencies
 */
import type { State } from './types';

/**
 * Store selectors
 */
export const selectors = {
	getActiveActorId( state: State ): number | null {
		return state.activeActorId;
	},
};
