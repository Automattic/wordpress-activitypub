/**
 * WordPress dependencies
 */
import { createReduxStore, register } from '@wordpress/data';
import { controls as dataControls } from '@wordpress/data-controls';

/**
 * Custom controls for async operations
 */
const controls = {
	...dataControls,
	API_FETCH( action ) {
		return apiFetch( action.request );
	},
};

/**
 * Internal dependencies
 */
import { actions } from './actions';
import { selectors } from './selectors';
import { reducer } from './reducer';

/**
 * Store name
 */
export const STORE_NAME = 'activitypub/social-web';

/**
 * Create and register the store
 */
export const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
	controls,
} );

register( store );

/**
 * Re-export types for convenience
 */
export type { State } from './types';
