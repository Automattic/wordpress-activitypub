/**
 * Internal dependencies
 */
import type { State, Action } from './types';
import { DEFAULT_STATE } from './types';

/**
 * Store reducer
 */
export function reducer( state = DEFAULT_STATE, _action: Action ): State {
	return state;
}
