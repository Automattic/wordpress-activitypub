/**
 * WordPress dependencies
 */
import { dispatch } from '@wordpress/data';
import { store as preferencesStore } from '@wordpress/preferences';

/**
 * Internal dependencies
 */
import type { SetActiveActorAction, SetSelectedTagAction } from './types';
import { SET_ACTIVE_ACTOR, SET_SELECTED_TAG } from './types';

/**
 * Store actions
 */
export const actions = {
	*setActiveActor( actorId: number ) {
		// Save to preferences
		yield dispatch( preferencesStore ).set( 'activitypub/social-web', 'activeActorId', actorId );

		// Update state
		return {
			type: SET_ACTIVE_ACTOR,
			actorId,
		} as SetActiveActorAction;
	},

	setSelectedTag( tagId: number | null ): SetSelectedTagAction {
		return {
			type: SET_SELECTED_TAG,
			tagId,
		};
	},
};
