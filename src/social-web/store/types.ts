/**
 * Internal dependencies
 */
import type { Follower, Following, Interaction, FeedPost } from '../types';

/**
 * Store state interface
 */
export interface State {
	feed: FeedPost[];
	followers: Follower[];
	following: Following[];
	interactions: Interaction[];
	isLoading: {
		feed: boolean;
		followers: boolean;
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

export type Action = SetFollowersAction | SetFollowingAction | SetInteractionsAction | SetFeedAction | SetLoadingAction;

/**
 * Initial state
 */
export const DEFAULT_STATE: State = {
	followers: [],
	following: [],
	interactions: [],
	feed: [],
	isLoading: {
		followers: false,
		following: false,
		interactions: false,
		feed: false,
	},
};
