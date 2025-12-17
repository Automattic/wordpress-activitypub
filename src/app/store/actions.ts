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
	setActiveActor( actorId: number ): SetActiveActorAction {
		// Save to preferences
		dispatch( preferencesStore ).set( 'activitypub/app', 'activeActorId', actorId );

		return {
			type: SET_ACTIVE_ACTOR,
			actorId,
		};
	},
};
