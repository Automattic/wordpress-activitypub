/**
 * Internal dependencies
 */
import type { Follower, Following, Interaction } from '../types';
import type { State } from './types';

/**
 * Store selectors
 */
export const selectors = {
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
			posts: 0, // This would come from a different endpoint
		};
	},
};
