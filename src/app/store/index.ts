/**
 * WordPress dependencies
 */
import { createReduxStore, register, StoreDescriptor } from '@wordpress/data';
import { controls as dataControls } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { actions } from './actions';
import { selectors } from './selectors';
import { reducer } from './reducer';
import { resolvers } from './resolvers';

/**
 * Store name
 */
export const STORE_NAME = 'activitypub/app';

/**
 * Store configuration
 */
interface StoreConfig {
	reducer: typeof reducer;
	actions: typeof actions;
	selectors: typeof selectors;
	resolvers: typeof resolvers;
	controls: typeof dataControls;
}

const storeConfig: StoreConfig = {
	reducer,
	actions,
	selectors,
	resolvers,
	controls: dataControls,
};

/**
 * Create and register the store
 */
export const store: StoreDescriptor = createReduxStore( STORE_NAME, storeConfig );

register( store );

/**
 * Re-export types for convenience
 */
export type { State } from './types';

/**
 * Store types for TypeScript
 */
export interface AppSelectors {
	getActiveActorId: () => number | null;
}

export interface AppActions {
	setActiveActor: ( actorId: number ) => void;
}

/* eslint-disable jsdoc/require-param -- Type declarations don't need param docs */
/**
 * Type helpers for using the store.
 * Extends the WordPress data module with typed selectors and dispatchers.
 */
declare module '@wordpress/data' {
	function select( storeNameOrDefinition: typeof STORE_NAME ): AppSelectors;
	function dispatch( storeNameOrDefinition: typeof STORE_NAME ): AppActions;
}
/* eslint-enable jsdoc/require-param */
