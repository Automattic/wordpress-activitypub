/**
 * Mock for @wordpress/interactivity
 *
 * This module is only available in the browser context via WordPress core.
 * We provide a mock for Jest testing.
 */

export const store = jest.fn( ( namespace, config ) => config );
export const getContext = jest.fn( () => ( {} ) );
export const getConfig = jest.fn( () => ( {} ) );
export const getElement = jest.fn( () => ( { ref: {} } ) );

export default {
	store,
	getContext,
	getConfig,
	getElement,
};
