/**
 * WordPress dependencies
 */
import { createReduxStore, register } from '@wordpress/data';
import { controls as dataControls } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { actions } from './actions';
import { selectors } from './selectors';
import { reducer } from './reducer';
import { resolvers } from './resolvers';
import type { State } from './types';

/**
 * Store name
 */
export const STORE_NAME = 'activitypub/app';

/**
 * Store configuration
 */
const storeConfig = {
	reducer,
	actions,
	selectors,
	resolvers,
	controls: dataControls,
};

/**
 * Create and register the store
 */
export const store = createReduxStore( STORE_NAME, storeConfig );

register( store );

/**
 * Re-export types for convenience
 */
export type { State } from './types';

/**
 * Store types for TypeScript
 */
export interface AppSelectors {
	getActiveActorId(): number | null;
}

export interface AppActions {
	setActiveActor( actorId: number ): void;
}

/**
 * Type helpers for using the store
 */
declare module '@wordpress/data' {
	function select( storeNameOrDefinition: typeof STORE_NAME ): AppSelectors;
	function dispatch( storeNameOrDefinition: typeof STORE_NAME ): AppActions;
}
