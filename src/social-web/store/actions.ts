/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import type { Follower, Following, Interaction } from '../types';
import type { SetFollowersAction, SetFollowingAction, SetInteractionsAction, SetLoadingAction, State } from './types';

/**
 * Store actions
 */
export const actions = {
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
