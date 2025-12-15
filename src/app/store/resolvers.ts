/**
 * WordPress dependencies
 */
import { dispatch, select } from '@wordpress/data';
import { store as preferencesStore } from '@wordpress/preferences';
import { store as coreStore, User } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from './index';
import type { AppActions } from './index';

/**
 * Resolver to initialize the active actor from preferences
 */
export function* getActiveActorId(): Generator {
	const preferences = select( preferencesStore );
	const savedActorId: any = preferences.get( 'activitypub/app', 'activeActorId' );

	if ( savedActorId !== undefined && savedActorId !== null ) {
		// Restore saved actor ID
		( dispatch( STORE_NAME ) as unknown as AppActions ).setActiveActor( savedActorId );
	} else {
		// No saved preference, initialize with current user ID
		const currentUser: User< 'view' > = select( coreStore ).getCurrentUser();
		if ( currentUser?.id ) {
			( dispatch( STORE_NAME ) as unknown as AppActions ).setActiveActor( currentUser.id );
		}
	}
}

export const resolvers = {
	getActiveActorId,
};
