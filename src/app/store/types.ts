/**
 * Store state interface
 */
export interface State {
	activeActorId: number | null;
}

/**
 * Action Types
 */
export const SET_ACTIVE_ACTOR = 'SET_ACTIVE_ACTOR' as const;

export interface SetActiveActorAction {
	type: typeof SET_ACTIVE_ACTOR;
	actorId: number;
}

export type Action = SetActiveActorAction;

/**
 * Initial state
 */
export const DEFAULT_STATE: State = {
	activeActorId: null,
};
