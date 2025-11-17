/**
 * Internal dependencies
 */
import type { State, Action } from './types';
import { DEFAULT_STATE } from './types';

/**
 * Store reducer
 */
export function reducer( state = DEFAULT_STATE, action: Action ): State {
	switch ( action.type ) {
		case 'SET_FOLLOWING':
			return {
				...state,
				following: action.following,
			};

		case 'SET_INTERACTIONS':
			return {
				...state,
				interactions: action.interactions,
			};

		case 'SET_LOADING':
			return {
				...state,
				isLoading: {
					...state.isLoading,
					[ action.resource ]: action.isLoading,
				},
			};

		default:
			return state;
	}
}
