/**
 * Internal dependencies
 */
import type { State, Action } from './types';
import { DEFAULT_STATE, SET_ACTIVE_ACTOR, SET_SELECTED_TAG } from './types';

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
		case SET_SELECTED_TAG:
			return {
				...state,
				selectedTagId: action.tagId,
			};
		default:
			return state;
	}
}
