/**
 * WordPress dependencies
 */
import { createReduxStore, register } from '@wordpress/data';
import { controls as dataControls } from '@wordpress/data-controls';
import apiFetch from '@wordpress/api-fetch';

/**
 * Custom controls for async operations
 */
const controls = {
	...dataControls,
	API_FETCH( action ) {
		return apiFetch( action.request );
	},
};

/**
 * Internal dependencies
 */
import type { Follower, Following, Interaction, FeedPost } from '../types';

// Store name
export const STORE_NAME = 'activitypub/social-web';

// State interface
interface State {
	followers: Follower[];
	following: Following[];
	interactions: Interaction[];
	feed: FeedPost[];
	isLoading: {
		followers: boolean;
		following: boolean;
		interactions: boolean;
		feed: boolean;
	};
}

// Initial state
const DEFAULT_STATE: State = {
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

// Action Types
type SetFollowersAction = {
	type: 'SET_FOLLOWERS';
	followers: Follower[];
};

type SetFollowingAction = {
	type: 'SET_FOLLOWING';
	following: Following[];
};

type SetInteractionsAction = {
	type: 'SET_INTERACTIONS';
	interactions: Interaction[];
};

type SetFeedAction = {
	type: 'SET_FEED';
	feed: FeedPost[];
};

type SetLoadingAction = {
	type: 'SET_LOADING';
	resource: keyof State[ 'isLoading' ];
	isLoading: boolean;
};

type Action = SetFollowersAction | SetFollowingAction | SetInteractionsAction | SetFeedAction | SetLoadingAction;

// Actions
const actions = {
	setFollowers( followers: Follower[] ): SetFollowersAction {
		return {
			type: 'SET_FOLLOWERS',
			followers,
		};
	},

	setFollowing( following: Following[] ): SetFollowingAction {
		return {
			type: 'SET_FOLLOWING',
			following,
		};
	},

	setInteractions( interactions: Interaction[] ): SetInteractionsAction {
		return {
			type: 'SET_INTERACTIONS',
			interactions,
		};
	},

	setFeed( feed: FeedPost[] ): SetFeedAction {
		return {
			type: 'SET_FEED',
			feed,
		};
	},

	setLoading( resource: keyof State[ 'isLoading' ], isLoading: boolean ): SetLoadingAction {
		return {
			type: 'SET_LOADING',
			resource,
			isLoading,
		};
	},

	*fetchFollowers() {
		yield actions.setLoading( 'followers', true );
		try {
			const followers = yield apiFetch( {
				path: '/activitypub/v1/followers',
			} );
			yield actions.setFollowers( followers as Follower[] );
		} catch ( error ) {
			console.error( 'Failed to fetch followers:', error );
		} finally {
			yield actions.setLoading( 'followers', false );
		}
	},

	*fetchFollowing() {
		yield actions.setLoading( 'following', true );
		try {
			const following = yield apiFetch( {
				path: '/activitypub/v1/following',
			} );
			yield actions.setFollowing( following as Following[] );
		} catch ( error ) {
			console.error( 'Failed to fetch following:', error );
		} finally {
			yield actions.setLoading( 'following', false );
		}
	},

	*fetchInteractions() {
		yield actions.setLoading( 'interactions', true );
		try {
			const interactions = yield apiFetch( {
				path: '/activitypub/v1/interactions',
			} );
			yield actions.setInteractions( interactions as Interaction[] );
		} catch ( error ) {
			console.error( 'Failed to fetch interactions:', error );
		} finally {
			yield actions.setLoading( 'interactions', false );
		}
	},

	*fetchFeed() {
		yield actions.setLoading( 'feed', true );
		try {
			const feed = yield {
				type: 'API_FETCH',
				request: {
					path: '/wp/v2/ap_post',
				},
			};
			yield actions.setFeed( Array.isArray( feed ) ? feed : [] );
		} catch ( error ) {
			console.error( 'Failed to fetch feed:', error );
			yield actions.setFeed( [] );
		} finally {
			yield actions.setLoading( 'feed', false );
		}
	},

	*blockFollower( followerId: string ) {
		try {
			yield apiFetch( {
				path: `/activitypub/v1/followers/${ followerId }/block`,
				method: 'POST',
			} );
			// Refresh followers list
			yield actions.fetchFollowers();
		} catch ( error ) {
			console.error( 'Failed to block follower:', error );
		}
	},

	*removeFollower( followerId: string ) {
		try {
			yield apiFetch( {
				path: `/activitypub/v1/followers/${ followerId }`,
				method: 'DELETE',
			} );
			// Refresh followers list
			yield actions.fetchFollowers();
		} catch ( error ) {
			console.error( 'Failed to remove follower:', error );
		}
	},
};

// Selectors
const selectors = {
	getFollowers( state: State ): Follower[] {
		return state?.followers || [];
	},

	getFollowerById( state: State, id: string ): Follower | undefined {
		return state?.followers?.find( ( follower ) => follower.id === id );
	},

	getFollowing( state: State ): Following[] {
		return state?.following || [];
	},

	getFollowingById( state: State, id: string ): Following | undefined {
		return state?.following?.find( ( following ) => following.id === id );
	},

	getInteractions( state: State ): Interaction[] {
		return state?.interactions || [];
	},

	getInteractionById( state: State, id: string ): Interaction | undefined {
		return state?.interactions?.find( ( interaction ) => interaction.id === id );
	},

	getFeed( state: State ): FeedPost[] {
		return state?.feed || [];
	},

	getFeedPostById( state: State, id: number ): FeedPost | undefined {
		return state?.feed?.find( ( post ) => post.id === id );
	},

	isLoading( state: State, resource: keyof State[ 'isLoading' ] ): boolean {
		return state?.isLoading?.[ resource ] || false;
	},

	getStats( state: State ) {
		return {
			followers: state?.followers?.length || 0,
			following: state?.following?.length || 0,
			interactions: state?.interactions?.length || 0,
			posts: state?.feed?.length || 0,
		};
	},
};

// Reducer
function reducer( state = DEFAULT_STATE, action: Action ): State {
	switch ( action.type ) {
		case 'SET_FOLLOWERS':
			return {
				...state,
				followers: action.followers,
			};

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

		case 'SET_FEED':
			return {
				...state,
				feed: action.feed,
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

// Create and register the store
export const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
	controls,
} );

register( store );
