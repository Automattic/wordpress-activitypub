/**
 * Internal dependencies
 */
import type { Follower, Following, Interaction, FeedPost } from '../types';
import type { State } from './types';

/**
 * Store selectors
 */
export const selectors = {
	getFeed( state: State ): FeedPost[] {
		return state.feed;
	},

	getFeedPostById( state: State, id: number ): FeedPost | undefined {
		return state.feed.find( ( post ) => post.id === id );
	},

	getFollowers( state: State ): Follower[] {
		return state.followers;
	},

	getFollowerById( state: State, id: string ): Follower | undefined {
		return state.followers.find( ( follower ) => follower.id === id );
	},

	getFollowing( state: State ): Following[] {
		return state.following;
	},

	getFollowingById( state: State, id: string ): Following | undefined {
		return state.following.find( ( following ) => following.id === id );
	},

	getInteractions( state: State ): Interaction[] {
		return state.interactions;
	},

	getInteractionById( state: State, id: string ): Interaction | undefined {
		return state.interactions.find( ( interaction ) => interaction.id === id );
	},

	isLoading( state: State, resource: keyof State[ 'isLoading' ] ): boolean {
		return state.isLoading[ resource ];
	},

	getStats( state: State ) {
		return {
			followers: state.followers.length,
			following: state.following.length,
			interactions: state.interactions.length,
			posts: state.feed.length,
		};
	},
};
