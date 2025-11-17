/**
 * Internal dependencies
 */
import type { Following, Interaction } from '../types';

/**
 * Store state interface
 */
export interface State {
	following: Following[];
	interactions: Interaction[];
	isLoading: {
		following: boolean;
		interactions: boolean;
	};
}

/**
 * Action Types
 */
export type SetFollowingAction = {
	type: 'SET_FOLLOWING';
	following: Following[];
};

export type SetInteractionsAction = {
	type: 'SET_INTERACTIONS';
	interactions: Interaction[];
};

export type SetLoadingAction = {
	type: 'SET_LOADING';
	resource: keyof State[ 'isLoading' ];
	isLoading: boolean;
};

export type Action = SetFollowingAction | SetInteractionsAction | SetLoadingAction;

/**
 * Initial state
 */
export const DEFAULT_STATE: State = {
	following: [],
	interactions: [],
	isLoading: {
		following: false,
		interactions: false,
	},
};
