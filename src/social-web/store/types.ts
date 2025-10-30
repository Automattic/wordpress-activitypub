/**
 * Internal dependencies
 */
import type { Follower, Following, Interaction } from '../types';

/**
 * Store state interface
 */
export interface State {
	followers: Follower[];
	following: Following[];
	interactions: Interaction[];
	isLoading: {
		followers: boolean;
		following: boolean;
		interactions: boolean;
	};
}

/**
 * Action Types
 */
export type SetFollowersAction = {
	type: 'SET_FOLLOWERS';
	followers: Follower[];
};

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

export type Action = SetFollowersAction | SetFollowingAction | SetInteractionsAction | SetLoadingAction;

/**
 * Initial state
 */
export const DEFAULT_STATE: State = {
	followers: [],
	following: [],
	interactions: [],
	isLoading: {
		followers: false,
		following: false,
		interactions: false,
	},
};
