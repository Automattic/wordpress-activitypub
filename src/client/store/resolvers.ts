/**
 * WordPress dependencies
 */
import { dispatch, select } from '@wordpress/data';
import { store as preferencesStore } from '@wordpress/preferences';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from './index';

/**
 * Resolver to initialize the active actor from preferences
 */
export function* getActiveActorId() {
	const preferences = select( preferencesStore );
	const savedActorId = preferences.get( 'activitypub/client', 'activeActorId' );

	if ( savedActorId !== undefined && savedActorId !== null ) {
		// Restore saved actor ID
		yield dispatch( STORE_NAME ).setActiveActor( savedActorId );
	} else {
		// No saved preference, initialize with current user ID
		const currentUser = select( coreStore ).getCurrentUser();
		if ( currentUser?.id ) {
			yield dispatch( STORE_NAME ).setActiveActor( currentUser.id );
		}
	}
}

export const resolvers = {
	getActiveActorId,
};
