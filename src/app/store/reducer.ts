/**
 * Internal dependencies
 */
import type { State, Action } from './types';
import { DEFAULT_STATE, SET_ACTIVE_ACTOR } from './types';

/**
 * Store reducer
 *
 * @param state  Current state or default if undefined.
 * @param action Action to process.
 * @return       Updated state.
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
