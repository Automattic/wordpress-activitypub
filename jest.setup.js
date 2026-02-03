/**
 * Jest setup file for testing-library matchers
 */

// Add custom jest matchers from jest-dom
import '@testing-library/jest-dom';

// Set up window.wp for Interactivity API modules (only in jsdom environment)
if ( typeof window !== 'undefined' ) {
	window.wp = window.wp || {
		apiFetch: jest.fn(),
		url: {
			addQueryArgs: jest.fn( ( path ) => path ),
		},
	};
}
