/**
 * WordPress dependencies
 */
import { dispatch, select, resolveSelect } from '@wordpress/data';
import { store as preferencesStore } from '@wordpress/preferences';
import { store as coreStore, User } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { SET_ACTIVE_ACTOR } from './types';

/**
 * Resolver to initialize the active actor from preferences
 */
export function* getActiveActorId(): Generator {
	// Use sync select for preferences (already loaded)
	let actorId: number = select( preferencesStore ).get( 'activitypub/app', 'activeActorId' );

	// No saved preference, initialize with current user ID
	if ( actorId === undefined || actorId === null ) {
		const currentUser: User< 'view' > = yield resolveSelect( coreStore ).getCurrentUser();
		if ( currentUser?.id ) {
			actorId = currentUser.id;
			// Save to preferences for future loads
			dispatch( preferencesStore ).set( 'activitypub/app', 'activeActorId', actorId );
		}
	}

	// Return action to set the state
	if ( actorId !== undefined ) {
		return {
			type: SET_ACTIVE_ACTOR,
			actorId,
		};
	}
}

export const resolvers = {
	getActiveActorId,
};
