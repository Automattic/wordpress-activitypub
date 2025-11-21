/**
 * Store state interface
 */
export interface State {
	activeActorId: number | null;
	selectedTagId: number | null;
}

/**
 * Action Types
 */
export const SET_ACTIVE_ACTOR = 'SET_ACTIVE_ACTOR' as const;
export const SET_SELECTED_TAG = 'SET_SELECTED_TAG' as const;

export interface SetActiveActorAction {
	type: typeof SET_ACTIVE_ACTOR;
	actorId: number;
}

export interface SetSelectedTagAction {
	type: typeof SET_SELECTED_TAG;
	tagId: number | null;
}

export type Action = SetActiveActorAction | SetSelectedTagAction;

/**
 * Initial state
 */
export const DEFAULT_STATE: State = {
	activeActorId: null,
	selectedTagId: null,
};
