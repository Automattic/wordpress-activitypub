/**
 * WordPress dependencies
 */
import { dispatch } from '@wordpress/data';
import { store as preferencesStore } from '@wordpress/preferences';

/**
 * Internal dependencies
 */
import type { SetActiveActorAction } from './types';
import { SET_ACTIVE_ACTOR } from './types';

/**
 * Store actions
 */
export const actions = {
	*setActiveActor( actorId: number ) {
		// Save to preferences
		yield dispatch( preferencesStore ).set( 'activitypub/client', 'activeActorId', actorId );

		// Update state
		return {
			type: SET_ACTIVE_ACTOR,
			actorId,
		} as SetActiveActorAction;
	},
};
