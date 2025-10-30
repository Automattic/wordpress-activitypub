/**
 * Type declarations for the social-web store
 */

import { Follower, Following, Interaction } from '../types';

declare module '@wordpress/data' {
	function select( key: 'activitypub/social-web' ): {
		getFollowers(): Follower[];
		getFollowerById( id: string ): Follower | undefined;
		getFollowing(): Following[];
		getFollowingById( id: string ): Following | undefined;
		getInteractions(): Interaction[];
		getInteractionById( id: string ): Interaction | undefined;
		isLoading( resource: 'followers' | 'following' | 'interactions' ): boolean;
		getStats(): {
			followers: number;
			following: number;
			interactions: number;
			posts: number;
		};
	};

	function dispatch( key: 'activitypub/social-web' ): {
		setFollowers( followers: Follower[] ): void;
		setFollowing( following: Following[] ): void;
		setInteractions( interactions: Interaction[] ): void;
		setLoading( resource: 'followers' | 'following' | 'interactions', isLoading: boolean ): void;
		fetchFollowers(): void;
		fetchFollowing(): void;
		fetchInteractions(): void;
		blockFollower( followerId: string ): void;
		removeFollower( followerId: string ): void;
	};
}
