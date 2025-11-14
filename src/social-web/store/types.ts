/**
 * Internal dependencies
 */
import type { Following, Interaction, FeedPost } from '../types';

/**
 * Store state interface
 */
export interface State {
	feed: FeedPost[];
	following: Following[];
	interactions: Interaction[];
	isLoading: {
		feed: boolean;
		following: boolean;
		interactions: boolean;
	};
}

/**
 * Action Types
 */
export type SetFeedAction = {
	type: 'SET_FEED';
	feed: FeedPost[];
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

export type Action = SetFollowingAction | SetInteractionsAction | SetFeedAction | SetLoadingAction;

/**
 * Initial state
 */
export const DEFAULT_STATE: State = {
	feed: [],
	following: [],
	interactions: [],
	isLoading: {
		feed: false,
		following: false,
		interactions: false,
	},
};
