/**
 * Internal dependencies
 */
import type { State, Action } from './types';
import { DEFAULT_STATE, SET_ACTIVE_ACTOR } from './types';

/**
 * Store reducer
 */
export function reducer( state = DEFAULT_STATE, action: Action ): State {
	switch ( action.type ) {
		case SET_ACTIVE_ACTOR:
			return {
				...state,
				activeActorId: action.actorId,
			};
		default:
			return state;
	}
}
